<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Work that takes minutes, run on hosting that allows seconds.
 *
 * WHY THE MEASUREMENTS NOBODY CAN RUN ARE THE ONES THAT MATTER MOST.
 *
 * Measured on this codebase: one Backtest::run over 1000 bars of one pair on
 * one timeframe takes about 4 seconds. Comparing three presets across three
 * timeframes over twenty coins is 180 replays - twelve minutes of solid work.
 * The A/B tool asks for it in a single web request behind
 * @set_time_limit(900), which shared hosting overrides whenever it feels like
 * it, and the level optimiser wants thirty candidate geometries on top of that.
 *
 * So the tools that answer "is this configuration actually any good" existed
 * and could not be run, which is indistinguishable from not having them. The
 * operator gets a white page, a 504, or a half-finished report, and stops
 * asking the question.
 *
 * This is the missing piece and deliberately the dullest thing in the codebase:
 * a queue in a settings row, walked a few items at a time, resumable, and
 * driven by two things that already exist - the cron that runs anyway, and a
 * button. NO WORKER PROCESS, NO REDIS, NO WEBSOCKET. Those are not available
 * here and pretending otherwise would ship a feature that works on a laptop
 * and fails on the customer's host.
 *
 * One job at a time, on purpose. Two long replays racing each other on shared
 * hosting is how an account gets its CPU quota pulled, and "which of my three
 * measurements is the slow one" is not a question anybody wants to answer.
 */
class LongJob
{
    /** Where the whole job lives. One row, so a job survives any restart. */
    private const KEY = 'long_job';

    /**
     * Registered kinds. A kind knows how to build its queue and do one item.
     *
     * The stop-and-target search is here now. It reads its candidates from
     * Backtest::levelGrid(), which is the one definition of the candidate
     * set - the inline optimiser that used to own it is gone, because it
     * could not finish and nobody could see that it had not.
     */
    public const KINDS = [
        'preset_accuracy' => ['Preset accuracy test', PresetTest::class],
        'level_search'    => ['Stop, target and exit search', LevelSearch::class],
    ];

    /** Nothing may run longer than this without a step boundary, in seconds. */
    private const MIN_SLICE = 3.0;

    /** @return array<string,mixed>|null the live job, or null */
    public static function state(): ?array
    {
        $raw = Database::setting(self::KEY, '');
        if ($raw === '') {
            return null;
        }
        $j = json_decode($raw, true);
        return is_array($j) && isset($j['kind']) ? $j : null;
    }

    /** Is there a job that still has work left? */
    public static function pending(): bool
    {
        $j = self::state();
        return $j !== null && empty($j['finished_at']) && !empty($j['queue']);
    }

    /**
     * Begin a job, discarding any finished one.
     *
     * Refuses to trample a job that is still running: a half-finished
     * measurement replaced by a new one is worse than either, because the
     * report it leaves behind is a blend of two configurations and says so
     * nowhere.
     */
    public static function start(string $kind, array $params = []): array
    {
        if (!isset(self::KINDS[$kind])) {
            return ['ok' => false, 'error' => 'Unknown job type.'];
        }
        if (self::pending()) {
            $j = self::state();
            return ['ok' => false, 'error' => 'A ' . (self::KINDS[$j['kind']][0] ?? 'job')
                . ' is already running (' . self::doneCount($j) . ' of ' . (int)$j['total']
                . ' steps). Cancel it first, or let it finish.'];
        }
        $cls = self::KINDS[$kind][1];
        $queue = $cls::queue($params);
        if (!$queue) {
            return ['ok' => false, 'error' => 'Nothing to measure - check that coins are enabled '
                . 'and at least one timeframe is switched on.'];
        }
        $job = [
            'kind'       => $kind,
            'params'     => $params,
            'queue'      => array_values($queue),
            'total'      => count($queue),
            'results'    => $cls::seed($params),
            'log'        => [],
            'started_at' => time(),
            'updated_at' => time(),
            'finished_at' => 0,
            'errors'     => 0,
        ];
        self::put($job);
        return ['ok' => true, 'total' => count($queue)];
    }

    /** Throw the job away. Deliberately one call and no confirmation here -
     *  the confirmation belongs to the screen, which can say what is lost. */
    public static function cancel(): void
    {
        Database::setSetting(self::KEY, '');
    }

    /**
     * step(), guarded so cron and the admin's "Run a step" button can never
     * advance the same job at once.
     *
     * Both call step() with the same starting state when they land together -
     * cron on its own schedule, an operator on a click - and each shifts the
     * same leading items off its own copy of the queue independently. The
     * slower write then overwrites the faster one's progress, and any item
     * both of them reached gets counted twice in the aggregated results. A
     * short, local, non-blocking lock is all that is needed: neither caller
     * has to wait for the other, it only must not overlap it.
     *
     * Fails open, same as the cron lock: a host that cannot flock must not
     * mean the job never advances again.
     *
     * @return array{ran:int, left:int, done:bool, seconds:float, locked?:bool}
     */
    public static function stepLocked(float $seconds = 15.0): array
    {
        $dataDir = defined('SMA_DATA_DIR') ? SMA_DATA_DIR : (__DIR__ . '/../data');
        $fp = @fopen($dataDir . '/longjob.lock', 'c');
        if ($fp !== false && !@flock($fp, LOCK_EX | LOCK_NB)) {
            $job = self::state();
            return [
                'ran' => 0,
                'left' => $job !== null ? count($job['queue'] ?? []) : 0,
                'done' => false,
                'seconds' => 0.0,
                'locked' => true,
            ];
        }
        try {
            return self::step($seconds);
        } finally {
            if ($fp !== false) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }

    /**
     * Do as much of the job as fits in $seconds, then stop cleanly.
     *
     * Stops on a whole item, never inside one, so the stored state is always a
     * consistent set of finished work. Called (through stepLocked() above) by
     * cron with a slice of its own budget, and by the admin screen when
     * somebody would rather watch it move than wait for the next cron tick.
     *
     * @return array{ran:int, left:int, done:bool, seconds:float}
     */
    public static function step(float $seconds = 15.0): array
    {
        $job = self::state();
        if ($job === null || !empty($job['finished_at']) || empty($job['queue'])) {
            return ['ran' => 0, 'left' => 0, 'done' => true, 'seconds' => 0.0];
        }
        $seconds = max(self::MIN_SLICE, $seconds);
        $cls = self::KINDS[$job['kind']][1] ?? null;
        if ($cls === null) {
            self::cancel();
            return ['ran' => 0, 'left' => 0, 'done' => true, 'seconds' => 0.0];
        }

        $t0 = microtime(true);
        $deadline = $t0 + $seconds;
        $ran = 0;

        while ($job['queue'] !== []) {
            // Checked BEFORE taking an item, not after finishing one: an item
            // costs about four seconds, so a check on the way out can overrun
            // the budget by a whole item and take the cron process with it.
            if ($ran > 0 && microtime(true) >= $deadline - self::MIN_SLICE) {
                break;
            }
            $item = array_shift($job['queue']);
            try {
                $job['results'] = $cls::work($item, $job['results'], $job['params']);
            } catch (\Throwable $e) {
                // One bad pair must not end a twelve-minute measurement. It is
                // counted and named, because a report quietly missing a coin
                // is a report that lies about its own sample.
                $job['errors']++;
                if (count($job['log']) < 40) {
                    $job['log'][] = self::describe($item) . ': ' . $e->getMessage();
                }
            }
            $ran++;
            $job['updated_at'] = time();
            // Written every item. A cron killed mid-slice then loses one item
            // of work rather than the whole run.
            self::put($job);
        }

        if ($job['queue'] === []) {
            $job['finished_at'] = time();
            if (method_exists($cls, 'finish')) {
                $job['results'] = $cls::finish($job['results'], $job['params']);
            }
            self::put($job);
        }
        return [
            'ran' => $ran,
            'left' => count($job['queue']),
            'done' => $job['queue'] === [],
            'seconds' => round(microtime(true) - $t0, 2),
        ];
    }

    /** Steps completed, for a progress line. */
    public static function doneCount(?array $job = null): int
    {
        $job ??= self::state();
        if ($job === null) {
            return 0;
        }
        return max(0, (int)$job['total'] - count($job['queue'] ?? []));
    }

    /** Rough seconds left, from how long the finished steps actually took. */
    public static function etaSeconds(?array $job = null): ?int
    {
        $job ??= self::state();
        if ($job === null || empty($job['queue'])) {
            return null;
        }
        $done = self::doneCount($job);
        if ($done < 1) {
            return null;
        }
        $elapsed = max(1, time() - (int)$job['started_at']);
        return (int)round($elapsed / $done * count($job['queue']));
    }

    /** A queue item as a human-readable string, for the error log. */
    private static function describe(array $item): string
    {
        return implode(' ', array_map(
            static fn ($v) => is_scalar($v) ? (string)$v : '?',
            array_slice($item, 0, 4)
        ));
    }

    /**
     * Store the job.
     *
     * Guarded on size: settings rows have bitten this codebase before, where a
     * learning blob grew past its column and coins were silently dropped off
     * the end to make it fit. A job that cannot be stored is stopped and says
     * why, rather than continuing to run against a state that is not saving.
     */
    private static function put(array $job): void
    {
        $raw = json_encode($job);
        if ($raw === false) {
            return;
        }
        if (strlen($raw) > 240000) {
            $job['queue'] = [];
            $job['finished_at'] = time();
            $job['log'][] = 'Stopped: the job state grew past what a settings row can hold. '
                . 'Measure fewer coins or fewer timeframes at once.';
            $raw = json_encode($job);
        }
        Database::setSetting(self::KEY, (string)$raw);
    }
}
