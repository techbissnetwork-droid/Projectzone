<?php
declare(strict_types=1);

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\Maintenance;

Auth::requireLogin();
$pdo = Database::pdo();
$error = null;

// A starting point, not a strategy.
//
// 142 fields with no reference point means tuning is guesswork, and
// the most common outcome is a configuration nobody can explain and
// nobody dares change back. A preset writes one coherent set of engine
// values in a single step - and because every field it touches goes
// through the change log, going back is reading a list, not guessing.
//
// The values are not opinions. "Selective" is what this install's own
// replayed history measured as better: the fast timeframe it loses on
// dropped, the whole position taken at the first target rather than
// scaled out, and the reach and extension gates tightened. "Active" is
// offered because some operators want volume, and it is labelled as
// what it is rather than dressed up.
// The values themselves live in Presets, because three things want them now:
// this page, the A/B replay that can load one as a challenger, and the card
// below that reports what each has actually done here. Copied into each of
// those, they drift, and an operator ends up pressing a button whose values
// are no longer the values described beside it.
// values(), not DEFS: the threshold has to be written under the name the
// engine actually resolves it from, which depends on the scoring mode. DEFS
// alone wrote the pair that 'category' mode - the default - never reads, so
// the headline of every preset was inert.
$presets = [];
foreach (array_keys(\SignalMasterAi\Presets::DEFS) as $pKey) {
    $presets[$pKey] = \SignalMasterAi\Presets::values($pKey);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'revert') {
        // One key, back to what it ships with. Deliberately one at a time:
        // "revert everything" is a button whose only use is the one time
        // somebody presses it by accident.
        $k = (string)($_POST['key'] ?? '');
        $defs = Database::defaultSettings($config);
        if (array_key_exists($k, $defs)) {
            $before = Database::setting($k, '');
            Database::setSetting($k, (string)$defs[$k]);
            // No actor argument: Audit::log() fills in the signed-in admin.
            // There is no ACTOR_ADMIN constant - referencing one is a fatal error,
            // and it fataled AFTER the write, so the change landed and the
            // response was a 500.
            \SignalMasterAi\Audit::log('setting.change', $k, $before, (string)$defs[$k]);
            flash('Reverted ' . $k . ' to its default.');
        }
        header('Location: settings.php#changed');
        exit;
    }
    if ($act === 'master') {
        // One feature at a time, so a form that failed to render cannot read
        // as "turn everything off".
        \SignalMasterAi\Master::save((string)($_POST['feature'] ?? ''), $_POST);
        flash('Saved.');
        header('Location: settings.php#masters');
        exit;
    }
    if ($act === 'preset') {
        $name = (string)($_POST['preset'] ?? '');
        if (isset($presets[$name])) {
            $before = [];
            foreach (array_keys($presets[$name]) as $k) {
                $before[$k] = Database::setting($k, '');
            }
            foreach ($presets[$name] as $k => $v) {
                Database::setSetting($k, $v);
                // The retired scan/re-calibration keys follow the one list, the
                // same way they do on a normal save. Nothing reads them, but a
                // preset that left them disagreeing with the site is a trap for
                // whoever greps for the old name next.
                if ($k === 'enabled_intervals') {
                    Database::setSetting('fullscan_tfs', $v);
                    Database::setSetting('auto_backtest_tfs', $v);
                }
            }
            $n = \SignalMasterAi\Audit::settings($before, $presets[$name]);
            \SignalMasterAi\Audit::log('preset.apply', $name, '', $n . ' setting(s) changed');
            flash(ucfirst($name) . " preset applied - $n setting(s) changed."
                . ' Every one is listed in the change log if you want it back.');
        }
        header('Location: settings.php#signals');
        exit;
    }

    if ($act === 'settings') {
        $map = [
            'site_name'        => fn($v) => trim($v) !== '' ? trim($v) : 'SignalMasterAi',
            'site_tagline'     => fn($v) => trim($v),
            'site_url'         => fn($v) => preg_match('#^https?://[^\s]+$#', rtrim(trim($v), '/')) ? rtrim(trim($v), '/') : '',
            'landing_enabled'  => fn($v) => $v === '1' ? '1' : '0',
            'webfonts'         => fn($v) => $v === '1' ? '1' : '0',
            'hero_title'       => fn($v) => trim($v) !== '' ? trim($v) : 'AI-Powered Signals, Smarter Trading',
            'hero_subtitle'    => fn($v) => trim($v),
            'hero_cta'         => fn($v) => trim($v) !== '' ? trim($v) : 'View live signals',
            'site_notice'      => fn($v) => trim($v) !== '' ? trim($v) : 'Not financial advice.',
            'seo_title'        => fn($v) => mb_substr(trim($v), 0, 120),
            'performance_page_enabled' => fn($v) => $v === '1' ? '1' : '0',
            // Every <textarea> submit normalises \n to \r\n per the HTML spec,
            // so a save that never touches these fields still rewrites their
            // LF line breaks to CRLF - which never matches the LF-only
            // defaults above again, and "Changed from default" says so
            // forever. Folded back to \n before it's stored, not just before
            // it's compared, so the value itself stays clean too.
            'terms_content'    => fn($v) => mb_substr(trim(str_replace("\r\n", "\n", $v)), 0, 20000),
            'privacy_content'  => fn($v) => mb_substr(trim(str_replace("\r\n", "\n", $v)), 0, 20000),
            'risk_content'     => fn($v) => mb_substr(trim(str_replace("\r\n", "\n", $v)), 0, 20000),
            'meta_description' => fn($v) => mb_substr(trim($v), 0, 300),
            'meta_keywords'    => fn($v) => mb_substr(trim($v), 0, 300),
            'api_mode'         => fn($v) => in_array($v, ['auto', 'single'], true) ? $v : 'auto',
            // Optional language-model layer (describes the analysis; never
            // participates in the decision).
            'ai_enabled'       => fn($v) => $v === '1' ? '1' : '0',
            'ai_endpoint'      => fn($v) => preg_match('#^https://[^\s]+$#', $u = rtrim(trim($v), '/')) ? $u : 'https://api.openai.com/v1/chat/completions',
            'ai_api_key'       => fn($v) => trim($v),
            'ai_model'         => fn($v) => mb_substr(trim($v), 0, 64) ?: 'gpt-4o-mini',
            'ai_ask_tier'      => fn($v) => in_array($v, ['any', 'free', 'paid'], true) ? $v : 'paid',
            // Public broadcast channels.
            'broadcast_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'broadcast_tg_chat' => fn($v) => mb_substr(trim($v), 0, 64),
            'broadcast_discord_url' => fn($v) => preg_match('#^https://[^\s]+$#', trim($v)) ? trim($v) : '',
            'broadcast_min_grade' => fn($v) => in_array($v, ['B', 'A', 'A+'], true) ? $v : 'A',
            // Referral credit, paid in premium days.
            'referral_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'referral_days'    => fn($v) => (string)max(1, min(365, (int)$v)),
            // Data quality and storage.
            'auto_backfill_gaps' => fn($v) => $v === '1' ? '1' : '0',
            'candle_retention_bars' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'candle_retention_idle_bars' => fn($v) => (string)max(300, min(100000, (int)$v)),
            'rate_limit_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'admin_idle_minutes' => fn($v) => (string)max(0, min(43200, (int)$v)),
            'hsts_enabled'     => fn($v) => $v === '1' ? '1' : '0',
            'login_attempt_limit' => fn($v) => (string)max(3, min(50, (int)$v)),
            'login_attempt_window' => fn($v) => (string)max(60, min(3600, (int)$v)),
            'trust_proxy'      => fn($v) => $v === '1' ? '1' : '0',
            'funding_api_url'  => fn($v) => preg_match('#^https?://[^\s]+$#', $u = rtrim(trim($v), '/')) ? $u : 'https://fapi.binance.com',
            'default_symbol'   => fn($v) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v) ?: 'BTCUSDT'),
            'default_interval' => fn($v) => in_array($v, $config['market']['intervals'], true) ? $v : '1h',
            'cache_ttl'        => fn($v) => (string)max(30, min(86400, (int)$v)),
            'candle_ttl_divisor' => fn($v) => (string)max(1, min(60, (int)$v)),
            'fetch_concurrency'  => fn($v) => (string)max(1, min(50, (int)$v)),
            'fetch_prefetch_max' => fn($v) => (string)max(0, min(1000, (int)$v)),
            'fullscan_bar_gate'  => fn($v) => $v === '1' ? '1' : '0',
            'candle_limit'     => fn($v) => (string)max(100, min(1000, (int)$v)),
            'buy_threshold'    => fn($v) => (string)max(0.5, min(20, (float)$v)),
            'sell_threshold'   => fn($v) => (string)min(-0.5, max(-20, (float)$v)),
            'news_enabled'     => fn($v) => $v === '1' ? '1' : '0',
            'news_cache_ttl'   => fn($v) => (string)max(60, min(86400, (int)$v)),
            'news_max_items'   => fn($v) => (string)max(5, min(100, (int)$v)),
            'news_retention_days' => fn($v) => (string)max(1, min(365, (int)$v)),
            'news_poll_seconds'=> fn($v) => (string)max(30, min(3600, (int)$v)),
            'signal_auto_seconds' => fn($v) => (string)max(0, min(3600, (int)$v)),
            'alerts_enabled'   => fn($v) => $v === '1' ? '1' : '0',
            'registration_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'email_otp'        => fn($v) => $v === '1' ? '1' : '0',
            'otp_ttl_min'      => fn($v) => (string)max(2, min(1440, (int)$v)),
            'otp_tries'        => fn($v) => (string)max(1, min(20, (int)$v)),
            'otp_resend_sec'   => fn($v) => (string)max(15, min(3600, (int)$v)),
            'levels_enabled'   => fn($v) => $v === '1' ? '1' : '0',
            'time_stop_bars'   => fn($v) => (string)max(1, min(500, (int)$v)),
            'learn_window_rows' => fn($v) => (string)max(50, min(100000, (int)$v)),
            'learn_model_rows'  => fn($v) => (string)max(200, min(200000, (int)$v)),
            'learn_window_days' => fn($v) => (string)max(0, min(3650, (int)$v)),
            'breakeven_after_tp1' => fn($v) => $v === '1' ? '1' : '0',
            'tp1_partial_pct'  => fn($v) => (string)max(0, min(100, (int)$v)),
            'tp2_partial_pct'  => fn($v) => (string)max(0, min(100, (int)$v)),
            'regime_filter_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'closed_candle_only' => fn($v) => $v === '1' ? '1' : '0',
            'autotune_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'autotune_min_samples' => fn($v) => (string)max(5, min(200, (int)$v)),
            'calibrated_confidence' => fn($v) => $v === '1' ? '1' : '0',
            'score_band_gate' => fn($v) => $v === '1' ? '1' : '0',
            // Floors chosen so the gate cannot be made reckless from the form:
            // it can never act on fewer than 20 settled signals in a band, and
            // it can never be told to refuse a band winning more than 60%.
            'score_band_min_n' => fn($v) => (string)max(20, min(500, (int)$v)),
            'score_band_min_winrate' => fn($v) => (string)max(0, min(60, (int)$v)),
            'score_band_max_r' => fn($v) => (string)max(-1.0, min(0.5, (float)$v)),
            'preflight_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'preflight_max_stale_bars' => fn($v) => (string)max(1, min(50, (int)$v)),
            'preflight_max_gap_pct' => fn($v) => (string)max(0, min(50, (int)$v)),
            'preflight_max_travelled_pct' => fn($v) => (string)max(0, min(100, (int)$v)),
            'min_rr' => fn($v) => (string)max(1, min(3, (int)$v)),
            'trial_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'trial_days' => fn($v) => (string)max(0, min(365, (int)$v)),
            'trial_max_per_ip' => fn($v) => (string)max(1, min(100, (int)$v)),
            'trial_ip_days' => fn($v) => (string)max(0, min(3650, (int)$v)),
            'trial_device_check' => fn($v) => $v === '1' ? '1' : '0',
            'smc_structure_max_age' => fn($v) => (string)max(5, min(500, (int)$v)),
            'auto_backtest_enabled' => fn($v) => $v === '1' ? '1' : '0',
            // Accepts the checkbox array AND the old comma string, so a saved
            // value from before the control changed shape still loads.
            'tg_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'digest_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'digest_hour' => fn($v) => (string)max(0, min(23, (int)$v)),
            'flip_exit_band' => fn($v) => (string)max(0, min(0.95, (float)$v)),
            'cooldown_bars' => fn($v) => (string)max(0, min(100, (int)$v)),
            'event_caution_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'event_caution_hours' => fn($v) => (string)max(0, min(48, (int)$v)),
            'panic_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'panic_move_pct' => fn($v) => (string)max(1.0, min(20.0, (float)$v)),
            'panic_hold_min' => fn($v) => (string)max(10, min(720, (int)$v)),
            'watchdog_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'watchdog_window' => fn($v) => (string)max(10, min(500, (int)$v)),
            'watchdog_floor_r' => fn($v) => (string)max(-2.0, min(1.0, (float)$v)),
            'quarantine_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'quarantine_min_r' => fn($v) => (string)max(-2.0, min(0.0, (float)$v)),
            'quarantine_min_samples' => fn($v) => (string)max(10, min(500, (int)$v)),
            'quarantine_days' => fn($v) => (string)max(1, min(120, (int)$v)),
            'funding_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'funding_extreme_pct' => fn($v) => (string)max(0.005, min(1, (float)$v)),
            'fng_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'context_panel_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'mtf_strip_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'taker_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'taker_extreme' => fn($v) => (string)max(1.01, min(3.0, (float)$v)),
            'breadth_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'breadth_bull' => fn($v) => (string)max(50, min(95, (int)$v)),
            'breadth_bear' => fn($v) => (string)max(5, min(50, (int)$v)),
            'btc_corr_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'btc_corr_min' => fn($v) => (string)max(0.0, min(0.9, (float)$v)),
            'btc_corr_bars' => fn($v) => (string)max(30, min(500, (int)$v)),
            'alert_min_grade'  => fn($v) => in_array($v, ['any', 'B', 'A', 'A+'], true) ? $v : 'any',
            'member_grade_filter' => fn($v) => $v === '1' ? '1' : '0',
            'member_engine_profile' => fn($v) => $v === '1' ? '1' : '0',
            'show_min_grade'   => fn($v) => in_array($v, ['any', 'B', 'A', 'A+'], true) ? $v : 'any',
            'alert_on_subscribe' => fn($v) => $v === '1' ? '1' : '0',
            'dupe_window_hours' => fn($v) => (string)max(0, min(720, (int)$v)),
            // Clamped, not free: below 0.1 every restatement becomes a new
            // trade and the open-call guard stops meaning anything.
            'reissue_min_risk' => fn($v) => (string)max(0.1, min(5.0, (float)$v)),
            'perf_show_stats'      => fn($v) => $v === '1' ? '1' : '0',
            'perf_show_breakdowns' => fn($v) => $v === '1' ? '1' : '0',
            'perf_show_trades'     => fn($v) => $v === '1' ? '1' : '0',
            'perf_page_size'       => fn($v) => (string)((int)$v <= 0 ? 0 : max(10, (int)$v)),
            'perf_detail'          => fn($v) => $v === '1' ? '1' : '0',
            'perf_wide_table'      => fn($v) => $v === '1' ? '1' : '0',
            'scanner_rows'         => fn($v) => (string)max(0, (int)$v),
            'signal_retention_days' => fn($v) => (string)max(0, min(3650, (int)$v)),
            'audit_retention_days'  => fn($v) => (string)max(7, min(3650, (int)$v)),
            'error_retention_days'  => fn($v) => (string)max(1, min(365, (int)$v)),
            'email_log_days'        => fn($v) => (string)max(0, min(3650, (int)$v)),
            // admin_signals_per is not here: the signals page writes it itself
            // when an operator changes its rows-per-page. A cleaner for a field
            // this form never renders reads as a control that failed to draw.
            'alert_email_max_detail' => fn($v) => (string)max(1, min(200, (int)$v)),
            'canonical_host'   => fn($v) => in_array($v, ['both', 'www', 'nonwww'], true) ? $v : 'both',
            'clean_urls'       => fn($v) => $v === '1' ? '1' : '0',
            'alert_email_batch' => fn($v) => $v === '0' ? '0' : '1',
            'alert_bundle_min' => fn($v) => (string)max(0, min(720, (int)$v)),
            'alert_tg_batch'   => fn($v) => $v === '0' ? '0' : '1',
            'fullscan_enabled' => fn($v) => $v === '1' ? '1' : '0',
            // Kept accepting the old comma string as well as the checkbox
            // array, so an upgrade does not lose a scan list mid-flight.
            // No ceiling worth defending: a 479-coin watchlist needs a batch
            // in the hundreds, and what protects the run is the time budget,
            // not a count that means different things on different hosts.
            'fullscan_batch' => fn($v) => (string)max(1, min(5000, (int)$v)),
            'fullscan_max_seconds' => fn($v) => (string)max(0, min(3600, (int)$v)),
            // Wall-clock budget for one NewsFetcher::refreshIfStale() sweep -
            // see the class docblock. 0 disables the cap outright (the old,
            // unbounded behaviour) rather than "work it out", because unlike
            // the scan there is no natural fallback figure to derive; the
            // shipped default of 20 is the safety net and this only needs
            // touching on an install with many sources or a slow feed.
            'news_fetch_max_seconds' => fn($v) => (string)max(0, min(300, (int)$v)),
            'scan_autodisable_fails' => fn($v) => (string)max(0, min(50, (int)$v)),
            'cron_interval_min' => fn($v) => (string)max(0, min(1440, (int)$v)),
            'store_neutral' => fn($v) => $v === '1' ? '1' : '0',
            // Feed switches and operator levers that had no control at all
            // until the audit went looking for settings no screen could
            // change - see the cards they now live in.
            'oi_enabled'             => fn($v) => $v === '1' ? '1' : '0',
            'book_enabled'           => fn($v) => $v === '1' ? '1' : '0',
            'session_filter_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'rate_limit_scale'       => fn($v) => (string)max(0.1, min(20.0, (float)$v)),
            'twelvedata_key'         => fn($v) => trim($v),
            'stooq_endpoint'         => fn($v) => trim($v),
            'futures_endpoints'      => fn($v) => trim($v),
            'onchain_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'onchain_caution' => fn($v) => $v === '1' ? '1' : '0',
            'onchain_surge_ratio' => fn($v) => (string)max(1.1, min(10.0, (float)$v)),
            'onchain_volume_url' => fn($v) => trim($v),
            'onchain_mempool_url' => fn($v) => trim($v),
            // Tiered limits - see Limits::LIMITS. Written out because the
            // map is read before the class list is walked anywhere else,
            // and a cleaner that arrives late is a field that saves
            // nothing.
            //
            // watch_max_* is NOT here - it used to be, with a 100000 cap,
            // duplicating the key further down (20000 cap, matching
            // bulk_watch_max) in the same array literal. PHP keeps only the
            // last of two duplicate keys, so this copy was always dead and
            // every save was already being clamped to 20000 regardless of
            // what this entry or the form's max="100000" implied. Removed
            // rather than reconciled upward, since 20000 is the ceiling
            // Limits::LIMITS['watch']['max'] documents on purpose.
            'rows_max_guest' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'rows_max_free' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'rows_max_paid' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'bt_max_guest' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'bt_max_free' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'bt_max_paid' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'ask_max_guest' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'ask_max_free' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'ask_max_paid' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'pos_max_guest' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'pos_max_free' => fn($v) => (string)max(0, min(100000, (int)$v)),
            'pos_max_paid' => fn($v) => (string)max(0, min(100000, (int)$v)),
            // Member tools - see the card. These had no control at all
            // until now, so they had no cleaner either.
            'paper_enabled'          => fn($v) => $v === '1' ? '1' : '0',
            'trade_email_enabled'    => fn($v) => $v === '1' ? '1' : '0',
            'paper_max_leverage'     => fn($v) => (string)max(1, min(
                (int)\SignalMasterAi\Paper::MAX_LEVERAGE_CEILING, (int)$v)),
            'paper_leverages'        => function ($v) {
                // Stored tidy so the ticket and this box agree on what was
                // typed: numbers only, in order, no duplicates, nothing above
                // the ceiling, and never empty.
                $out = [];
                foreach (explode(',', (string)$v) as $bit) {
                    $n = (float)trim($bit);
                    if ($n >= 1 && !in_array($n, $out, true)) {
                        $out[] = $n;
                    }
                }
                sort($out);
                return $out ? implode(',', array_map(
                    static fn($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.'), $out
                )) : \SignalMasterAi\Paper::LEVERAGE_LADDER_DEFAULT;
            },
            'member_backtest_enabled'=> fn($v) => $v === '1' ? '1' : '0',
            'api_feed_enabled'       => fn($v) => $v === '1' ? '1' : '0',
            'webhooks_enabled'       => fn($v) => $v === '1' ? '1' : '0',
            'learned_model_enabled'  => fn($v) => $v === '1' ? '1' : '0',
            'sym_learn_enabled'      => fn($v) => $v === '1' ? '1' : '0',
            'sym_learn_mode'         => fn($v) => in_array($v, ['auto','coin','global'], true) ? $v : 'auto',
            'shadow_enabled'         => fn($v) => $v === '1' ? '1' : '0',
            'shadow_learn'           => fn($v) => $v === '1' ? '1' : '0',
            'shadow_near_band'       => fn($v) => (string)max(0.1, min(0.99, (float)$v)),
            'shadow_retention_days'  => fn($v) => (string)max(7, min(3650, (int)$v)),
            'sym_learn_k'            => fn($v) => (string)max(1, min(1000, (int)$v)),
            'bulk_watch_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'bulk_watch_tier'  => fn($v) => in_array($v, ['paid', 'free', 'any'], true) ? $v : 'paid',
            'bulk_watch_max'   => fn($v) => (string)max(0, min(20000, (int)$v)),
            // Per-tier watchlist size. 0 = no limit; the upper bound is the
            // same one bulk_watch_max has always had.
            'watch_max_paid'   => fn($v) => (string)max(0, min(20000, (int)$v)),
            'watch_max_free'   => fn($v) => (string)max(0, min(20000, (int)$v)),
            'watch_max_guest'  => fn($v) => (string)max(0, min(20000, (int)$v)),
            'top_watch_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'top_watch_tier'   => fn($v) => in_array($v, ['paid', 'free', 'any'], true) ? $v : 'paid',
            'top_watch_count'  => fn($v) => (string)max(1, min(200, (int)$v)),
            // Never below 5. A floor of 1 makes the ranking a list of coins
            // that won once, which is the failure this number exists to stop.
            'top_watch_min'    => fn($v) => (string)max(5, min(1000, (int)$v)),
            'upsell_popup_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'upsell_popup_new' => fn($v) => $v === '1' ? '1' : '0',
            'upsell_popup_days' => fn($v) => (string)max(0, min(365, (int)$v)),
            // Never zero. 0 would mean "show it again on the very next page
            // load after they closed it", which is not a setting an operator
            // wants by accident - it is the behaviour that gets a site
            // reported as malware.
            'upsell_popup_repeat' => fn($v) => (string)max(1, min(365, (int)$v)),
            'promo_enabled'   => fn($v) => $v === '1' ? '1' : '0',
            'promo_coupon'    => fn($v) => strtoupper(trim((string)$v)),
            'promo_headline'  => fn($v) => trim((string)$v),
            'promo_body'      => fn($v) => trim((string)$v),
            // datetime-local arrives as "2026-08-12T18:00" in the SERVER's
            // clock, which is what the campaign window is measured in. Blank
            // stays 0, meaning unbounded, rather than becoming 1970.
            'promo_starts'    => fn($v) => (string)(trim((string)$v) === '' ? 0 : (strtotime((string)$v) ?: 0)),
            'promo_ends'      => fn($v) => (string)(trim((string)$v) === '' ? 0 : (strtotime((string)$v) ?: 0)),
            'promo_audience'  => fn($v) => in_array($v, ['all', 'free', 'paid', 'expired', 'new'], true) ? $v : 'free',
            'promo_new_days'  => fn($v) => (string)max(1, min(365, (int)$v)),
            'promo_emails'    => fn($v) => implode(',', array_slice(array_filter(array_map(
                                    static fn($e) => strtolower(trim((string)$e)),
                                    preg_split('/[,\s;]+/', (string)$v) ?: [])), 0, 500)),
            'promo_random_pct' => fn($v) => (string)max(0, min(100, (int)$v)),
            'promo_banner'    => fn($v) => $v === '1' ? '1' : '0',
            'promo_popup'     => fn($v) => $v === '1' ? '1' : '0',
            'promo_timer'     => fn($v) => $v === '1' ? '1' : '0',
            'promo_repeat_hours' => fn($v) => (string)max(1, min(8760, (int)$v)),
            'sl_atr_mult'      => fn($v) => (string)max(0.1, min(10, (float)$v)),
            'tp1_atr_mult'     => fn($v) => (string)max(0.1, min(20, (float)$v)),
            'tp2_atr_mult'     => fn($v) => (string)max(0.1, min(20, (float)$v)),
            'tp3_atr_mult'     => fn($v) => (string)max(0.1, min(20, (float)$v)),
            'liq_clusters_enabled' => fn($v) => $v === '1' ? '1' : '0',
            'liq_lookback_bars'    => fn($v) => (string)max(60, min(1000, (int)$v)),
            'liq_stop_min_weight'  => fn($v) => (string)max(0.0, min(1.0, (float)$v)),
            // Leverage tiers to project liquidations for. Anything at or below
            // 1x cannot be liquidated, so it is dropped rather than stored.
            'liq_leverages'    => function ($v) {
                $tiers = array_values(array_unique(array_filter(
                    array_map('intval', explode(',', (string)$v)),
                    fn(int $l): bool => $l > 1 && $l <= 500
                )));
                sort($tiers);
                return $tiers ? implode(',', $tiers) : '10,25,50,100';
            },
            'smtp_mode'        => fn($v) => in_array($v, ['off', 'smtp', 'phpmail'], true) ? $v : 'off',
            'smtp_host'        => fn($v) => trim($v),
            // An emptied port meant port 1, because (int)'' is 0 and the floor
            // pulled it up to 1. Port 1 is not a mail port on any server, and
            // nothing said so - SMTP just stopped connecting, and the changed
            // list reported "1" as if someone had chosen it. Empty means "use
            // the one it ships with", like every other blanked field here.
            'smtp_port'        => fn($v) => trim((string)$v) === ''
                ? '587' : (string)max(1, min(65535, (int)$v)),
            'smtp_security'    => fn($v) => in_array($v, ['tls', 'ssl', 'none'], true) ? $v : 'tls',
            'smtp_user'        => fn($v) => trim($v),
            'smtp_from_email'  => fn($v) => filter_var(trim($v), FILTER_VALIDATE_EMAIL) ? trim($v) : '',
            'smtp_from_name'   => fn($v) => trim($v),
            'accent_color'     => fn($v) => preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : \SignalMasterAi\View::BRAND_DEFAULTS['accent_color'],
            'up_color'         => fn($v) => preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : \SignalMasterAi\View::BRAND_DEFAULTS['up_color'],
            'down_color'       => fn($v) => preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : \SignalMasterAi\View::BRAND_DEFAULTS['down_color'],
            'footer_text'      => fn($v) => trim($v),
        ];
        // Feature gates, registered from the one table that defines them.
        //
        // Written out by hand here, this would be a second place to forget a
        // feature - and a gate missing from this map is a select that renders,
        // accepts a change, and silently discards it on save. Derived, a new
        // entry in Gate::FEATURES is savable the moment it exists.
        foreach (\SignalMasterAi\Gate::FEATURES as $gf) {
            $map[$gf['setting']] = static fn($v) =>
                in_array($v, ['any', 'free', 'paid'], true) ? $v : 'paid';
        }
        // Which coins the background scan rotates through. The hidden marker
        // tells a submitted form apart from one that never rendered the
        // picker: without it, unticking is indistinguishable from absent, and
        // saving any other page of settings would empty the scan list.
        if (isset($_POST['scan_list'])) {
            $wanted = array_map('strval', (array)($_POST['scan_symbols'] ?? []));
            $pdo->exec('UPDATE symbols SET scan = 0');
            if ($wanted) {
                $in = implode(',', array_fill(0, count($wanted), '?'));
                $pdo->prepare("UPDATE symbols SET scan = 1 WHERE symbol IN ($in)")->execute($wanted);
            }
        }
        // The timeframe list - one list, and everything reads it.
        //
        // fullscan_tfs and auto_backtest_tfs are written to match, and no
        // longer have controls of their own. Nothing reads them any more: cron
        // asks Visibility::siteTfs() for both the scan and the weekly
        // re-calibration. They are kept in step anyway because an operator who
        // looks in the settings table, or a future reader who greps for the
        // old key, should find a value that agrees with the site rather than a
        // fossil from whenever the two were last set separately.
        if (isset($_POST['intervals']) && is_array($_POST['intervals'])) {
            $chosen = array_values(array_intersect($config['market']['intervals'], $_POST['intervals']));
            if ($chosen) {
                $csv = implode(',', $chosen);
                Database::setSetting('enabled_intervals', $csv);
                Database::setSetting('fullscan_tfs', $csv);
                Database::setSetting('auto_backtest_tfs', $csv);
            }
        }
        // Snapshot before the loop so only real changes are recorded - a save
        // that rewrites 142 fields and alters two should leave two lines.
        $auditBefore = [];
        foreach (array_keys($map) as $key) {
            $auditBefore[$key] = Database::setting($key, '');
        }
        $auditAfter = [];
        foreach ($map as $key => $clean) {
            if (array_key_exists($key, $_POST)) {
                // Checkbox groups arrive as arrays; casting one to string
                // yields the literal word "Array" and quietly destroys the
                // setting. The cleaner decides what it can accept.
                $raw = $_POST[$key];
                $auditAfter[$key] = $clean(is_array($raw) ? $raw : (string)$raw);
                // A credential field is write-only: it renders empty on every
                // load, so every save posts a blank for it. Writing that blank
                // through is how the AI key used to be destroyed by saving an
                // unrelated setting on another tab - the label said "leave
                // blank to keep" and the code did the opposite. Blank means
                // keep, for every credential, from the same list that decides
                // which fields are write-only in the first place. Clearing one
                // on purpose is the Revert button next to it.
                if ($auditAfter[$key] === '' && \SignalMasterAi\Secrets::isSecret($key)) {
                    unset($auditAfter[$key]);
                    continue;
                }
                Database::setSetting($key, $auditAfter[$key]);
            }
        }
        \SignalMasterAi\Audit::settings($auditBefore, $auditAfter);
        // SMTP password: only overwrite when a new one is entered.
        if (trim((string)($_POST['smtp_pass'] ?? '')) !== '') {
            Database::setSetting('smtp_pass', trim((string)$_POST['smtp_pass']));
        }
        // Telegram bot token: keep-if-blank secret; a new token resets the
        // cached bot username and the update offset.
        if (trim((string)($_POST['tg_bot_token'] ?? '')) !== '') {
            Database::setSetting('tg_bot_token', trim((string)$_POST['tg_bot_token']));
            Database::setSetting('tg_bot_username', '');
            Database::setSetting('tg_updates_offset', '0');
        }
        // THE PROVIDER LIST, AS ROWS RATHER THAN AS A BLOCK OF TEXT.
        //
        // It was a textarea. That could add and reorder, but it could not
        // switch one off without deleting the line, it could not say which
        // provider a URL resolved to, and a typo silently dropped an endpoint
        // on save with nothing said. Each row now carries its own enable box
        // and its own URL field, and the stored format is unchanged - one per
        // line, priority order - with a leading # for the ones that are off.
        // MarketData::endpoints skips those, so "off" is off everywhere
        // without a second setting to keep in step.
        if (isset($_POST['ep_url'])) {
            $lines = [];
            $live = [];
            $bad = 0;
            foreach ((array)$_POST['ep_url'] as $i => $raw) {
                $url = rtrim(trim((string)$raw), '/');
                if ($url === '') {
                    continue;             // an empty row is the "add another" one, unused
                }
                if (!preg_match('#^https?://[^\s]+$#', $url)) {
                    $bad++;               // counted and reported, not dropped in silence
                    continue;
                }
                if (count($lines) >= 10) {
                    break;
                }
                $on = isset($_POST['ep_on'][$i]);
                $lines[] = ($on ? '' : '#') . $url;
                if ($on) {
                    $live[] = $url;
                }
            }
            // At least one has to be live, or the site has no market data and
            // every page that draws a chart breaks at once. Refusing the save
            // is kinder than accepting it.
            if (!$live) {
                flash('At least one endpoint has to stay switched on - the site cannot fetch prices '
                    . 'without one. Nothing was changed.', 'warn');
                header('Location: settings.php#market');
                exit;
            }
            Database::setSetting('api_endpoints', implode("\n", $lines));
            Database::setSetting('api_base_url', $live[0]);   // primary, kept in sync
            // Carried to the end rather than flashed here: flash() holds one
            // message, and the unconditional "Settings saved." below would
            // overwrite this one - so a rejected URL was reported by a warning
            // nobody ever saw, which is the silent drop this replaced.
            $epBad = $bad;
        }
        flash(($epBad ?? 0) > 0
            ? 'Settings saved, but ' . (int)$epBad . ' endpoint(s) were not - a URL has to start '
              . 'with http:// or https:// and contain no spaces.'
            : 'Settings saved.',
            ($epBad ?? 0) > 0 ? 'warn' : 'ok');
        // The tab the form was submitted from, same as the #market rejection
        // above sends back to Market - a save that lands back on Overview no
        // matter which of the seven tabs it was made from is a save that
        // loses your place. Whitelisted rather than echoed straight back:
        // this becomes part of a URL, and $_POST['tab'] is client-supplied.
        $saveTab = in_array($_POST['tab'] ?? '',
            ['overview', 'site', 'signals', 'market', 'alerts', 'members', 'security'], true)
            ? '#' . $_POST['tab'] : '';
        header('Location: settings.php' . $saveTab);
        exit;
    }

    if ($act === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new1'] ?? '');
        $new2 = (string)($_POST['new2'] ?? '');
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $user = $stmt->fetch();
        $stmt->closeCursor();
        if (!$user || !password_verify($cur, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $new2) {
            $error = 'New passwords do not match.';
        } else {
            // pw_changed ends every other admin session. The generated
            // first-login password was written to a file, so this is the
            // request that closes that door - and it does not close while a
            // session opened with the old password still works.
            // One clock read, used for both: two calls to time() can land a
            // second apart and sign the operator out of their own session.
            $gen = time();
            $pdo->prepare('UPDATE users SET password_hash = ?, pw_changed = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $gen, $user['id']]);
            // Keep the operator who just typed it signed in; everyone else out.
            $_SESSION['admin_pw'] = $gen;
            // The generated first-login password is no longer the password, so
            // the file holding it is no longer a convenience - it is a copy of
            // a credential sitting on disk.
            \SignalMasterAi\DataGuard::clearFirstLogin();
            Database::setSetting('admin_first_login', '0');
            flash('Password changed.');
            // This form only ever lives on Security - hardcoded like the
            // #market rejection below, not a tab the admin could be on
            // elsewhere.
            header('Location: settings.php#security');
            exit;
        }
    }

    if ($act === 'test_apis') {
        // THE PATH DEPENDS ON THE PROVIDER.
        //
        // This asked every endpoint for /api/v3/ping, which is Binance's route
        // and nobody else's. OKX, Bybit and KuCoin answered 404 and were
        // reported FAILED while working, so an operator who added one as a
        // backup was told to remove it. MarketData::pingPath knows the right
        // route for each, from the same table the adapters are chosen by.
        $apiReport = [];
        foreach (\SignalMasterAi\MarketData::endpoints($config) as $host) {
            $t0 = microtime(true);
            $ch = curl_init($host . \SignalMasterAi\MarketData::pingPath($host));
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5]);
            curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            $ms = (int)round((microtime(true) - $t0) * 1000);
            $label = \SignalMasterAi\MarketData::providerLabel($host);
            $apiReport[$host] = ($code === 200 ? "OK ({$ms} ms)" : 'FAILED (' . ($err !== '' ? $err : "HTTP $code") . ')')
                . ' - ' . $label;
        }
    }

    if ($act === 'test_email') {
        $to = trim((string)($_POST['test_to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid address to send the test email to.';
        } elseif (!\SignalMasterAi\Mailer::enabled()) {
            $error = 'Email sending is disabled - pick SMTP or PHP mail() above and save first.';
        } else {
            $ok = \SignalMasterAi\Mailer::send($to, 'Test email - ' . Database::setting('site_name', 'SignalMasterAi'),
                "This is a test email from your SignalMasterAi installation.\n\nIf you can read this, signal alert emails will work.",
                \SignalMasterAi\Mailer::template('Email is working! 🎉',
                    '<p style="margin:0">This is a test email from your installation. If you can read this styled version, members will receive the rich signal-alert emails with colored BUY/SELL badges and trade plans.</p>',
                    'Open your site', Database::setting('site_url')),
                ['kind' => \SignalMasterAi\EmailLog::TEST, 'context' => 'admin test send']);
            if ($ok) {
                flash("Test email sent to $to - check the inbox (and spam folder).");
                header('Location: settings.php');
                exit;
            }
            // The reason is now recorded rather than left in a log nobody on
            // shared hosting can open.
            $why = \SignalMasterAi\EmailLog::recent(1, \SignalMasterAi\EmailLog::TEST, true);
            $error = 'Sending failed'
                . (!empty($why[0]['reason']) ? ': ' . $why[0]['reason'] : ' - check host, port and credentials.')
                . ' The full record is under Alerts > Email log.';
        }
    }

    if ($act === 'logo_upload') {
        // Custom logo: raster images only (SVG is refused - an uploaded SVG
        // opened directly could carry scripts). Used site-wide incl. favicon.
        $f = $_FILES['logo'] ?? [];
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Upload failed - choose an image file (PNG/JPG/WEBP/GIF).';
        } elseif ($f['size'] > 2 * 1024 * 1024) {
            $error = 'Logo too large (max 2 MB).';
        } else {
            $info = @getimagesize($f['tmp_name']);
            $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
            if ($info === false || !isset($allowed[$info[2]])) {
                $error = 'File must be a PNG, JPG, WEBP or GIF image.';
            } else {
                if (!is_dir(SMA_UPLOAD_DIR)) {
                    mkdir(SMA_UPLOAD_DIR, 0775, true);
                }
                $name = 'site-logo-' . bin2hex(random_bytes(6)) . '.' . $allowed[$info[2]];
                if (!move_uploaded_file($f['tmp_name'], SMA_UPLOAD_DIR . '/' . $name)) {
                    $error = 'Could not store the logo - check that data/uploads is writable.';
                } else {
                    $old = Database::setting('custom_logo');
                    if ($old !== '' && is_file(SMA_UPLOAD_DIR . '/' . $old)) {
                        @unlink(SMA_UPLOAD_DIR . '/' . $old);
                    }
                    Database::setSetting('custom_logo', $name);
                    flash('Logo updated - it now shows in the header, admin panel and browser tab.');
                    header('Location: settings.php#site');
                    exit;
                }
            }
        }
    }

    if ($act === 'logo_remove') {
        $old = Database::setting('custom_logo');
        if ($old !== '' && is_file(SMA_UPLOAD_DIR . '/' . $old)) {
            @unlink(SMA_UPLOAD_DIR . '/' . $old);
        }
        Database::setSetting('custom_logo', '');
        flash('Custom logo removed - the default logo is back.');
        header('Location: settings.php#site');
        exit;
    }

    // MOVE THE PRIVATE FILES OUT OF THE WEB ROOT.
    //
    // The one fix for "your database is downloadable" that needs no server
    // configuration, for the operators who cannot change theirs. All of the
    // care lives in DataDir::move() - it renames rather than copies where it
    // can, verifies before deleting anything where it cannot, and undoes
    // itself rather than leaving an install that will not start.
    if ($act === 'move_data') {
        $target = (string)($_POST['data_target'] ?? '');
        [$ok, $msg] = \SignalMasterAi\DataDir::move($target);
        if ($ok) {
            // The check that reported the problem answers again, now, rather
            // than from a cache written before the files moved. An operator
            // who has just fixed this is entitled to see it go green.
            \SignalMasterAi\Maintenance::dataExposed(true);
            flash($msg);
        } else {
            flash('Not moved. ' . $msg, 'error');
        }
        header('Location: settings.php#datadir');
        exit;
    }

    if ($act === 'clear_cache') {
        $pdo->exec('DELETE FROM candles');
        $pdo->exec('DELETE FROM fetch_log');
        // Micro-caches stored in settings: live tickers, funding rates, F&G.
        // Backslash cannot be the LIKE escape character on MySQL/MariaDB.
        $pdo->exec("DELETE FROM settings WHERE skey LIKE 'tick!_%' ESCAPE '!'
                    OR skey LIKE 'fund!_%' ESCAPE '!' OR skey = 'fng_cache'");
        flash('Market data cache cleared - it will be refetched on the next chart view.');
        header('Location: settings.php');
        exit;
    }
}

$csrf = Auth::csrfToken();
$s = fn(string $k, string $d = '') => sma_e(Database::setting($k, $d));

admin_header('Settings', 'settings',
    'Every setting in the application, filed by subject. Pick a section, or search across all of '
    . 'them at once. <strong>Overview</strong> holds the master switches that turn a whole feature '
    . 'on or off everywhere, and the list of every value you have moved off its default.');
show_flash();
if ($error) {
    echo '<div class="error">' . sma_e($error) . '</div>';
}
?>
<?php // 142 fields across seven sections, and until now the only way to find
      // one was to remember which section it lived in. Searching looks across
      // all of them at once, opens the cards that match, and says how many it
      // found. ?>
<div class="settings-find">
  <input type="search" id="settingsFind" placeholder="Find a setting&hellip; (e.g. &quot;stop&quot;, &quot;cron&quot;, &quot;fee&quot;)"
         autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Find a setting">
  <span class="hint" id="settingsFindCount"></span>
</div>
<?php // Seven subjects, each named after what is actually inside it.
      //
      // There were eight, and the names had drifted from the contents: the
      // referral programme and the AI key were filed under "Email" because
      // they happened to be added at the same time, the full-market scan was
      // under "Signals" while the endpoints it fetches from were under
      // "Market data", and "Account" held the admin password next to the cache
      // tools. Every one of those is findable by search and none is findable
      // by looking, which is the failure mode a tab bar exists to prevent.
      //
      // The names match the sidebar groups, so there is one arrangement of
      // this admin panel to learn rather than two.
      //
      // WHERE THIS SITS MATTERS. It used to sit below the master switches and
      // the changed-from-default table, which put the page's own map 2,078px
      // down the screen on a desktop and 2,385px down on a phone: you arrived
      // at Settings and the first two and a half screens contained no setting
      // and no sign that the page had sections at all. It is now the first
      // thing under the search box and it sticks below the header, so the
      // section you are in - and every section you are not - stays on screen
      // through a four-thousand-pixel tab.
      //
      // It was a grid of tiles with a description under each. Seven
      // descriptions arguing for attention at once is not a map, it is a wall,
      // and the descriptions were hidden on phones anyway. One line describing
      // the section you actually chose says the same thing where it is useful.
      //
      // "Overview" is new, and it is what makes the rest of this work: the
      // master switches and the changed-from-default table are not settings,
      // they are the two things you look at BEFORE settings - so they are a
      // section of their own instead of a preamble stacked on top of all six
      // others. ?>
<nav class="settings-tabs" id="settingsTabs" aria-label="Settings sections">
  <a href="#overview" data-lede="The switches that turn whole features on or off, and every value you have moved away from its default."><i></i><b>Overview</b><span class="tab-n"></span></a>
  <a href="#site" data-lede="Name, addresses, SEO, the landing page words, colours and logo, legal pages, and what the public track record shows."><i></i><b>Site</b><span class="tab-n"></span></a>
  <a href="#signals" data-lede="What score becomes a call, the entry, stop and three targets it publishes, and the optional AI write-up."><i></i><b>Signals</b><span class="tab-n"></span></a>
  <a href="#market" data-lede="Where candles come from, how long they are kept, the background scan and the cron schedule that drives it."><i></i><b>Market</b><span class="tab-n"></span></a>
  <a href="#alerts" data-lede="Who gets told about a flip and how often - email and SMTP, Telegram, digests, public broadcast channels."><i></i><b>Alerts</b><span class="tab-n"></span></a>
  <a href="#members" data-lede="Sign-up, email verification, referrals, and what the site keeps about the people who register."><i></i><b>Members</b><span class="tab-n"></span></a>
  <a href="#security" data-lede="Admin login and lockouts, the admin password, and the cache, backup and maintenance tools."><i></i><b>Security</b><span class="tab-n"></span></a>
</nav>
<p class="tab-lede" id="settingsLede"></p>
<?php // Master switches.
      //
      // Above every other setting, because they are above every other setting:
      // a master that is off is off in the background scan, in cron, on the
      // chart, on the scanner, in the API and in this panel. Nothing under it
      // can hold a surface open. The customs inside are for the cases where
      // the feature should run but not everywhere - charted but not scanned,
      // scanned but not alerted. ?>
<details class="form-card coin-hub" id="masters" data-tab="overview" open>
  <summary>Master switches &mdash; what this site does at all</summary>
  <p class="hint">The switch above everything else. Off means off in the background scan, in cron, on
    the chart, on the scanner, in the API <em>and in this admin panel</em> &mdash; the pages for a
    feature that is off disappear until you turn it back on here. Each master has the surfaces it
    covers inside it, for when a feature should run but not everywhere.</p>
  <?php foreach (\SignalMasterAi\Master::FEATURES as $mKey => [$mLabel, $mBlurb, $mSurfaces]):
        $mOn = \SignalMasterAi\Master::master($mKey);
        [$mNum, $mTot] = \SignalMasterAi\Master::activeCount($mKey); ?>
    <details class="coin-grp" id="master-<?= sma_e($mKey) ?>" <?= $mOn ? '' : 'open' ?>>
      <summary><?= sma_e($mLabel) ?>
        <span class="grp-state"><?= $mOn ? 'on &middot; ' . $mNum . ' of ' . $mTot . ' surfaces' : 'OFF everywhere' ?></span></summary>
      <p class="hint"><?= sma_e($mBlurb) ?></p>
      <form method="post" action="settings.php">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="master">
        <input type="hidden" name="feature" value="<?= sma_e($mKey) ?>">
        <label class="chk" style="font-weight:600">
          <input type="checkbox" name="master_<?= sma_e($mKey) ?>" value="1" <?= $mOn ? 'checked' : '' ?>
                 style="width:auto"> <?= sma_e($mLabel) ?> is on
        </label>
        <div style="margin:10px 0 0 22px<?= $mOn ? '' : ';opacity:.45' ?>">
          <p class="hint" style="margin-top:0">Where it runs<?= $mOn ? '' : ' (the master above is off, so none of these apply)' ?>:</p>
          <?php foreach ($mSurfaces as $sKey => [$sLabel, $sWhat, $sLegacy]): ?>
            <label class="chk" style="align-items:flex-start">
              <input type="checkbox" name="surface_<?= sma_e($mKey) ?>[]" value="<?= sma_e($sKey) ?>"
                     <?= Database::setting('master_' . $mKey . '_' . $sKey, '1') === '1' ? 'checked' : '' ?>
                     style="width:auto;margin-top:3px">
              <span><strong><?= sma_e($sLabel) ?></strong>
                <span class="hint" style="display:block;margin-top:1px"><?= sma_e($sWhat) ?><?php
                  if ($sLegacy !== '' && Database::setting($sLegacy, '1') !== '1'): ?>
                  &mdash; <em>also held off by the older <code><?= sma_e($sLegacy) ?></code> setting</em><?php
                  endif; ?></span></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p style="margin-top:12px"><button class="btn" type="submit">Save <?= sma_e(strtolower($mLabel)) ?></button></p>
      </form>
    </details>
  <?php endforeach; ?>
</details>

<?php
// What have I changed?
//
// Two hundred and forty settings, and the only view of them was all of them.
// An operator cannot hold that many in their head, and does not need to: the
// ones that matter on any given day are the handful they have moved away
// from the shipped value. Those are listed here, with what it was, what it
// is, and a way back - and everything else stays exactly where it was, one
// tab away, for the times you do want to go looking.
$smaDefaults = Database::defaultSettings($config);
// Settings a PERSON chose, which is not the same set as rows in the settings
// table. The engine keeps its own state in there too - where the scan rotation
// has got to, the last auto-tune report, the calibration it fitted last night
// - and every one of those differs from its shipped value the moment the site
// does any work at all. Listing them under "changed" would put twenty machine
// entries in front of the six the operator actually touched, which is the
// same failure as showing all two hundred and forty.
$smaMachine = static function (string $k): bool {
    foreach (['_report', '_last', '_pos', '_calib', '_seen', '_rate_', '_stopped_'] as $suffix) {
        if (str_contains($k, $suffix)) {
            return true;
        }
    }
    return in_array($k, ['time_buckets', 'quarantined_rules', 'tf_rule_mult', 'last_dispatch',
                         'symbols_seeded', 'rule_weights_baseline', 'admin_first_login',
                         'admin_first_login_file', 'cron_last_run'], true)
        || str_starts_with($k, 'mig_');
};
$smaChanged = [];
foreach ($smaDefaults as $k => $v) {
    if ($smaMachine($k)) {
        continue;
    }
    // Secrets are compared but never printed: the point is to say THAT a key
    // is set, never what it is.
    $cur = Database::setting($k, (string)$v);
    // Numbers compared as numbers. "2.0" and "2" are the same threshold, and
    // a list that calls them a change is a list nobody will trust twice - the
    // whole value of this panel is that it is short and every line is real.
    // Hex colors compared case-insensitively for the same reason: the form's
    // <input type=color> always posts lowercase, so an untouched color saved
    // through the big form drifts from the uppercase shipped default and
    // reads as "changed" forever after - same fix View::head() already uses
    // for this exact comparison, when deciding whether to emit an override.
    $same = is_numeric($cur) && is_numeric($v)
        ? abs((float)$cur - (float)$v) < 1e-9
        : (in_array($k, ['accent_color', 'up_color', 'down_color'], true)
            ? strcasecmp((string)$cur, (string)$v) === 0
            : (string)$cur === (string)$v);
    if (!$same) {
        // ONE definition of "this is a credential", shared with the audit log
        // and the storage layer. The regular expression that used to live here
        // was a third opinion, and a third opinion is a key on a page as soon
        // as somebody names a setting slightly differently.
        $secret = \SignalMasterAi\Secrets::isSecret($k);
        $smaChanged[$k] = [(string)$v, (string)$cur, $secret];
    }
}
// A value as it should appear in the table. Two things this got wrong: the
// ellipsis was inside the escaper, so a long value ended in a literal
// "&hellip;" - "hashed pass&hellip;" is what the page actually printed - and
// an emptied field showed as blank, which reads as "no value here" when the
// point of the row is that you emptied it on purpose.
$smaShow = static function (string $s): string {
    if ($s === '') {
        return '<em>empty</em>';
    }
    return sma_e(mb_substr($s, 0, 60)) . (mb_strlen($s) > 60 ? '&hellip;' : '');
};
?>
<details class="form-card" id="changed" data-tab="overview" <?= $smaChanged ? 'open' : '' ?>>
  <summary>Changed from default <span class="chg-count"><?= count($smaChanged) ?></span></summary>
  <?php if (!$smaChanged): ?>
    <p class="hint">Nothing has been changed &mdash; every setting is on the value it ships with.</p>
  <?php else: ?>
    <p class="hint">The settings you have moved, and what they were. Everything else is on its
      default and is one section away.</p>
    <div class="tbl-scroll">
    <table class="grid chg-table">
      <tr><th>Setting</th><th>Ships with</th><th>Now</th><th></th></tr>
      <?php foreach ($smaChanged as $k => [$def, $cur, $secret]): ?>
      <tr>
        <td><code><?= sma_e($k) ?></code></td>
        <td class="chg-was"><?= $secret ? '<em>not set</em>' : $smaShow($def) ?></td>
        <td><strong><?= $secret ? '<em>set</em>' : $smaShow($cur) ?></strong></td>
        <td style="white-space:nowrap">
          <form method="post" action="settings.php" class="inline-form"
                onsubmit="return confirm('Put <?= sma_e($k) ?> back to its default?')">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="act" value="revert">
            <input type="hidden" name="key" value="<?= sma_e($k) ?>">
            <button class="btn small gray" type="submit">Revert</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
  <?php endif; ?>
</details>

<?php // These are their own forms, so they live ABOVE the big settings form
      // rather than inside it: HTML has no nested forms, and a browser handed
      // one quietly throws the inner tags away, leaving buttons that submit
      // the wrong thing or nothing at all. ?>
  <?php if (\SignalMasterAi\Master::master('signals')): ?><details class="form-card" data-tab="signals" open>
    <summary>Starting points</summary>
    <p class="hint">A preset writes one coherent set of engine values in a single step, so tuning
      starts from something explainable instead of from 142 fields and a guess. It changes only the
      engine settings listed under each option &mdash; nothing about your site, coins, members or
      email &mdash; and every field it touches is recorded in the
      <a href="audit.php" style="color:var(--accent)">change log</a>, so undoing it is reading a
      list rather than remembering one.</p>
    <?php
    // Named for what they do, not for how they feel. "Active" is offered
    // because some operators want volume, and it says so instead of implying
    // that more signals is more edge.
    // THE CARD USED TO CLAIM A MEASUREMENT IT COULD NOT HAVE MADE.
    //
    // Selective's description said it "is what this install's own replayed
    // history measured as its best configuration". That sentence shipped
    // identically to every install, including ones with no settled signals at
    // all. It was measured once, here, on one dataset. Presented as a finding
    // about the reader's own account, it is an operator switching presets on
    // the strength of a promise the software was in no position to make.
    //
    // The descriptions now say what each preset DOES. The measuring is done
    // underneath, against this install's own record, and says so when there is
    // not enough of it.
    $presetBlurb = \SignalMasterAi\Presets::BLURB;
    $presetSeen = \SignalMasterAi\Presets::report();
    $presetByTf = \SignalMasterAi\Presets::byInterval();
    $liveBuy = Database::setting('buy_threshold', '2.0');
    ?>
    <p class="hint">The line under each card is measured from this install's own settled signals,
      and it covers <strong>the timeframes that preset scans</strong> &mdash; the part of a preset
      that can be checked against history already on disk. It does not replay the thresholds and
      gates: answering "would this setup have been published at all" needs the candles back, which
      is what <a href="engine.php" style="color:var(--accent)">Engine lab &rsaquo; Compare a change
      before you make it</a> does. Two presets that scan the same timeframes will show the same
      line here and can still behave very differently.</p>
    <div class="preset-grid">
      <?php foreach ($presetBlurb as $key => [$title, $why]): ?>
        <?php
        $diff = 0;
        foreach ($presets[$key] as $k => $v) {
            if (Database::setting($k, '') !== $v) {
                $diff++;
            }
        }
        ?>
        <form method="post" action="settings.php" class="preset-card"
              onsubmit="return confirm('Apply the <?= $key ?> preset? <?= $diff ?> setting(s) will change. Every one is listed in the change log afterwards.')">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="act" value="preset">
          <input type="hidden" name="preset" value="<?= $key ?>">
          <h4><?= $title ?></h4>
          <p><?= $why ?></p>
          <?php
          // What this install's own settled signals did on the timeframes this
          // preset scans. Not a replay of its thresholds - that needs the
          // candles, and the engine lab's A/B does it - but the timeframe
          // choice is the part of a preset most likely to move the headline,
          // because it decides which trades exist at all.
          $seen = $presetSeen[$key] ?? null;
          ?>
          <?php if ($seen && $seen['n'] > 0): ?>
            <p class="preset-seen<?= $seen['enough'] ? '' : ' thin' ?>">
              On this install, <strong><?= sma_e(implode(', ', $seen['intervals'])) ?></strong>
              settled <strong><?= (int)$seen['n'] ?></strong> signals:
              <strong><?= sma_e((string)$seen['win_rate']) ?>% closed in profit</strong>,
              <?= $seen['avg_r'] >= 0 ? '+' : '' ?><?= sma_e((string)$seen['avg_r']) ?>R average.
              <?php if (!$seen['enough']): ?>
                <br><em>Under <?= \SignalMasterAi\Presets::MIN_N ?> trades &mdash; too few to
                choose on.</em>
              <?php endif; ?>
              <?php if ($seen['dropped']): ?>
                <br>Leaves out
                <?php $bits = [];
                      foreach ($seen['dropped'] as $tf) {
                          $d = $presetByTf[$tf] ?? null;
                          $bits[] = '<strong>' . sma_e($tf) . '</strong>'
                              . ($d ? ' (' . sma_e((string)$d['win_rate']) . '%, '
                                    . ($d['avg_r'] >= 0 ? '+' : '') . sma_e((string)$d['avg_r'])
                                    . 'R on ' . (int)$d['n'] . ')'
                                  : ' (nothing settled yet)');
                      }
                      echo implode(' and ', $bits); ?>.
                Scanning fewer timeframes is being selective about speed, not about quality &mdash;
                check the number beside each before deciding it is a loss worth taking.
              <?php endif; ?>
            </p>
          <?php else: ?>
            <p class="preset-seen thin">Nothing settled on
              <?= sma_e(implode(', ', \SignalMasterAi\Presets::intervals($key))) ?> here yet, so
              this install has no opinion on it.</p>
          <?php endif; ?>
          <button class="btn small <?= $diff === 0 ? 'gray' : '' ?>" type="submit"
                  <?= $diff === 0 ? 'disabled' : '' ?>>
            <?= $diff === 0 ? '&#10003; Already applied' : 'Apply (' . $diff . ' change' . ($diff === 1 ? '' : 's') . ')' ?>
          </button>
        </form>
      <?php endforeach; ?>
    </div>
  </details><?php endif; ?>

<?php // Named so the script below can tell "Save all settings" apart from the
      // cards that carry their own form. On a section made only of those -
      // Overview, where nothing is part of this form - a sticky bar offering
      // to save is a button with nothing to save. ?>
<form method="post" action="settings.php" id="allSettings">
  <input type="hidden" name="csrf" value="<?= $csrf ?>">
  <input type="hidden" name="act" value="settings">
  <?php // Which tab the save should return to. The script below fills this in
        // from the same hash the tab strip itself reads on submit - left
        // empty, a save lands back on Overview no matter which tab it was
        // made from. ?>
  <input type="hidden" name="tab" id="allSettingsTab" value="">

  <details class="form-card" data-tab="site" open>
    <summary>Site</summary>
    <div class="row2">
      <div><label>Site name</label><input type="text" name="site_name" value="<?= $s('site_name', 'SignalMasterAi') ?>"></div>
      <div><label>Tagline</label><input type="text" name="site_tagline" value="<?= $s('site_tagline') ?>"></div>
    </div>
    <label>Site URL (auto-detected on first visit; leave empty to re-detect - used in alert emails &amp; push)</label>
    <input type="text" name="site_url" value="<?= $s('site_url') ?>" placeholder="https://signalmasterai.com">
    <label>Legal notice (shown in the footer and with every signal)</label>
    <textarea name="site_notice"><?= $s('site_notice') ?></textarea>
    <p class="hint">Keep the "not financial advice" wording - signals are educational chart commentary, not investment recommendations.</p>
  </details>

  <?php
  // The same probe cron runs, read from its cache. Opening this page forces a
  // fresh one, because someone who came here to look at this setting is
  // usually here to change it or to find out why it is off.
  $rwState = \SignalMasterAi\Maintenance::rewriteWorks(true);
  ?>
  <details class="form-card" data-tab="site">
    <summary>Addresses: clean URLs and www</summary>
    <div class="row2">
      <div><label>Page addresses</label>
        <select name="clean_urls">
          <option value="0" <?= Database::setting('clean_urls', '0') === '0' ? 'selected' : '' ?>>/charts.php &mdash; always works</option>
          <option value="1" <?= Database::setting('clean_urls', '0') === '1' ? 'selected' : '' ?>>/charts &mdash; needs mod_rewrite</option>
        </select>
        <p class="hint">
          <?php if ($rwState === true): ?>
            <strong style="color:var(--up)">Tested just now: your server is rewriting.</strong>
            Clean addresses are working.
          <?php elseif ($rwState === false): ?>
            <strong style="color:var(--down)">Tested just now: your server is NOT rewriting</strong>
            &mdash; so clean addresses are being <strong>ignored</strong> and the site is writing
            <code>.php</code> links, which is the safe half of this setting doing its job rather
            than a site full of 404s. To get them working: the shipped <code>.htaccess</code> needs
            <code>AllowOverride All</code> on Apache; on nginx or LiteSpeed the rule belongs in the
            server config instead.
          <?php else: ?>
            <strong style="color:var(--warn)">Could not test.</strong> The check calls this site's
            own <code>/status</code> address, so it needs a working Site URL and outbound HTTP.
            Until it can, clean addresses stay on &mdash; only a definite failure turns them off.
          <?php endif; ?>
          Either way the <code>.php</code> addresses keep serving, so old links and bookmarks never
          break. This only decides which spelling the site writes into its own links.</p></div>
      <div><label>www and non-www</label>
        <select name="canonical_host">
          <option value="both" <?= Database::setting('canonical_host', 'both') === 'both' ? 'selected' : '' ?>>Serve both (no redirect)</option>
          <option value="www" <?= Database::setting('canonical_host', 'both') === 'www' ? 'selected' : '' ?>>Always redirect to www</option>
          <option value="nonwww" <?= Database::setting('canonical_host', 'both') === 'nonwww' ? 'selected' : '' ?>>Always redirect to non-www</option>
        </select>
        <p class="hint">Both spellings serve the site as shipped. Pick one only once you know your
          certificate covers it &mdash; a redirect to a host with no certificate turns a working
          site into a browser warning. The API, cron and payment webhooks are never redirected
          whatever you choose, because a gateway POSTing to a 301 loses its request body.</p></div>
    </div>
  </details>

  <details class="form-card" data-tab="site">
    <summary>SEO</summary>
    <label>Browser-tab / search-result title (empty = "site name — tagline")</label>
    <input type="text" name="seo_title" value="<?= $s('seo_title') ?>" placeholder="Crypto trading signals with live chart analysis | <?= $s('site_name', 'SignalMasterAi') ?>">
    <label>Meta description shown under your result on Google (empty = tagline; ~150-160 characters is ideal)</label>
    <textarea name="meta_description" rows="2"><?= $s('meta_description') ?></textarea>
    <label>Meta keywords, comma-separated (optional; most engines ignore them)</label>
    <input type="text" name="meta_keywords" value="<?= $s('meta_keywords') ?>" placeholder="crypto signals, bitcoin buy sell, trading alerts">
    <p class="hint">Applied to the landing page and the live charts page, including the social-share
      (Open Graph / Twitter) tags.</p>
  </details>

  <details class="form-card" data-tab="market" open>
    <summary>Market data APIs</summary>
    <?php if (isset($apiReport)): ?>
      <div class="flash" style="background:var(--surface2);color:var(--text);border-color:var(--border)">
        <?php foreach ($apiReport as $host => $res): ?>
          <div style="display:flex;justify-content:space-between;gap:12px;padding:2px 0">
            <span><?= sma_e($host) ?></span>
            <strong style="color:<?= str_starts_with($res, 'OK') ? 'var(--up)' : 'var(--down)' ?>"><?= sma_e($res) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="row2">
      <div><label>Endpoint mode</label>
        <select name="api_mode">
          <option value="auto" <?= Database::setting('api_mode', 'auto') === 'auto' ? 'selected' : '' ?>>Auto-switch (failover through the list, built-in mirrors as backup)</option>
          <option value="single" <?= Database::setting('api_mode') === 'single' ? 'selected' : '' ?>>Selected only (use just the first endpoint, never switch)</option>
        </select></div>
    </div>
    <label>Price feeds, in the order they are tried</label>
    <?php
    // Rendered from the stored setting rather than from a fixed list: the
    // whole point is that an operator can add a provider this code has never
    // heard of, as long as it speaks one of the shapes below.
    $epLines = preg_split('/\R+/', Database::setting('api_endpoints',
        "https://data-api.binance.vision\nhttps://api.binance.com")) ?: [];
    $epRows = [];
    foreach ($epLines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $off = str_starts_with($line, '#');
        $url = rtrim(ltrim($line, '#'), '/');
        if ($url !== '') {
            $epRows[] = ['url' => $url, 'on' => !$off];
        }
    }
    if (!$epRows) {
        $epRows[] = ['url' => 'https://data-api.binance.vision', 'on' => true];
    }
    // One spare row, so adding a feed does not need a button that adds a row.
    $epRows[] = ['url' => '', 'on' => true];
    ?>
    <div class="tbl-scroll">
    <table class="grid feed-list">
      <tr><th style="width:34px">On</th><th>Endpoint URL</th><th style="width:170px">Provider</th></tr>
      <?php foreach ($epRows as $i => $row): $blank = $row['url'] === ''; ?>
        <tr>
          <td><input type="checkbox" name="ep_on[<?= (int)$i ?>]" value="1"
                     <?= $row['on'] ? 'checked' : '' ?> style="width:auto"></td>
          <td><input type="url" name="ep_url[<?= (int)$i ?>]" value="<?= sma_e($row['url']) ?>"
                     placeholder="<?= $blank ? 'https://... (add another)' : '' ?>"
                     style="font-family:var(--font-mono);width:100%"></td>
          <td><?php if ($blank): ?><span class="hint">detected on save</span>
              <?php else: ?><strong><?= sma_e(\SignalMasterAi\MarketData::providerLabel($row['url'])) ?></strong>
              <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p class="hint">Unticking keeps the URL and stops using it &mdash; the old textarea could only
      delete the line. Clear a URL to remove it. The first ticked row is the primary. Order is
      priority; ten rows maximum, which is more failover than any install needs.</p>
    <p class="hint">The <strong>Provider</strong> column is detected from the host, and it decides
      which adapter reads the candles <em>and</em> which health route the test below calls:
      <?php $bits = [];
            foreach (\SignalMasterAi\MarketData::PROVIDERS as $k => [$lab, $path, $note]) {
                $bits[] = '<strong>' . sma_e($lab) . '</strong> <code>' . sma_e($path) . '</code>'
                        . ($note !== '' ? ' <span class="hint">' . sma_e($note) . '</span>' : '');
            }
            echo implode(' &middot; ', $bits); ?>.
      Anything unrecognised is treated as Binance-compatible, which is what a mirror or your own
      proxy will be. The bulk symbol import uses the Binance-compatible entries only.</p>
    <?php
    // ON-CHAIN, AND WHAT IT HONESTLY IS.
    //
    // Placed with the other market feeds because that is what it is - not with
    // the rules, because it deliberately is not one.
    $ocHealth = [];
    $ocRead = null;
    try {
        $ocHealth = \SignalMasterAi\OnChain::health();
        $ocRead = \SignalMasterAi\OnChain::reading();
    } catch (Throwable $e) {
    }
    ?>
    <h3 style="margin-top:22px">Bitcoin on-chain settlement</h3>
    <p class="hint"><strong>Read this before switching it on.</strong> &ldquo;Whale movement&rdquo;
      and &ldquo;exchange movement&rdquo; as sold by data providers mean coins moving from a known
      large holder into a known exchange deposit address (a seller getting ready) or back out of
      one (a buyer taking custody). <em>That direction is the whole value of the signal</em>, and it
      depends on knowing whose address is whose. Address labelling is the product Glassnode,
      CryptoQuant, Nansen and Arkham sell; there is no free keyless source for it, and guessing it
      from public data would publish a guess to your members as though it were an observation.</p>
    <p class="hint">So this <strong>never casts a directional vote</strong>. What free chain data
      genuinely supports is how much value is settling on-chain now against its own recent normal.
      A jump means size is moving. It does not say whose, or which way. Used as a caution &mdash;
      raising the bar rather than taking a side &mdash; which is the strongest claim the data
      supports. Bitcoin only; there is no free multi-chain equivalent, and the engine already
      treats BTC as the tide (see the <code>btc_regime</code> rule).</p>
    <p class="hint"><strong>It cannot be backtested.</strong> These feeds serve today's value, not a
      history the replay can walk &mdash; and the engine skips it entirely in a replay, because
      applying today's chain reading to a bar from three weeks ago is not a caution, it is the
      future leaking into the past. Judge it on the shadow record.</p>
    <div class="tbl-scroll">
    <table class="grid feed-list">
      <tr><th>Source</th><th style="width:90px">Status</th><th>Reading</th></tr>
      <?php foreach ($ocHealth as $h): ?>
        <tr>
          <td><?= sma_e((string)$h['name']) ?><br><span class="hint"><code><?= sma_e((string)$h['url']) ?></code></span></td>
          <td><?= !empty($h['ok']) ? '<span style="color:var(--good)">OK</span>'
                : '<span style="color:var(--bad)">no reply</span>' ?></td>
          <td><?= sma_e((string)$h['note']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($ocRead): ?>
        <tr><td colspan="3"><strong>Now:</strong>
          $<?= sma_e(number_format($ocRead['usd'] / 1e9, 2)) ?>bn settled,
          <strong><?= sma_e((string)$ocRead['ratio']) ?>x</strong> the 30-day median,
          mempool <?= sma_e(number_format((int)$ocRead['mempool'])) ?> waiting
          <?= !empty($ocRead['surge']) ? ' &mdash; <strong>above your surge threshold</strong>' : '' ?>
        </td></tr>
      <?php elseif (Database::setting('onchain_enabled', '0') !== '1'): ?>
        <tr><td colspan="3" class="hint">Switched off, so nothing is fetched.</td></tr>
      <?php endif; ?>
    </table>
    </div>
    <div class="row2">
      <div><label>Fetch the chain reading</label>
        <select name="onchain_enabled">
          <option value="0" <?= Database::setting('onchain_enabled', '0') === '0' ? 'selected' : '' ?>>Off (default)</option>
          <option value="1" <?= Database::setting('onchain_enabled', '0') === '1' ? 'selected' : '' ?>>On &mdash; fetch and show it</option>
        </select>
        <label style="margin-top:10px">Act on it as a caution</label>
        <select name="onchain_caution">
          <option value="0" <?= Database::setting('onchain_caution', '0') === '0' ? 'selected' : '' ?>>No &mdash; show it only (default)</option>
          <option value="1" <?= Database::setting('onchain_caution', '0') === '1' ? 'selected' : '' ?>>Yes &mdash; raise the score bar 25% during a surge</option>
        </select>
        <p class="hint">Weaker than the volatility circuit breaker's 50% on purpose: a busy chain
          is a softer signal than a price shock that has already happened.</p></div>
      <div><label>Call it a surge above this multiple of the 30-day median</label>
        <input type="number" min="1.1" max="10" step="0.1" name="onchain_surge_ratio"
               value="<?= sma_e(Database::setting('onchain_surge_ratio', '1.8')) ?>">
        <p class="hint">The median, not the average &mdash; one enormous day drags a mean up and
          then makes itself look normal.</p>
        <label style="margin-top:10px">Settled-value feed (blank = blockchain.info)</label>
        <input type="url" name="onchain_volume_url" style="font-family:var(--font-mono)"
               value="<?= sma_e(Database::setting('onchain_volume_url', '')) ?>">
        <label style="margin-top:10px">Mempool feed (blank = mempool.space)</label>
        <input type="url" name="onchain_mempool_url" style="font-family:var(--font-mono)"
               value="<?= sma_e(Database::setting('onchain_mempool_url', '')) ?>"></div>
    </div>

    <label>Funding-rate endpoint (Binance-futures-compatible, used by the funding contrarian rule)</label>
    <input type="text" name="funding_api_url" value="<?= $s('funding_api_url', 'https://fapi.binance.com') ?>" style="font-family:var(--font-mono)">
    <?php // The other three sources, which had no fields at all.
          //
          // MarketData reads every one of them and there was no way to set
          // any: a site could not serve a stock or a forex pair, because the
          // key that makes those work could only be put in by editing the
          // database. A feature that cannot be switched on is not a feature. ?>
    <label>Futures endpoints &mdash; one per line (open interest and long/short ratio)</label>
    <textarea name="futures_endpoints" rows="2" style="font-family:var(--font-mono)"><?= $s('futures_endpoints') ?></textarea>
    <p class="hint">Left blank the funding endpoint above is used. Order matters; the first host
      that answers wins.</p>

    <?php
    // MARKET-FLOW FEED HEALTH, BECAUSE IT FAILED IN SILENCE.
    //
    // MarketData::futuresGet() catches everything and returns null, so an
    // unreachable host is indistinguishable from a coin with no perpetual: the
    // rule quietly does not vote and nothing says why. Measured while this was
    // written, fapi.binance.com answered HTTP 451 - geo-blocked - and the four
    // rules below had fired ZERO times across a thousand stored signals. Four
    // rules in the rule list, tunable, documented, and structurally incapable
    // of ever firing.
    //
    // Live figures rather than a claim: the ping is real, and the fire counts
    // come from this install's own settled record.
    $flowHealth = [];
    try {
        $flowHealth = \SignalMasterAi\MarketData::futuresHealth();
    } catch (Throwable $e) {
    }
    $flowAlive = false;
    foreach ($flowHealth as $h) {
        if (!empty($h['ok'])) {
            $flowAlive = true;
        }
    }
    $flowStats = [];
    try {
        $flowStats = \SignalMasterAi\Outcomes::ruleStats(1000);
    } catch (Throwable $e) {
    }
    ?>
    <div class="tbl-scroll">
    <table class="grid feed-list" style="margin-top:10px">
      <tr><th>Futures host</th><th style="width:90px">Status</th><th>Detail</th></tr>
      <?php foreach ($flowHealth as $h): ?>
        <tr>
          <td><code><?= sma_e((string)$h['host']) ?></code></td>
          <td><?= !empty($h['ok'])
                ? '<span style="color:var(--good)">OK ' . (int)$h['ms'] . 'ms</span>'
                : '<span style="color:var(--bad)">HTTP ' . (int)$h['code'] . '</span>' ?></td>
          <td><?= sma_e((string)$h['note']) ?: '&mdash;' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>

    <?php if (!$flowAlive): ?>
      <p class="hint" style="border-left:3px solid var(--bad);padding-left:10px">
        <strong>No futures host is answering, so these four rules cannot vote at all:</strong>
        <?php foreach (\SignalMasterAi\MarketData::FUTURES_RULES as $rk => $rn): ?>
          <br><code><?= sma_e($rk) ?></code> &mdash; <?= sma_e($rn) ?>
          <?php $fired = (int)($flowStats[$rk]['fired'] ?? 0); ?>
          <span class="hint">(fired <?= $fired ?> time<?= $fired === 1 ? '' : 's' ?> in your
            settled record)</span>
        <?php endforeach; ?>
        <br><br>They are not broken and there is nothing to fix in the rules themselves &mdash;
        the data never arrives. Binance geo-restricts its futures API, so a server in a
        restricted region gets HTTP 451 on every call. Add a reachable mirror above and they
        start voting; leave it and the engine is running on price and volume alone, which is
        worth knowing rather than guessing at.
      </p>
    <?php else: ?>
      <p class="hint">Flow rules live on this feed:
        <?php $bits = [];
              foreach (\SignalMasterAi\MarketData::FUTURES_RULES as $rk => $rn) {
                  $bits[] = '<code>' . sma_e($rk) . '</code> (fired '
                      . (int)($flowStats[$rk]['fired'] ?? 0) . ')';
              }
              echo implode(' &middot; ', $bits); ?>.
        A rule showing 0 after the feed has been up for a while is a threshold that is too
        extreme, not a broken feed.</p>
    <?php endif; ?>
    <div class="row2">
      <div><label>Twelve Data API key (stocks, forex, indices)
          <?= Database::setting('twelvedata_key') !== '' ? '<span class="hint">(saved &mdash; leave blank to keep)</span>' : '' ?></label>
        <?php // Write-only, like every other credential on this site. This one
              // was a type="text" carrying its own value, so the key was in
              // the page source of every settings load - the only key on the
              // site that was. ?>
        <input type="password" name="twelvedata_key" value="" autocomplete="new-password"
               style="font-family:var(--font-mono)" placeholder="<?= Database::setting('twelvedata_key') !== '' ? '••••••••' : '' ?>">
        <p class="hint">Only needed for non-crypto symbols. Without it those coins cannot be priced
          at all, whatever else is configured.</p></div>
      <div><label>Stooq endpoint (free end-of-day equities)</label>
        <input type="text" name="stooq_endpoint" value="<?= $s('stooq_endpoint', 'https://stooq.com') ?>"
               style="font-family:var(--font-mono)"></div>
    </div>
    <?php // Every timeframe control now lives in the Timeframes card below.
          //
          // The master list passed through here on its way out of Appearance,
          // where it had been labelled "Timeframe buttons shown on the site"
          // and filed with the brand colours. Next to the API endpoints was an
          // improvement and still not the answer: the operator was setting
          // timeframes in three cards on two tabs and had to hold the order of
          // narrowing in their head to know which one they were changing. ?>
    <?php // What a chart opens on is a chart-page setting, not an endpoint
          // one; it lives under Site > Chart page with the rest of them. ?>
    <p class="hint">This card is the exchange connection and nothing else.
      <a href="#timeframes"><strong>Timeframes</strong></a> are further down this tab; what the
      chart page shows is under <strong>Site &rsaquo; Chart page</strong>.
      <a href="#apitest"><strong>Test these endpoints</strong></a> from the last card on this
      tab &mdash; it needs its own form, so it cannot live inside this one.</p>
  </details>

  <?php
  // ONE LIST. NOT THREE.
  //
  // There were four timeframe settings: the site's master list, the scan's
  // own, the weekly re-calibration's, and the frame a chart opens on. The
  // first three were LISTS, each narrowing the one above it, and putting them
  // in one card made the rule visible without making it simple - a reader
  // still had to hold three lists in their head to predict what would be
  // scanned, and three boxes to tick to turn a frame on.
  //
  // Nobody wanted them separate. "Run 15m but do not scan it" is not a thing
  // an operator asks for: a frame that is not scanned has no verdicts, so it
  // shows nothing on the chart, nothing on the scanner, nothing in the track
  // record and never alerts - it is off, with extra steps. The narrower lists
  // were a way of saving work that instead produced a timeframe which looks
  // enabled and does nothing.
  //
  // So: tick a frame and everything runs it - cron scans it, alerts fire on
  // it, charts offer it, the weekly re-calibration tunes it. Untick it and all
  // of that stops together. The frame a chart OPENS on stays, because it is
  // one choice out of the list rather than a list of its own.
  $tfAll     = $config['market']['intervals'];
  $tfEnabled = \SignalMasterAi\Visibility::siteTfs($tfAll);
  $tfR       = \SignalMasterAi\Gate::TF_EXPECTANCY;   // one definition - see Gate
  ?>
  <details class="form-card" data-tab="market" id="timeframes" open>
    <summary>Timeframes</summary>
    <p class="hint"><strong>One list.</strong> A timeframe ticked here is scanned by cron, alerted
      on, offered on the chart, published in the track record and tuned by the weekly
      re-calibration. Unticked, all of that stops together &mdash; there is no way to have a
      timeframe that looks on and does nothing.</p>
    <?php // The expectancy numbers appear once, with the list they inform,
          // rather than beside two pickers in two different shapes. ?>
    <p class="hint" style="margin:0 0 8px">Expectancy measured on this install's replayed history,
      after costs. The fast frames win <em>more</em> often &mdash; 73% on 5m &mdash; and still lose
      money, because the wins are small, the losses are full size, and the stop sits close enough to
      the round-trip fee that no sequence of price moves pays for it. 5m and below are off by
      default for that reason; tick one only if your own track record disagrees.</p>
    <div style="display:flex;flex-wrap:wrap;gap:4px 14px">
      <?php foreach ($tfAll as $iv): ?>
        <label style="display:inline-flex;align-items:center;gap:6px;color:var(--text);margin:3px 0">
          <input type="checkbox" name="intervals[]" value="<?= sma_e($iv) ?>" <?= in_array($iv, $tfEnabled, true) ? 'checked' : '' ?>>
          <?= sma_e($iv) ?><?php if (isset($tfR[$iv])): ?><span class="hint"
            style="display:inline"><?= $tfR[$iv] ?></span><?php endif; ?>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="hint" style="margin-top:8px">Each frame multiplies the scan's work: 30 coins across 3
      timeframes is 90 jobs a cycle, and at 10 per run that is 9 cron runs before it starts again.
      How one run is divided between them is shown under
      <a href="#cron"><strong>Background worker (cron)</strong></a>.</p>

    <?php // ONE CONTROL. The frame a chart opens on moved to Site > Chart
          // page, with the other things that decide what that page shows, and
          // the weekly re-calibration's on/off switch moved back to the engine
          // card where the rest of the learning settings are. Neither is a
          // timeframe LIST - one is a single choice out of this list and the
          // other is a yes/no - and leaving them here meant a card called
          // "Timeframes" still answered three questions. ?>
    <p class="hint" style="margin-top:14px"><strong>Per-coin exceptions</strong> live with the coin,
      not here: <a href="symbols.php">Coins</a> &rsaquo; a coin's timeframes, which narrows this
      list for that one pair.</p>
  </details>

  <details class="form-card" data-tab="site">
    <summary>Landing page</summary>
    <div class="row2">
      <div><label>Homepage mode</label>
        <select name="landing_enabled">
          <option value="1" <?= Database::setting('landing_enabled', '1') === '1' ? 'selected' : '' ?>>Hero landing page (charts at /charts.php)</option>
          <option value="0" <?= Database::setting('landing_enabled', '1') === '0' ? 'selected' : '' ?>>Straight to live charts</option>
        </select></div>
      <div><label>Hero CTA button text</label><input type="text" name="hero_cta" value="<?= $s('hero_cta', 'View live signals') ?>"></div>
    </div>
    <label>Hero title</label>
    <input type="text" name="hero_title" value="<?= $s('hero_title') ?>">
    <label>Hero subtitle</label>
    <textarea name="hero_subtitle" rows="2"><?= $s('hero_subtitle') ?></textarea>
    <p class="hint">The landing page also shows live stats (coins, signals, verified win rate),
      features, how-it-works and your pricing plans - all pulled automatically from the database.</p>
  </details>

  <details class="form-card" data-tab="site" open>
    <summary>Appearance</summary>
    <div class="row2">
      <div><label>Accent color</label><input type="color" name="accent_color" value="<?= $s('accent_color', \SignalMasterAi\View::BRAND_DEFAULTS['accent_color']) ?>" style="height:42px;padding:4px"></div>
      <div><label>Bullish / up color</label><input type="color" name="up_color" value="<?= $s('up_color', \SignalMasterAi\View::BRAND_DEFAULTS['up_color']) ?>" style="height:42px;padding:4px"></div>
      <div><label>Bearish / down color</label><input type="color" name="down_color" value="<?= $s('down_color', \SignalMasterAi\View::BRAND_DEFAULTS['down_color']) ?>" style="height:42px;padding:4px"></div>
    </div>
    <?php // WEBFONTS, WITH THE TRADE-OFF STATED.
          //
          // The only thing in this application that reaches a third party on
          // a page load, so it gets a switch rather than being assumed. Off is
          // not a downgrade to something broken: the stylesheet's fallback
          // chain is a Palatino-class serif, the platform UI face and the
          // platform mono, chosen so the site is correct without a webfont at
          // all. What is lost is the tabular figures that stop a live price
          // jittering as it ticks, on platforms whose default mono is not
          // tabular.
          //
          // Turning this off also narrows the Content-Security-Policy back
          // down - font-src and style-src stop naming Google's origins - so
          // the switch is a real reduction in what the page is allowed to
          // load, not just a cosmetic preference. ?>
    <div><label>Web fonts</label>
      <select name="webfonts">
        <option value="1" <?= Database::setting('webfonts', '1') === '1' ? 'selected' : '' ?>>Load Instrument Serif / Inter Tight / JetBrains Mono (Google Fonts)</option>
        <option value="0" <?= Database::setting('webfonts', '1') === '0' ? 'selected' : '' ?>>System fonts only - no external request, tighter CSP</option>
      </select>
    </div>
    <?php // The timeframe list used to sit here, between the brand colours and
          // the footer text, labelled "Timeframe buttons shown on the site" -
          // which made the setting that decides what the ENGINE runs read like
          // a piece of styling. It has moved to Market data, next to the scan
          // that obeys it. ?>
    <label>Footer text (shown after the site name)</label>
    <textarea name="footer_text"><?= $s('footer_text') ?></textarea>
  </details>

  <details class="form-card" data-tab="site" open>
    <summary>Legal &amp; trust</summary>
    <div class="row2">
      <div><label>Public track-record page (performance.php - shows every verified signal, wins AND losses)</label>
        <select name="performance_page_enabled">
          <option value="1" <?= Database::setting('performance_page_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled — linked in the footer (recommended for trust)</option>
          <option value="0" <?= Database::setting('performance_page_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select></div>
      <div></div>
    </div>

    <label>Terms of Use (plain text; blank lines make paragraphs)</label>
    <textarea name="terms_content" rows="8"><?= $s('terms_content') ?></textarea>
    <label>Privacy Policy</label>
    <textarea name="privacy_content" rows="8"><?= $s('privacy_content') ?></textarea>
    <label>Risk Disclosure</label>
    <textarea name="risk_content" rows="6"><?= $s('risk_content') ?></textarea>
    <p class="hint">Shown at <code>page.php?p=terms</code> / <code>privacy</code> / <code>risk</code>,
      linked from every footer. Solid generic defaults are provided - review them once and adjust
      for your jurisdiction before launch.</p>
  </details>

  <details class="form-card" data-tab="site">
    <summary>Track record page &mdash; what it shows</summary>
    <?php $pf = fn(string $k) => Database::setting($k, '1') === '1'; ?>
    <p class="hint" style="margin-top:6px">What that page publishes. The <strong>list of trades</strong>
      is the record; everything else on it is commentary &mdash; headline ratios, an equity curve,
      breakdowns by timeframe and coin, and a verdict on whether the sample proves anything yet. Turn
      off what you do not want to publish. The risk warning stays whatever you choose.</p>
    <div class="row2">
      <div><label>Headline figures and the verdict</label>
        <select name="perf_show_stats">
          <option value="1" <?= $pf('perf_show_stats') ? 'selected' : '' ?>>Show</option>
          <option value="0" <?= !$pf('perf_show_stats') ? 'selected' : '' ?>>Hide</option>
        </select>
        <span class="hint">Expectancy, win rate, profit factor, and the confidence box that says
          whether the record proves an edge or a loss.</span></div>
    </div>
    <div class="row2">
      <div><label>Breakdowns</label>
        <select name="perf_show_breakdowns">
          <option value="1" <?= $pf('perf_show_breakdowns') ? 'selected' : '' ?>>Show</option>
          <option value="0" <?= !$pf('perf_show_breakdowns') ? 'selected' : '' ?>>Hide</option>
        </select>
        <span class="hint">By timeframe, how trades ended, and how they behaved while open.</span></div>
      <div><label>The list of verified trades</label>
        <select name="perf_show_trades">
          <option value="1" <?= $pf('perf_show_trades') ? 'selected' : '' ?>>Show</option>
          <option value="0" <?= !$pf('perf_show_trades') ? 'selected' : '' ?>>Hide</option>
        </select>
        <span class="hint">Every settled trade with the plan it was published with. Hiding this and
          keeping the figures above leaves a page of statistics with nothing behind them &mdash; the
          opposite of what the page is for.</span></div>
    </div>
    <div class="row2">
      <div><label>Detailed track record</label>
        <select name="perf_detail">
          <option value="1" <?= $pf('perf_detail') ? 'selected' : '' ?>>Show the detail</option>
          <option value="0" <?= !$pf('perf_detail') ? 'selected' : '' ?>>Keep it simple</option>
        </select>
        <span class="hint">Adds <strong>profit factor</strong> to the headline figures, and three
          columns to every trade: <strong>Heat</strong> (how far it
          went against you before it resolved), <strong>Best</strong> (how far it went in your
          favour) and <strong>Bars</strong> (how long it stayed open). Heat is the one that decides
          whether a trade was actually holdable. Costs nothing to switch on &mdash; all three are
          already recorded on every signal; this only decides whether the page prints them.
          Turn it off for a shorter page aimed at first-time visitors.</span></div>
    </div>
    <div class="row2">
      <div><label>Trades per page (0 = all of them)</label>
        <input type="number" name="perf_page_size" min="0"
               value="<?= (int)Database::setting('perf_page_size', '50') ?>">
        <span class="hint">The list was capped at a hundred with nothing to say the rest existed.
          It now loads this many at a time, with a button that says how many are left and a second
          one that jumps straight to all of them. <strong>0 publishes every settled trade on first
          load</strong>, however many there are &mdash; there is no ceiling, because rows are streamed
          from the database one at a time rather than gathered into memory first. The paging button
          works without JavaScript; with it, rows are appended instead of reloading.</span></div>
      <div><label>Table width</label>
        <select name="perf_wide_table">
          <option value="1" <?= Database::setting('perf_wide_table', '1') === '1' ? 'selected' : '' ?>>Full window width</option>
          <option value="0" <?= Database::setting('perf_wide_table', '1') === '0' ? 'selected' : '' ?>>Same column as the text</option>
        </select>
        <span class="hint">Fourteen columns in a 900px column of prose is unreadable. On full width
          the table uses the window and the writing around it keeps its measure. Narrow screens are
          unaffected either way &mdash; the table scrolls sideways on its own.</span></div>
    </div>
  </details>

  <?php // Its own card.
        //
        // "Scanner rows" sat in the card headed "Track record page -
        // what it shows", among six settings that really are about the
        // track record. It is not one of them: it controls the "Live
        // setups" sheet's board (charts.php), a different page. Somebody
        // tuning the track record changed the scanner without meaning to,
        // and somebody looking for a scanner setting had no reason to open
        // a card named after another page.
        ?>
  <?php // Chart page, next to the other two per-page cards.
        //
        // These four were in three different places: the default symbol and
        // the candle count under Market data APIs among the exchange
        // endpoints, and the two display switches inside an "extra data feeds"
        // accordion on the engine card. All four decide what one page looks
        // like, which is the same kind of decision the Scanner page and Track
        // record page cards already own. An operator who wants to change the
        // chart page should not have to know which of the engine's internals
        // the switch happens to live near. ?>
  <details class="form-card" data-tab="site" id="chartpage">
    <summary>Chart page</summary>
    <div class="row2">
      <div><label>Default symbol (the coin a chart opens on)</label>
        <input type="text" name="default_symbol" value="<?= $s('default_symbol', 'BTCUSDT') ?>"></div>
      <div><label>Default timeframe (the frame a chart opens on)</label>
        <?php // Offered from the site's own list, never from every interval the
              // app supports: a default set to a switched-off frame opened the
              // chart on a timeframe its own picker was about to remove. ?>
        <select name="default_interval">
          <?php foreach (\SignalMasterAi\Visibility::siteTfs($config['market']['intervals']) as $iv): ?>
            <option value="<?= sma_e($iv) ?>" <?= Database::setting('default_interval', '1h') === $iv ? 'selected' : '' ?>><?= sma_e($iv) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="hint">One choice out of
          <a href="#timeframes"><strong>Timeframes</strong></a>. Members switch frames on the chart
          themselves; this is only where it starts.</span></div>
    </div>
    <div class="row2">
      <div><label>Candles per chart (100 - 1000)</label>
        <input type="number" name="candle_limit" value="<?= $s('candle_limit', '500') ?>"></div>
      <div><label>Refresh the chart every N seconds (0 = manual only)</label>
        <input type="number" name="signal_auto_seconds" value="<?= $s('signal_auto_seconds', '60') ?>">
        <p class="hint">Each refresh re-reads the coin on screen, so this is one pass of the rule
          set per open tab per interval &mdash; the main cost a busy chart page carries. It also
          paces the traffic-driven alert dispatch, the fallback that keeps alerts moving on a site
          whose cron is unreliable, so 0 leaves that entirely to
          <a href="#cron">the background worker</a>.</p></div>
    </div>
    <div class="row2">
      <div><label>Every-timeframe verdict strip</label>
        <select name="mtf_strip_enabled">
          <option value="1" <?= Database::setting('mtf_strip_enabled', '1') === '1' ? 'selected' : '' ?>>Shown — last stored verdict for each enabled timeframe</option>
          <option value="0" <?= Database::setting('mtf_strip_enabled', '1') === '0' ? 'selected' : '' ?>>Hidden</option>
        </select>
        <p class="hint">A 5m BUY under a falling daily is a different trade from a 5m BUY with
          everything above it agreeing, and the signal card cannot show that. Read from stored
          verdicts rather than re-running the engine per timeframe, so it costs one query; a frame
          with nothing stored says so instead of reading as neutral. Who may see it is set under
          <a href="#gates">What free and premium accounts get</a>.</p>
      </div>
      <div><label>Market context panel</label>
        <select name="context_panel_enabled">
          <option value="1" <?= Database::setting('context_panel_enabled', '1') === '1' ? 'selected' : '' ?>>Shown — market context below the chart</option>
          <option value="0" <?= Database::setting('context_panel_enabled', '1') === '0' ? 'selected' : '' ?>>Hidden</option>
        </select>
        <p class="hint">Fear &amp; Greed gauge, funding, long/short, open interest, taker flow,
          breadth &mdash; these are the same figures the engine already scores. Showing them is what
          turns a verdict into something a reader can check rather than a number they have to take
          on trust. Every cell fills on its own, so a feed that returns nothing leaves a dash. Which
          feeds are collected at all is under <strong>Signals &rsaquo; Signal engine thresholds
          &rsaquo; Market inputs</strong>.</p>
      </div>
    </div>
  </details>

  <details class="form-card" data-tab="site">
    <summary>Scanner page</summary>
    <div class="row2">
        <div><label>Scanner rows (0 = every coin)</label>
          <input type="number" name="scanner_rows" min="0"
                 value="<?= (int)Database::setting('scanner_rows', '0') ?>">
          <span class="hint">The market scanner was capped at three hundred rows, which is a number
            nobody chose &mdash; four hundred coins across five timeframes is two thousand possible
            rows, and the page said nothing about the ones it left out. 0 shows every coin that passes
            the visitor's filters; the board is one row per coin and timeframe, so it is bounded by
            your own coin list rather than by history.</span></div>
      <div></div>
    </div>
  </details>


  <details class="form-card" data-tab="market" open>
    <summary>News &amp; events</summary>
    <label>Show news section on the public site</label>
    <select name="news_enabled">
      <option value="1" <?= Database::setting('news_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled (fully automated)</option>
      <option value="0" <?= Database::setting('news_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
    </select>
    <div data-dep="news_enabled" data-dep-value="1"
         data-dep-note="The news section is switched off, so none of these are doing anything on the public site.">
    <div class="row2">
      <div><label>News refresh TTL in seconds (60 - 86400)</label><input type="number" name="news_cache_ttl" value="<?= $s('news_cache_ttl', '300') ?>"></div>
      <div><label>Headlines shown (5 - 100)</label><input type="number" name="news_max_items" value="<?= $s('news_max_items', '30') ?>"></div>
    </div>
    <div class="row2">
      <div><label>Headline retention in days (1 - 365)</label><input type="number" name="news_retention_days" value="<?= $s('news_retention_days', '30') ?>"></div>
      <div><label>Site live-refresh interval in seconds (30 - 3600)</label><input type="number" name="news_poll_seconds" value="<?= $s('news_poll_seconds', '90') ?>"></div>
    </div>
    </div><?php // end of the news-only block ?>
    <p class="hint">Sources are managed on the <a href="news.php" style="color:var(--accent)">News sources</a> page.
      Optional background refresh: <code>php cron.php</code> or
      <code>cron.php?token=<?= $s('cron_token') ?></code> - not required, the site refreshes itself on views.</p>
  </details>


  <?php if (\SignalMasterAi\Master::master('signals')): ?><details class="form-card" data-tab="signals" open>
    <summary>Signal engine thresholds</summary>
    <details class="acc" id="thresholds">
      <summary>Verdict thresholds</summary>
      <div class="acc-body">
        <p class="hint">Which types of signal the site publishes, what score becomes a call, and the
          two accuracy gates.</p>
      <?php // FIRST IN THE CARD AN OPERATOR OPENS TO CHANGE WHAT GETS PUBLISHED.
            //
            // This shipped inside Self-learning, next to the pre-flight checks
            // it is implemented beside - which is where the CODE lives, not
            // where the question lives. "Which signals do I publish" is the
            // same question as the BUY and SELL thresholds under it, so it
            // sits with them. ?>
      <div id="signaltypes">
        <label>Which signal types to publish</label>
        <select name="min_rr">
          <?php foreach ([1 => 'All three - 1:1, 1:2 and 1:3',
                          2 => '1:2 and 1:3 only - drop the 1:1s',
                          3 => '1:3 only'] as $mv => $ml): ?>
          <option value="<?= $mv ?>"<?= (int)Database::setting('min_rr', '1') === $mv ? ' selected' : '' ?>>
            <?= sma_e($ml) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="hint">Every plan carries the same three targets &mdash; one, two and three times
          the risk &mdash; and each signal is typed by the furthest of them this pair has actually
          reached inside its deadline often enough to plan for, measured on its own recent history.
          A 1:1 is not a bad signal; it is a shorter trade whose room ran out sooner, and it should
          win more often for that reason. Narrowing this means fewer signals, and every refusal
          appears in <a href="engine.php" style="color:var(--accent)">What refusing cost you</a>
          with its real outcome, so the cost of the choice is measured rather than assumed. What
          each type is actually worth is measured under
          <a href="engine.php" style="color:var(--accent)">What each signal type is worth</a>.</p>
      </div>
      <div class="row2">
      <div><label>BUY when score &ge; <span class="hint">(Plain sum mode only)</span></label>
        <input type="number" step="0.1" name="buy_threshold" value="<?= $s('buy_threshold', '2.0') ?>"></div>
      <div><label>SELL when score &le; <span class="hint">(Plain sum mode only)</span></label>
        <input type="number" step="0.1" name="sell_threshold" value="<?= $s('sell_threshold', '-2.0') ?>"></div>
      <?php $modeNow = \SignalMasterAi\SignalEngine::scoringMode(); ?>
      <p class="hint">
        <?php if ($modeNow === 'sum'): ?>
          Live right now &mdash; Scoring mode on <a href="engine.php#thresholds">Engine Lab</a> is set
          to Plain sum.
        <?php else: ?>
          <strong>Not live right now.</strong> Scoring mode on
          <a href="engine.php#thresholds">Engine Lab</a> is Category-capped (the default), which reads
          <strong>BUY/SELL threshold (category mode)</strong> instead &mdash; editing this pair here
          changes nothing until you switch modes.
        <?php endif; ?>
      </p>
    </div>
    <?php // The chart's refresh interval used to sit here, between the BUY/SELL
          // thresholds and the ADX filter - a page timing among engine tuning,
          // in the one card an operator opens to change how signals are
          // scored. It is under Site > Chart page now, with the rest of what
          // that page does. ?>

    <div class="row2">
      <div><label>ADX regime filter (accuracy)</label>
        <select name="regime_filter_enabled">
          <option value="1" <?= Database::setting('regime_filter_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled — dampen counter-regime rules</option>
          <option value="0" <?= Database::setting('regime_filter_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select></div>
      <div><label>Analyse closed candles only (accuracy)</label>
        <select name="closed_candle_only">
          <option value="1" <?= Database::setting('closed_candle_only', '1') === '1' ? 'selected' : '' ?>>Enabled — no repaint from the forming bar</option>
          <option value="0" <?= Database::setting('closed_candle_only', '1') === '0' ? 'selected' : '' ?>>Disabled — include the live candle</option>
        </select></div>
    </div>
      </div>
    </details>
    <details class="acc" id="selflearn">
      <summary>Self-learning</summary>
      <div class="acc-body">
        <p class="hint">Whether the engine re-weights its own rules from verified outcomes, and how much evidence it wants first.</p>
    <div class="row2">
      <div><label>Automatic daily rule tuning (self-learning)</label>
        <select name="autotune_enabled">
          <option value="1" <?= Database::setting('autotune_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled — weights follow verified hit rates</option>
          <option value="0" <?= Database::setting('autotune_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled — weights change only manually</option>
        </select></div>
      <div><label>Min verified outcomes per rule before tuning (5 - 200)</label>
        <input type="number" name="autotune_min_samples" value="<?= $s('autotune_min_samples', '10') ?>"></div>
    </div>
    <?php
    // How much history the tuning actually sees, and - the part that matters -
    // how long that turns out to be. The window is a ROW COUNT, so the same
    // 500 is three months on a fifty-coin watchlist and about two days on a
    // five-hundred-coin one. Nothing about the setting changes as a site
    // grows, so nothing warns you; this line is the warning.
    [$lwRows, $lwDays] = \SignalMasterAi\Outcomes::learnWindow();
    $lwSpan = null;
    try {
        $lwQ = $pdo->prepare(
            "SELECT MIN(closed_at) a, MAX(closed_at) b, COUNT(*) n FROM (
               SELECT closed_at FROM signals
               WHERE outcome IN ('confirmed','invalid') AND `signal` != 'NEUTRAL' AND closed_at > 0
               ORDER BY created_at DESC LIMIT " . (int)$lwRows . ") w");
        $lwQ->execute();
        $lwSpan = $lwQ->fetch();
    } catch (\Throwable $e) {
        $lwSpan = null;
    }
    $lwN = (int)($lwSpan['n'] ?? 0);
    $lwCovers = ($lwSpan && (int)$lwSpan['a'] > 0)
        ? max(1, (int)round(((int)$lwSpan['b'] - (int)$lwSpan['a']) / 86400)) : null;
    ?>
    <p class="hint" style="margin:2px 0 8px">
      <?php if ($lwN === 0): ?>
        <strong>Nothing settled yet</strong>, so there is nothing to learn from. These windows start
        to matter once trades begin resolving.
      <?php else: ?>
        Right now the tuner is learning from <strong><?= number_format($lwN) ?></strong> settled
        trades<?= $lwCovers !== null ? ', covering <strong>' . $lwCovers . ' day'
            . ($lwCovers === 1 ? '' : 's') . '</strong> of market' : '' ?>.
        <?php if ($lwCovers !== null && $lwCovers < 14 && $lwN >= $lwRows): ?>
          <br><strong>That is a short period.</strong> The window is a number of trades, not a
          number of days, so it shrinks in time as you add coins - and the engine ends up tuned on
          the last few days of market, which is when you would want it looking wider. Raise the row
          count, or set a day window below.
        <?php endif; ?>
      <?php endif; ?>
    </p>
    <div class="row2">
      <div><label>Trades the rule tuning learns from</label>
        <input type="number" name="learn_window_rows" min="50" max="100000" value="<?= $s('learn_window_rows', '500') ?>">
        <p class="hint">The newest N settled trades. Each nightly run re-reads them from scratch and
          re-derives every weight from its shipped baseline &mdash; it never stacks yesterday's
          adjustment on today's, so the engine cannot drift or compound a bad week.</p></div>
      <div><label>Trades the learned model trains on</label>
        <input type="number" name="learn_model_rows" min="200" max="200000" value="<?= $s('learn_model_rows', '3000') ?>">
        <p class="hint">Wider on purpose: this fits a number per feature rather than one per rule,
          so it needs more evidence to say anything at all.</p></div>
    </div>
    <?php // The learned model's own switch. It had none - LearnedModel::predict()
          // read the setting and nothing on the site could set it, so the row
          // count above tuned a component the operator could not turn off. ?>
    <div class="row2">
      <div><label>Learned model</label>
        <select name="learned_model_enabled">
          <option value="1" <?= Database::setting('learned_model_enabled', '1') === '1' ? 'selected' : '' ?>>On — its vote joins the score once it has trained</option>
          <option value="0" <?= Database::setting('learned_model_enabled', '1') === '0' ? 'selected' : '' ?>>Off — rule weights only</option>
        </select>
        <p class="hint">A regression over the fired-rule vectors of settled trades, retrained on the
          row count above. Off leaves the engine on its rule weights alone, which is what a fresh
          install runs on until there is enough history to fit anything.</p></div>
      <div></div>
    </div>
    <?php // PER-COIN LEARNING, AND THE ONE NUMBER THAT MAKES IT SAFE.
          //
          // Splitting the record thirty ways and letting each coin learn alone
          // is how a rule that won two of three trades on one pair gets
          // promoted for what a fair coin does half the time. So a coin's own
          // estimate is mixed with the global one in proportion to what it has
          // actually settled, and this box is where that proportion is set. ?>
    <?php // Setups the engine declined - recorded, settled, and never shown. ?>
    <div class="row2">
      <div><label>Learn from setups it declined</label>
        <select name="shadow_enabled">
          <option value="1" <?= Database::setting('shadow_enabled', '1') === '1' ? 'selected' : '' ?>>On — record and settle them</option>
          <option value="0" <?= Database::setting('shadow_enabled', '1') === '0' ? 'selected' : '' ?>>Off — learn only from published signals</option>
        </select>
        <p class="hint">The engine only ever saw the trades it took, so it could refine its judgement
          but never question it. These are the ones it turned down &mdash; a near miss on score, or a
          verdict a filter stood down &mdash; walked forward against real candles with the plan they
          would have had. Members never see them; they live in their own table and feed the tuner.
          See <a href="engine.php">Engine &rsaquo; What refusing cost you</a>.</p></div>
      <div><label>How close a near miss has to be</label>
        <input type="number" name="shadow_near_band" min="0.1" max="0.99" step="0.05" value="<?= $s('shadow_near_band', '0.6') ?>">
        <p class="hint">As a fraction of the threshold: 0.6 records anything that reached 60% of the
          score needed. Higher records fewer and closer misses. Setups a filter refused are always
          recorded whatever this says &mdash; those are the ones worth arguing about.</p></div>
    </div>
    <div class="row2">
      <div><label>Feed them into the weight tuning</label>
        <select name="shadow_learn">
          <option value="1" <?= Database::setting('shadow_learn', '1') === '1' ? 'selected' : '' ?>>Yes — a rule's record covers every time it fired</option>
          <option value="0" <?= Database::setting('shadow_learn', '1') === '0' ? 'selected' : '' ?>>No — report them, but tune on published signals only</option>
        </select>
        <p class="hint">They join the training half only. The hold-out that decides whether new
          weights are kept stays on published signals, because that is what the site is judged on.</p></div>
      <div><label>Keep declined setups for (days)</label>
        <input type="number" name="shadow_retention_days" min="7" max="3650" value="<?= $s('shadow_retention_days', '120') ?>">
        <p class="hint">They are evidence for the tuner, not a record anyone looks up, so they age out
          faster than published signals.</p></div>
    </div>

    <div class="row2">
      <div><label>Per-coin learning</label>
        <?php $symMode = \SignalMasterAi\Outcomes::symLearnMode(); ?>
        <select name="sym_learn_mode">
          <option value="auto" <?= $symMode === 'auto' ? 'selected' : '' ?>>Automatic — shared at first, the coin's own as it proves it</option>
          <option value="coin" <?= $symMode === 'coin' ? 'selected' : '' ?>>Coin only — each coin uses its own record, whole</option>
          <option value="global" <?= $symMode === 'global' ? 'selected' : '' ?>>Shared only — no coin has its own opinion</option>
        </select>
        <p class="hint"><strong>Automatic</strong> blends the two in proportion to what a coin has
          settled, so a new pair behaves exactly like the shared weights and earns a voice only by
          earning it. It is the default because it cannot be wrong: with no data it <em>is</em> the
          shared weights.<br>
          <strong>Coin only</strong> trusts each coin's own measurement whole, from
          <?= \SignalMasterAi\Outcomes::SYM_MIN_SAMPLES ?> settled trades upward (below that no coin
          is looked at in any mode). That is honest on a pair with hundreds of settled trades and
          noise on one with six &mdash; four wins from six is well inside what a coin toss does, and
          a rule wrongly cut on a pair fires less there, so it gathers less evidence and cannot
          recover. Use it when your coins have long records.<br>
          <strong>Shared only</strong> is the behaviour before per-coin learning existed.</p></div>
      <div><label>Trades for a coin to carry half its own weight <span class="hint">(Automatic only)</span></label>
        <input type="number" name="sym_learn_k" min="1" max="1000" value="<?= $s('sym_learn_k', '40') ?>">
        <p class="hint">At this many settled trades a coin's own measurement counts for half, and the
          shared weight for the other half. Lower trusts a coin sooner; higher waits for more proof.
          Whatever it lands on is clamped to &plusmn;25% and printed in the signal's own reasons.</p></div>
    </div>
    <div class="row2">
      <div><label>Also ignore anything older than (days, 0 = no limit)</label>
        <input type="number" name="learn_window_days" min="0" max="3650" value="<?= $s('learn_window_days', '0') ?>">
        <p class="hint">The safety net for growth. With both set the <em>narrower</em> wins: at most
          the row count above, and nothing older than this. 90 is a reasonable starting point if you
          plan to add a lot of coins &mdash; it keeps the learning period the same length however
          many signals you produce. Left at 0 nothing changes.</p></div>
      <div></div>
    </div>
    <div class="row2">
      <div><label>Pre-flight check before a signal is published</label>
        <select name="preflight_enabled">
          <option value="1" <?= Database::setting('preflight_enabled', '1') === '1' ? 'selected' : '' ?>>On &mdash; check the data and the plan (default)</option>
          <option value="0" <?= Database::setting('preflight_enabled', '1') === '0' ? 'selected' : '' ?>>Off</option>
        </select>
        <p class="hint">The last look before anybody is told. Not another opinion about the market
          &mdash; the score threshold, chop filter, cooldown and the rest are that. This asks the
          questions that have right answers: is the feed still arriving, are there holes in the
          candles the indicators were computed across, and does the plan hold together.
          <br>A member's own manual trade is already refused if its stop is on the wrong side of
          the entry. <strong>The engine's own plan was never checked for the same thing</strong>,
          on the reasoning that it builds its own levels &mdash; which holds until a flat series or
          a zero ATR makes it false, and then the site publishes a trade that closes at a loss on
          the first tick. Data quality <em>warns</em>; an incoherent plan is <em>refused</em> and
          recorded as a shadow.</p></div>
      <div><label>Market-structure levels stay live for (bars)</label>
        <input type="number" min="5" max="500" step="1" name="smc_structure_max_age"
               value="<?= sma_e(Database::setting('smc_structure_max_age', '60')) ?>">
        <p class="hint">Order blocks, breakers and equal-high shelves. Breaks of structure,
          character changes, liquidity sweeps and fair value gaps were all bounded by an age;
          these three were not, so a level from three hundred bars ago still voted at full
          weight and still printed &ldquo;300 bars old&rdquo; in its own reason text as though
          saying it were the same as acting on it.</p></div>
      <div><label>Warn when the newest candle is older than this many bars</label>
        <input type="number" min="1" max="50" step="1" name="preflight_max_stale_bars"
               value="<?= sma_e(Database::setting('preflight_max_stale_bars', '3')) ?>">
        <label style="margin-top:10px">Warn when this much of the window is missing (%)</label>
        <input type="number" min="0" max="50" step="1" name="preflight_max_gap_pct"
               value="<?= sma_e(Database::setting('preflight_max_gap_pct', '2')) ?>">
        <p class="hint">Gap detection has been in this codebase the whole time and was called from
          nowhere. 0 switches the gap warning off.</p>
        <label style="margin-top:10px">Refuse a call once price has covered this much of the way to
          target 1 (%)</label>
        <input type="number" min="0" max="100" step="5" name="preflight_max_travelled_pct"
               value="<?= sma_e(Database::setting('preflight_max_travelled_pct', '0')) ?>">
        <p class="hint"><strong>0 is off.</strong> The only pre-flight check that is about money
          rather than correctness: a call published after price has already run most of the way to
          its first target has a fraction of the advertised reward left while the whole stop is
          still at risk. That is exactly the shape this engine suffers from &mdash; it wins two
          trades in three and still loses, because the average win is a third of the average loss.
          Turning it on means fewer signals; the ones left have their reward still in front of
          them. 60 is a sensible first try, and refusals appear in
          <a href="engine.php" style="color:var(--accent)">What refusing cost you</a> so you can
          see whether it was right.
          <br><strong>It cannot be measured by the preset test or the backtester.</strong> A replay
          enters at the bar it decided on, so nothing has travelled anywhere yet and this check can
          never fire there. Judge it on the shadow record instead, which is live evidence.</p></div>
    </div>
    <div class="row2">
      <div><label>Score band gate &mdash; refuse a band your own record says loses</label>
        <select name="score_band_gate">
          <option value="0" <?= Database::setting('score_band_gate', '0') === '0' ? 'selected' : '' ?>>Off &mdash; publish every band (default)</option>
          <option value="1" <?= Database::setting('score_band_gate', '0') === '1' ? 'selected' : '' ?>>On &mdash; stand down bands under the floor below</option>
        </select>
        <p class="hint">The score threshold is a <em>floor</em>: it asks for a minimum amount of
          agreement and nothing caps the top. The engine already measures its win rate per score
          band and already uses that to lower the confidence printed beside a call &mdash; but it
          would still <strong>publish</strong> a band it had measured at 30%, as the strongest
          calls on the site, because they scored highest. That is how tightening the threshold can
          make a site worse: a very high score usually means every indicator agrees, which is most
          often true once the move is mature and the entry is late.
          <br><strong>Read
          <a href="engine.php" style="color:var(--accent)">Engine lab &rsaquo; Confidence
          calibration</a> before turning this on</strong> &mdash; if your bands rise with score,
          leave it off. Refusals become shadow signals, so &ldquo;What refusing cost you&rdquo;
          reports whether the gate was right.</p></div>
      <div><label>Floor: refuse a band under this win rate (%)</label>
        <input type="number" min="0" max="60" step="1" name="score_band_min_winrate"
               value="<?= sma_e(Database::setting('score_band_min_winrate', '45')) ?>">
        <p class="hint">A band has to fail on <strong>both</strong> counts before it is stood
          down: win less often than this, <em>and</em> lose money on average. One statistic on its
          own is too noisy at the sample sizes a real install has &mdash; measured here, a band
          winning 65% over 20 trades has a lower bound of 43.3%, so a single conservative test
          would have refused the best band on the site.</p>
        <label style="margin-top:10px">&hellip; and average worse than this expectancy (R)</label>
        <input type="number" min="-1" max="0.5" step="0.05" name="score_band_max_r"
               value="<?= sma_e(Database::setting('score_band_max_r', '0')) ?>">
        <p class="hint">0 means only bands that actually lose money can qualify.</p>
        <label style="margin-top:10px">&hellip; and only once the band has this many settled signals</label>
        <input type="number" min="20" max="500" step="1" name="score_band_min_n"
               value="<?= sma_e(Database::setting('score_band_min_n', '30')) ?>">
        <p class="hint">Below this the band is left alone. A gate that fires on a thin sample is
          guessing with the authority of a measurement.</p></div>
    </div>
    <div class="row2">
      <div><label>Calibrated confidence (show measured win % of similar signals)</label>
        <select name="calibrated_confidence">
          <option value="1" <?= Database::setting('calibrated_confidence', '1') === '1' ? 'selected' : '' ?>>Enabled — honest, from verified outcomes</option>
          <option value="0" <?= Database::setting('calibrated_confidence', '1') === '0' ? 'selected' : '' ?>>Disabled — model heuristic only</option>
        </select></div>
      <?php // Back with the rest of the learning settings, which is the only
            // thing it has in common with anything. It had its own timeframe
            // list once; that list is gone, because a frame tuned but never
            // scanned has no stored history to replay, so the two were never
            // usefully different. ?>
      <div><label>Weekly automatic backtest re-calibration (cron)</label>
        <select name="auto_backtest_enabled">
          <option value="1" <?= Database::setting('auto_backtest_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled — walk-forward weights every 7 days</option>
          <option value="0" <?= Database::setting('auto_backtest_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <p class="hint">Replays stored history on
          <a href="#timeframes">the timeframes the site runs</a> and moves the rule weights towards
          what actually worked. Walk-forward, so a weight is only trusted on trades it was not
          fitted to.</p></div>
    </div>
      </div>
    </details>
    <details class="acc">
      <summary>Holding and cooldowns</summary>
      <div class="acc-body">
        <p class="hint">How long a call is held once made, and how long the engine stays quiet after one is stopped out.</p>
      <div class="row2">
      <div><label>Flip hysteresis — hold BUY/SELL down to this fraction of the threshold (0 - 0.95, 0 = off)</label>
        <input type="number" name="flip_exit_band" step="0.05" value="<?= $s('flip_exit_band', '0.5') ?>"></div>
      <div><label>Cooldown after a stop-out — skip same-direction signals for N bars (0 = off)</label>
        <input type="number" name="cooldown_bars" value="<?= $s('cooldown_bars', '4') ?>"></div>
    </div>
      </div>
    </details>
    <details class="acc">
      <summary>Safety brakes</summary>
      <div class="acc-body">
        <p class="hint">The conditions that make the engine cautious or silent: scheduled events, flash moves, a record that has turned, and rules that keep losing.</p>
      <div class="row2">
      <div><label>High-impact event caution window</label>
        <select name="event_caution_enabled">
          <option value="1" <?= Database::setting('event_caution_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled — raise thresholds &amp; cap grade near big events</option>
          <option value="0" <?= Database::setting('event_caution_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select></div>
      <div><label>Caution window in hours before the event (1 - 48)</label>
        <input type="number" name="event_caution_hours" value="<?= $s('event_caution_hours', '2') ?>"></div>
    </div>
    <div class="row2">
      <div><label>Flash-move circuit breaker — BTC 1h move that arms it (%)</label>
        <select name="panic_enabled" style="margin-bottom:6px">
          <option value="1" <?= Database::setting('panic_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= Database::setting('panic_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <input type="number" name="panic_move_pct" step="0.5" min="1" max="20"
               value="<?= $s('panic_move_pct', '4') ?>">
        <label>Caution hold after the move (minutes)</label>
        <input type="number" name="panic_hold_min" min="10" max="720" value="<?= $s('panic_hold_min', '90') ?>">
        <p class="hint">When Bitcoin's last closed hour moves this far, every pair needs a stronger
          score and no setup is graded A until the window passes — the rule set is reading a chart
          still reacting to something it cannot see. Regime detection covers the ordinary case; this
          covers the shock that has not finished arriving yet.
          <?php $pu = (int)Database::setting('panic_until', '0');
                if ($pu > time()): ?><strong>Armed for
            <?= (int)ceil(($pu - time()) / 60) ?> more minute(s).</strong><?php else: ?>Not armed
            right now.<?php endif; ?></p>
      </div>
      <div><label>Performance watchdog — recalibrate early when the record turns</label>
        <select name="watchdog_enabled" style="margin-bottom:6px">
          <option value="1" <?= Database::setting('watchdog_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= Database::setting('watchdog_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <div class="row2">
          <div><label>Over the last N verified trades</label>
            <input type="number" name="watchdog_window" min="10" max="500" value="<?= $s('watchdog_window', '30') ?>"></div>
          <div><label>Average R below which it fires</label>
            <input type="number" name="watchdog_floor_r" step="any" min="-2" max="1"
                   value="<?= $s('watchdog_floor_r', '-0.05') ?>"></div>
        </div>
        <p class="hint">Quarantine judges one rule and the self-tuning log judges one change;
          neither notices the case where nothing in particular broke and the whole thing simply
          stopped working. This watches the record as a whole and brings the weekly recalibration
          forward when it turns. Expectancy, not win rate — this engine once ran 67% while losing
          money on every fast timeframe, and a win-rate watchdog would have reported health
          throughout.</p>
      </div>
      <div><label>Rule quarantine — bench rules that keep losing money</label>
        <select name="quarantine_enabled" style="margin-bottom:6px">
          <option value="1" <?= Database::setting('quarantine_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= Database::setting('quarantine_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <label>Bench a rule below this average R (after costs)</label>
        <input type="number" name="quarantine_min_r" step="0.05" min="-2" max="0"
               value="<?= $s('quarantine_min_r', '-0.1') ?>">
        <div class="row2">
          <div><label>Over at least this many trades</label>
            <input type="number" name="quarantine_min_samples" min="10" max="500"
                   value="<?= $s('quarantine_min_samples', '30') ?>"></div>
          <div><label>Probation (days)</label>
            <input type="number" name="quarantine_days" min="1" max="120"
                   value="<?= $s('quarantine_days', '14') ?>"></div>
        </div>
        <p class="hint">Tuning only moves a weight, and a rule with a real negative edge still drags
          at a small one. This stops it voting entirely, then releases it to earn its place back
          rather than writing it off. Judged on expectancy, never win rate.
          <?php $qList = array_keys((array)json_decode(Database::setting('quarantined_rules', '[]'), true));
                echo $qList ? '<strong>Benched now: ' . sma_e(implode(', ', $qList)) . '.</strong>'
                            : 'Nothing benched right now.'; ?></p>
      </div>
    </div>
      </div>
    </details>
    <details class="acc">
      <summary>Market inputs</summary>
      <div class="acc-body">
        <p class="hint">Extra readings folded into the score - funding, sentiment, order flow, breadth - and the two chart panels that show them.</p>
    <div class="row2">
      <div><label>Funding-rate contrarian input (free Binance futures data)</label>
        <select name="funding_enabled">
          <option value="1" <?= Database::setting('funding_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled — fade crowded funding extremes</option>
          <option value="0" <?= Database::setting('funding_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <label>Extreme threshold in % per 8h (default 0.05)</label>
        <input type="number" name="funding_extreme_pct" step="0.01" value="<?= $s('funding_extreme_pct', '0.05') ?>"></div>
      <div><label>Fear &amp; Greed index input (free, alternative.me)</label>
        <select name="fng_enabled">
          <option value="1" <?= Database::setting('fng_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled — contrarian votes at extremes (&le;20 / &ge;80)</option>
          <option value="0" <?= Database::setting('fng_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <p class="hint">Two rules, one switch: the level at its extremes, and the three-day
          direction. 25 on the way up from 12 is capitulation being bought; 25 on the way down from
          45 is fear still arriving — the level alone reads those identically.
          <?php // Read from cache only. Fetching the index while rendering a
                // settings form would make this page as slow as the slowest
                // external endpoint, and cron already keeps the value warm.
                $fg = \SignalMasterAi\Cache::get('fng');
                $fgd = \SignalMasterAi\Cache::get('fngtrend');
                echo $fg === null ? 'No reading cached yet - cron fills it on the next run.'
                   : 'Last reading <strong>' . (int)$fg . '</strong>'
                     . ($fgd === null ? '' : sprintf(' (%+d over three days)', (int)$fgd)) . '.'; ?></p>
      </div>
    </div>
    <?php // The two chart-page display switches that sat here have moved to
          // Site > Chart page, with the other per-page display settings. They
          // decide what a READER sees, not what the engine scores, and filing
          // them among the data feeds meant an operator looking for "turn the
          // context panel off" searched the Site tab and found nothing. ?>
    <div class="row2">
      <div><label>Taker flow — buy/sell ratio that counts as aggressive</label>
        <select name="taker_enabled" style="margin-bottom:6px">
          <option value="1" <?= Database::setting('taker_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= Database::setting('taker_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <?php // step="any": with a min of 1.01 and a step of 0.05 the browser
              // treats the 1.25 default as off-grid, refuses to submit, and -
              // because this card sits in a hidden tab - fails silently. One
              // bad step attribute takes the whole settings page down with it. ?>
        <input type="number" name="taker_extreme" step="any" min="1.01" max="3"
               value="<?= $s('taker_extreme', '1.25') ?>">
        <p class="hint">Positioning says what people are holding; this says who is paying up right
          now. 1.25 means aggressive buyers are crossing 25% more volume than sellers. Read as
          confirmation, never as a call on its own — aggressive buying into a falling chart is a
          squeeze, not a trend. Free Binance futures data; spot-only coins simply skip it.</p>
      </div>
      <div><label>Market breadth — share of coins above their own 20-bar average</label>
        <select name="breadth_enabled" style="margin-bottom:6px">
          <option value="1" <?= Database::setting('breadth_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled — computed locally, no API calls</option>
          <option value="0" <?= Database::setting('breadth_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <div class="row2">
          <div><label>Bullish above (%)</label>
            <input type="number" name="breadth_bull" min="50" max="95" value="<?= $s('breadth_bull', '65') ?>"></div>
          <div><label>Bearish below (%)</label>
            <input type="number" name="breadth_bear" min="5" max="50" value="<?= $s('breadth_bear', '35') ?>"></div>
        </div>
        <p class="hint">One coin rallying while the board bleeds is a different market from one where
          three quarters of it is green, and no single-pair indicator can tell them apart. Read from
          candles already on disk, so it costs nothing, and it stays silent under ten readable pairs
          rather than reporting noise as a statistic.</p>
      </div>
      </div>
      <div class="row2">
      <div><label>Follow Bitcoin only where the coin actually follows Bitcoin</label>
        <select name="btc_corr_enabled" style="margin-bottom:6px">
          <option value="1" <?= Database::setting('btc_corr_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled &mdash; weight the Bitcoin rule by measured correlation</option>
          <option value="0" <?= Database::setting('btc_corr_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled &mdash; apply it equally to every coin</option>
        </select>
        <div class="row2">
          <div><label>Ignore Bitcoin below this correlation (0 - 0.9)</label>
            <input type="number" name="btc_corr_min" step="0.05" min="0" max="0.9"
                   value="<?= sma_e(Database::setting('btc_corr_min', '0.2')) ?>"></div>
          <div><label>Candles measured over (30 - 500)</label>
            <input type="number" name="btc_corr_bars" min="30" max="500"
                   value="<?= (int)Database::setting('btc_corr_bars', '120') ?>"></div>
        </div>
        <p class="hint">The Bitcoin-regime rule used to fire for every crypto that was not itself
          Bitcoin, at full weight, on the assumption that &ldquo;altcoin&rdquo; means
          &ldquo;moves with BTC&rdquo;. Plenty do not: a gold-backed token like PAXG or XAUT tracks
          gold, a stablecoin pair tracks nothing, and a coin in its own news cycle can ignore the
          market for a week. Telling those &ldquo;the tide is with altcoin longs&rdquo; is not a weak
          signal, it is a wrong one pushing the score at full strength. The vote is now scaled by how
          closely this coin has actually moved with Bitcoin over the last <?= (int)Database::setting('btc_corr_bars', '120') ?>
          candles &mdash; full weight when it tracks BTC, none below the floor, and inverted for a
          coin that reliably moves the other way. Correlation is measured on returns, not prices, so
          two things that merely drift upward together are not mistaken for one following the other.</p>
      </div>
      </div>
      <?php // THREE FEED SWITCHES THAT HAD NO CONTROL.
            //
            // Taker flow, breadth, funding, Fear & Greed and BTC correlation
            // all had one; open interest, order-book imbalance and the session
            // filter did not, and they sit in the same card doing the same
            // kind of job. Every one is read by SignalEngine on every
            // analysis, so an operator could see five of eight decisions and
            // change five of eight. ?>
      <div class="row2">
        <div><label>Open-interest confirmation</label>
          <select name="oi_enabled">
            <option value="1" <?= Database::setting('oi_enabled', '1') === '1' ? 'selected' : '' ?>>On — a move backed by rising open interest counts for more</option>
            <option value="0" <?= Database::setting('oi_enabled', '1') === '0' ? 'selected' : '' ?>>Off</option>
          </select>
          <span class="hint">Needs the futures endpoint under Market data APIs. A feed that returns
            nothing leaves the rule silent rather than guessing.</span></div>
        <div><label>Order-book imbalance</label>
          <select name="book_enabled">
            <option value="1" <?= Database::setting('book_enabled', '1') === '1' ? 'selected' : '' ?>>On — resting bids against asks votes on direction</option>
            <option value="0" <?= Database::setting('book_enabled', '1') === '0' ? 'selected' : '' ?>>Off</option>
          </select>
          <span class="hint">The shallowest of the feeds: a book can be stacked deliberately, so it
            is one vote among many rather than a signal on its own.</span></div>
      </div>
      <div class="row2">
        <div><label>Session filter</label>
          <select name="session_filter_enabled">
            <option value="1" <?= Database::setting('session_filter_enabled', '0') === '1' ? 'selected' : '' ?>>On — hold back calls in the hours that have lost money here</option>
            <option value="0" <?= Database::setting('session_filter_enabled', '0') === '0' ? 'selected' : '' ?>>Off (default)</option>
          </select>
          <span class="hint">Measured from this install's own settled trades by hour of day. Off by
            default because it needs a real history before an hour's record means anything &mdash;
            on a young site it is fitting noise.</span></div>
        <div></div>
      </div>
      </div>
    </details>
    <p class="hint">Scores between the two thresholds produce NEUTRAL. Rule weights are tuned in the
      <a href="knowledge.php" style="color:var(--accent)">knowledge base</a>.</p>
  </details><?php endif; ?>

  <details class="form-card" data-tab="market" id="cron">
    <summary>Background worker (cron) &mdash; everything the scan does</summary>
    <?php // The install instructions and the settings, in one card.
          //
          // They were two cards in the same tab, four hundred lines apart,
          // both titled about cron: "Cron job - one line, and what it does
          // with it" and "Background worker (cron)". One explained, one
          // configured, and nothing said so - an operator setting up their
          // worker read the first, did what it said, and had no reason to
          // scroll past a Signal engine card to find the half that has the
          // controls. Two headings about one subject is the same failure as
          // two timeframe boxes: the reader has to work out which is the
          // real one, and there is no way to tell from the outside. ?>
    <p class="hint"><strong>Install this and nothing else.</strong> Run it as often as your host
      allows; every minute is best, because everything below is limited by how many runs it gets.</p>
    <pre class="hook" style="white-space:pre-wrap"><?= sma_e(
      '* * * * *  php ' . dirname(__DIR__) . '/cron.php') ?></pre>
    <p class="hint"><strong>By URL</strong>, if your host only offers a fetcher:</p>
    <pre class="hook" style="white-space:pre-wrap"><?php
      $cronBase = rtrim(Database::setting('site_url'), '/');
      $cronBase = $cronBase !== '' ? $cronBase : 'https://your-site';
      echo sma_e($cronBase . '/cron.php?token=' . Database::setting('cron_token'));
    ?></pre>

    <p class="hint" style="margin-top:14px"><strong>What one job does with each run.</strong> The
      frames do deserve different rates &mdash; a daily candle cannot change between two runs and a
      15m one is stale after ten minutes &mdash; so the job divides each run between them by how
      often each can actually produce something new, rather than giving them equal turns. This is
      the split, worked out from your own scan list:</p>
    <?php
      // Shown rather than described, because the ratio is the whole point and a
      // sentence about it is not checkable. Same arithmetic the job runs.
      $cronTfList = array_values(array_filter(array_map('trim',
          \SignalMasterAi\Visibility::siteTfs($config['market']['intervals']))));
      $cronBatch = max(1, min(5000, (int)Database::setting('fullscan_batch', '10')));
      $cronW = [];
      foreach ($cronTfList as $tfName) {
          $cronW[$tfName] = 60.0 / max(1, \SignalMasterAi\SignalEngine::tfMinutes($tfName));
      }
      $cronWSum = array_sum($cronW) ?: 1.0;
    ?>
    <div class="tbl-scroll">
    <table class="grid">
      <tr class="head"><th>Timeframe</th><th>Share of each run</th><th>Roughly</th></tr>
      <?php foreach ($cronTfList as $tfName): ?>
      <tr>
        <td><code><?= sma_e($tfName) ?></code></td>
        <td><?= number_format($cronW[$tfName] / $cronWSum * 100, 1) ?>%</td>
        <td class="muted"><?php
          $share = $cronW[$tfName] / $cronWSum * $cronBatch;
          echo $share >= 1
              ? number_format($share, 1) . ' coin' . ($share >= 1.95 ? 's' : '') . ' every run'
              : 'about 1 coin every ' . max(2, (int)round(1 / max(0.0001, $share))) . ' runs';
        ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p class="hint">Shares smaller than one coin are banked between runs rather than rounded away,
      so a daily frame advances steadily instead of never. Each frame keeps its own place in its own
      list, so none of them can push another past coins nobody has looked at.</p>
    <p class="hint"><strong>Watched pairs come first, and are never anyone else's job.</strong> A
      pair a member is watching is re-read when its own candle could have moved &mdash; about every
      third of a frame &mdash; by whichever run gets to it, oldest first. It is not tied to a
      timeframe group, because a pair that no job owns is a pair that never alerts.</p>

    <details class="acc">
      <summary>Splitting across more than one job</summary>
      <div class="acc-body">
        <p class="hint">Not needed, and worth understanding before you do it: a job restricted to
          some frames does not scan the others, so if the job that owned them stops, those frames go
          dark and nothing says so. One unrestricted job cannot fail that way. These exist for
          spreading load across machines, not for fixing rates.</p>
        <pre class="hook" style="white-space:pre-wrap"><?= sma_e(
          '*/5  * * * *  php ' . dirname(__DIR__) . '/cron.php --group=fast     # under 1h only
'
          . '*/30 * * * *  php ' . dirname(__DIR__) . '/cron.php --group=slow     # 1h and above
'
          . '*/5  * * * *  php ' . dirname(__DIR__) . '/cron.php --tfs=15m,1h    # exactly these') ?></pre>
        <p class="hint">Very long lists can split across machines instead: add
          <code>--slot=1 --slots=2</code> and <code>--slot=2 --slots=2</code> to two copies of the
          same job and they interleave the rotation between them. Each worker that runs appears on
          the <a href="index.php">dashboard</a> with its own measured interval and cycle time.</p>
        <p class="hint"><strong>On SQLite</strong>, concurrent jobs share one file. Writers wait for
          each other rather than failing, so this is safe &mdash; but MySQL handles real concurrency
          better, and past two or three jobs it is the right move.</p>
      </div>
    </details>
    <?php // One card for the whole of it.
          //
          // These settings were split between here and the market-data card
          // six hundred lines up, which is a problem of the same shape as two
          // pages disagreeing about the win rate: an operator setting up their
          // worker had to know that the refresh interval and the parallel
          // fetch limit governed how much of it finished, and nothing on
          // either card said so. Every knob that decides what one cron run
          // gets through is here, in the order the run uses them. ?>
    <p class="hint" style="margin-bottom:12px">Everything below decides what <strong>one run of
      <code>cron.php</code></strong> gets through. The command to paste into your host is on the
      <a href="index.php#cron">dashboard</a>, which also measures how long a full rotation is
      actually taking against the fastest timeframe you scan.</p>

    <p class="sub" style="margin-top:4px"><strong>1. What to scan</strong></p>
    <div class="row2">
      <?php
      // Default '1', and the wording no longer calls the other one "default".
      //
      // Set to 0 with nobody watching anything, eight cron runs produce zero
      // stored verdicts - measured, not assumed. The scan had nothing to scan,
      // because its whole work list was watched pairs plus recently viewed
      // ones, and a new site has neither. So the scanner is empty, the track
      // record never starts, and the operator sees "signals generated: 0" with
      // a worker reporting healthy runs. A site cannot ask people to watch
      // coins before it has anything to show them.
      //
      // The rotation is what produces signals; watching a pair only decides
      // who gets TOLD about them, and is a priority hint inside the same scan.
      $fsOn = Database::setting('fullscan_enabled', '1');
      ?>
      <div><label>What the background scan covers</label>
        <select name="fullscan_enabled">
          <option value="1" <?= $fsOn === '1' ? 'selected' : '' ?>>Every coin in the rotation (default)</option>
          <option value="0" <?= $fsOn === '0' ? 'selected' : '' ?>>Only pairs someone watches or has just viewed</option>
        </select>
        <?php if ($fsOn !== '1'): ?>
          <p class="hint" style="color:var(--warn-text)">With this off, a coin nobody
            watches is never analysed &mdash; so it cannot appear on the scanner, in the track record
            or in an alert. On a site with no watchers yet, that means <strong>nothing is scanned at
            all</strong>.</p>
        <?php else: ?>
          <p class="hint">Watched pairs are still read first and more often inside the same scan, so
            an alert is not held up behind the rotation. Watching a coin decides who is
            <em>told</em>; it is not what makes the signal.</p>
        <?php endif; ?></div>
      <?php // The scan's timeframe list moved to the Timeframes card. It sat
            // here, six hundred lines from the master list it narrows, and the
            // two were never on the screen together - so ticking a frame here
            // that was off up there did nothing, silently. ?>
      <div><strong>Timeframes to scan</strong>
        <p class="hint" style="margin:6px 0 0">Currently
          <strong><?= sma_e(implode(', ', \SignalMasterAi\Visibility::siteTfs($config['market']['intervals']))) ?></strong>
          &mdash; the timeframes the site runs, because those are the same thing. Set them under
          <a href="#timeframes"><strong>Timeframes</strong></a>, above on this tab.</p></div>
    </div>
    <?php
    // How often cron actually runs, measured rather than asked for. The
    // schedule lives in a hosting panel this app cannot see, and a number
    // typed here goes stale the moment it is changed there - so cron.php
    // records the gap between its own runs and that is what is used. The
    // field below stays as an override for an install that has not run twice
    // yet, or one whose schedule is about to change.
    $seenSec = (int)Database::setting('cron_interval_seen', '0');
    $overrideMin = (int)Database::setting('cron_interval_min', '0');
    $everyN = $overrideMin > 0 ? $overrideMin : ($seenSec > 0 ? max(1, (int)round($seenSec / 60)) : 10);
    ?>
    <p class="sub" style="margin-top:18px"><strong>2. How long one run may take</strong>
      <span class="hint" style="display:block;font-weight:400">A run stops on the clock, not on the
        batch size, so the budget is what actually decides how many coins it reaches. Runs never
        overlap &mdash; a run still going when the next one fires makes the new one stand down, so
        firing cron every minute is safe.</span></p>
    <div class="row2">
      <div><label>Coins per cron run</label>
        <input type="number" name="fullscan_batch" min="1" value="<?= $s('fullscan_batch', '10') ?>">
        <p class="hint">Set whatever your host can take &mdash; there is no cap. As a starting point:
          <strong>10&ndash;25</strong> on cheap shared hosting, <strong>50&ndash;150</strong> on a decent
          shared plan, <strong>300+</strong> on a VPS. You cannot break a run by asking for too much:
          the scan stops on the clock and the next run resumes at the exact coin it reached, so a batch
          that is too big just means the cycle takes an extra run.</p></div>
      <div><label>Drop a coin from the rotation after this many failed runs (0 = never)</label>
        <input type="number" name="scan_autodisable_fails" min="0"
               value="<?= $s('scan_autodisable_fails', '3') ?>">
        <p class="hint">An imported watchlist rots: coins get delisted and the exchange answers
          HTTP 400 for them forever, one wasted slot per cycle each. After this many runs in a row a
          coin takes itself out of the rotation and the reason is shown on the
          <a href="symbols.php" style="color:var(--accent)">Coins</a> page, where you can put it back.
          An exchange outage cannot empty your list: if most of a run fails, nothing is counted, and
          one clean read on any timeframe clears a coin's record.</p></div>
      <div></div>
    </div>
    <div class="row2">
      <div><label>Seconds a run may spend scanning (0 = work it out)</label>
        <input type="number" name="fullscan_max_seconds" min="0"
               value="<?= $s('fullscan_max_seconds', '0') ?>">
        <p class="hint">Left at 0 this is 80% of your host's <code>max_execution_time</code>
          (<?= (int)ini_get('max_execution_time') > 0 ? sma_e((string)(int)ini_get('max_execution_time')) . 's here'
              : 'unlimited here, so 50s' ?>). Watched pairs and the default chart are analysed first
          and are never cut short &mdash; only the background rotation yields to the clock.</p></div>
      <div><label>Seconds one news refresh may spend fetching (0 = unlimited)</label>
        <input type="number" name="news_fetch_max_seconds" min="0" max="300"
               value="<?= $s('news_fetch_max_seconds', '20') ?>">
        <p class="hint">Every enabled source in <a href="news.php" style="color:var(--accent)">News</a>
          gets its own 12s timeout, tried one after another with no shared cap before this setting -
          a handful of slow or unreachable feeds could burn a minute or more of a single cron run,
          <strong>before the scan above even starts counting its own budget</strong>. A source not
          reached within this budget is simply due again next time; nothing is lost.</p></div>
    </div>
    <div class="row2">
      <div><label>How often your host runs cron.php</label>
        <?php if ($seenSec > 0): ?>
          <p class="hint" style="margin-top:0">Measured from the last runs of cron.php:
            <strong>every <?= sma_e((string)max(1, (int)round($seenSec / 60))) ?> min</strong>.
            Nothing to set &mdash; it follows your hosting panel on its own.</p>
        <?php else: ?>
          <p class="hint" style="margin-top:0">Not measured yet &mdash; it fills in by itself once
            cron.php has run twice.</p>
        <?php endif; ?>
        <input type="number" name="cron_interval_min" min="0"
               value="<?= $s('cron_interval_min', '0') ?>"
               placeholder="0 = use the measured value">
        <p class="hint">Only used for the cycle estimate below. Leave it at 0 unless you want to
          plan against a schedule you have not set up yet.</p></div>
      <div></div>
    </div>
    <p class="sub" style="margin-top:18px"><strong>What the run writes down</strong></p>
    <div class="row2">
      <div><label>Record NEUTRAL verdicts in the signal history</label>
        <select name="store_neutral">
          <option value="0" <?= Database::setting('store_neutral', '0') === '0' ? 'selected' : '' ?>>No — only BUY and SELL are stored (default)</option>
          <option value="1" <?= Database::setting('store_neutral', '0') === '1' ? 'selected' : '' ?>>Yes — store every quiet period too</option>
        </select>
        <p class="hint">A NEUTRAL is the engine saying "no trade here". It is always kept as the
          pair's current verdict, so the scanner still shows the coin as quiet and alerts still fire
          when it wakes up — this only decides whether it also gets a row in the history that
          feeds the track record and the admin signal list. Storing them buried the actionable
          calls under many times their number in rows nobody can act on.</p></div>
      <div></div>
    </div>

    <?php
    // Coins in the rotation. "Enabled" says the coin exists on the site;
    // this says the background scan spends part of every cycle on it. They
    // were the same switch, so the only way to keep a coin listed but out of
    // the rotation was to delete it - and its history with it.
    $scanRows = $pdo->query('SELECT symbol, label, scan FROM symbols WHERE enabled = 1 ORDER BY symbol')->fetchAll();
    $scanOn = array_values(array_filter($scanRows, fn($r) => (int)$r['scan'] === 1));
    // Read from the setting rather than from a variable the picker happened to
    // leave behind: the picker now lives in the Timeframes card, and a local
    // that depends on another card having rendered first is a fatal waiting
    // for someone to reorder the page.
    $tfCount = max(1, count(\SignalMasterAi\Visibility::siteTfs($config['market']['intervals'])));
    $jobs = count($scanOn) * $tfCount;
    $batchN = max(1, (int)Database::setting('fullscan_batch', '10'));
    $runs = $jobs > 0 ? (int)ceil($jobs / $batchN) : 0;
    $cycleMin = $runs * $everyN;
    ?>
    <?php // An orphaned "Coins in the scan rotation" label sat here, left
          // behind when the coin picker moved down to step 4. A label with no
          // control under it reads as a control that failed to render. ?>
    <p class="sub" style="margin-top:18px"><strong>3. How the data is fetched</strong>
      <span class="hint" style="display:block;font-weight:400">These two decide how much work a run
        has to do, and they are usually worth more than the budget above. A bar cannot change more
        often than it closes, so refreshing a daily series every five minutes is 287 requests a day
        that cannot return anything new &mdash; and fetching in parallel means the run waits once
        for many coins instead of once per coin.</span></p>
    <div class="row2">
      <div><label>Cache TTL in seconds (30 - 86400)</label><input type="number" name="cache_ttl" value="<?= $s('cache_ttl', '300') ?>">
        <span class="hint">The floor for every timeframe. Fast frames use exactly this.</span></div>
      <div><label>Slow-timeframe refresh divisor (1 - 60)</label>
        <input type="number" name="candle_ttl_divisor" min="1" max="60" value="<?= $s('candle_ttl_divisor', '4') ?>">
        <span class="hint">A bar cannot change more often than it closes, so each timeframe refreshes
          at <strong>bar length &divide; this</strong>, never below the TTL above. At 4: 1h refreshes
          every 15 min, 4h hourly, 1d every 6 hours &mdash; instead of all of them every 5 minutes.
          <strong>This is usually the single biggest cost in the scan</strong>, because multi-timeframe
          confluence pulls 1h, 4h and 1d for every coin it looks at. Raise it for fresher slow frames
          and a slower rotation; lower it to scan more coins per run. 1 disables the scaling and
          restores the old behaviour.</span></div>
    </div>
    <div class="row2">
      <div><label>Parallel fetches (1 - 50)</label>
        <input type="number" name="fetch_concurrency" min="1" max="50" value="<?= $s('fetch_concurrency', '8') ?>">
        <span class="hint">How many candle requests the scan keeps open at once. Almost none of a
          scan is computing &mdash; it is waiting on the exchange, and a server can wait on eight
          sockets as easily as on one, so eight pairs take about the time one used to.
          <strong>Raise it to scan faster; lower it if your exchange rate-limits you</strong>
          (HTTP 429 in the error log is the sign). 1 restores the old one-at-a-time behaviour.</span></div>
      <div><label>Skip coins whose candle has not closed</label>
        <select name="fullscan_bar_gate">
          <option value="1" <?= Database::setting('fullscan_bar_gate', '1') !== '0' ? 'selected' : '' ?>>Skip them (recommended)</option>
          <option value="0" <?= Database::setting('fullscan_bar_gate', '1') === '0' ? 'selected' : '' ?>>Re-scan anyway</option>
        </select>
        <span class="hint">The rotation walks a ring and re-reads whatever it lands on. With cron
          every minute and a lap of roughly an hour, a <strong>4h</strong> coin is visited about four
          times per candle and a <strong>daily</strong> one about twenty-four &mdash; and every visit
          inside the same bar recomputes an identical verdict from identical closed candles.
          Skipping them hands the slot to a coin that does have new data, so the rotation covers
          more of your list in the same budget. Watched pairs are unaffected: they are paced at a
          third of their frame so a live setup is still looked at two or three times inside its own
          candle. The cron report counts what it skipped, under
          <code>skipped_same_bar</code>.</span></div>
      <div><label>Max pairs pre-fetched per run (0 = off)</label>
        <input type="number" name="fetch_prefetch_max" min="0" max="1000" value="<?= $s('fetch_prefetch_max', '120') ?>">
        <span class="hint">The cap on how many requests one cron run may issue up front. A pair
          fetched but not reached this run is not wasted &mdash; its candles are cached and the next
          run gets it free. Bound it so a single run cannot spend too long opening connections.
          0 turns pre-fetching off and leaves every fetch to happen one at a time inside the
          loop.</span></div>
    </div>

    <p class="sub" style="margin-top:18px"><strong>4. Which coins are in the rotation</strong></p>
    <input type="hidden" name="scan_list" value="1">
    <?php // Picking sixty coins one box at a time is not a control panel. The
          // buttons act only on what the search box is currently showing, so
          // "USDT" then "Select all" is a bulk action, not an all-or-nothing
          // one. Everything here is display-side: the boxes are still what
          // gets posted. ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 8px">
      <input type="search" id="scanFilter" placeholder="Filter coins&hellip;"
             autocomplete="off" autocapitalize="off" spellcheck="false"
             style="flex:1 1 160px;min-width:0;padding:6px 10px">
      <button type="button" class="btn small gray" data-scanpick="all">Select all</button>
      <button type="button" class="btn small gray" data-scanpick="none">Select none</button>
      <button type="button" class="btn small gray" data-scanpick="invert">Invert</button>
      <span class="hint" id="scanCount" style="margin:0"></span>
    </div>
    <div id="scanGrid"
         style="display:flex;flex-wrap:wrap;gap:4px 14px;max-height:260px;overflow:auto;
                border:1px solid var(--border);border-radius:8px;padding:10px;margin-bottom:8px">
      <?php if (!$scanRows): ?>
        <span class="hint">No enabled coins yet — add them under
          <a href="symbols.php" style="color:var(--accent)">Coins</a>.</span>
      <?php endif; ?>
      <?php foreach ($scanRows as $sr): ?>
        <label data-coin="<?= sma_e(strtolower($sr['symbol'] . ' ' . $sr['label'])) ?>"
               style="display:inline-flex;align-items:center;gap:6px;color:var(--text);margin:3px 0">
          <input type="checkbox" name="scan_symbols[]" value="<?= sma_e($sr['symbol']) ?>"
                 <?= (int)$sr['scan'] === 1 ? 'checked' : '' ?>>
          <?= sma_e($sr['symbol']) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <script>
    (function () {
      var grid = document.getElementById('scanGrid');
      if (!grid) { return; }
      var filter = document.getElementById('scanFilter');
      var count = document.getElementById('scanCount');
      var labels = Array.prototype.slice.call(grid.querySelectorAll('label[data-coin]'));
      function visible() {
        return labels.filter(function (l) { return l.style.display !== 'none'; });
      }
      function tally() {
        var on = labels.filter(function (l) { return l.querySelector('input').checked; }).length;
        count.textContent = on + ' of ' + labels.length + ' coin(s) selected';
      }
      if (filter) {
        filter.addEventListener('input', function () {
          var q = filter.value.trim().toLowerCase();
          labels.forEach(function (l) {
            l.style.display = (!q || l.dataset.coin.indexOf(q) !== -1) ? '' : 'none';
          });
        });
      }
      document.querySelectorAll('[data-scanpick]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var mode = btn.dataset.scanpick;
          visible().forEach(function (l) {
            var box = l.querySelector('input');
            box.checked = mode === 'all' ? true : (mode === 'none' ? false : !box.checked);
          });
          tally();
        });
      });
      grid.addEventListener('change', tally);
      tally();
    })();
    </script>
    <p class="hint">
      <?php if ($jobs > 0): ?>
        <strong><?= count($scanOn) ?></strong> coin(s) &times; <strong><?= $tfCount ?></strong>
        timeframe(s) = <strong><?= $jobs ?></strong> jobs per cycle. At <?= $batchN ?> per run and a
        cron every <?= $everyN ?> min<?= $overrideMin > 0 ? '' : ($seenSec > 0 ? ' (measured)' : ' (assumed)') ?>,
        the whole list is covered in <strong><?= $runs ?></strong> run(s)
        &mdash; about <strong><?= $cycleMin ?> minutes</strong>
        <?= $cycleMin >= 120 ? '(' . round($cycleMin / 60, 1) . ' hours)' : '' ?> per full pass.
        A pair on a 15m chart that is only looked at every <?= $cycleMin ?> minutes will miss setups;
        either untick coins or raise the batch until the cycle is shorter than the timeframe.
      <?php else: ?>
        Nothing is in the rotation, so the background scan has nothing to do.
      <?php endif; ?>
    </p>
    <p class="hint">Each cron run analyses the next batch and remembers where it stopped, so the whole
      list is covered in a few runs and then repeats. Signals found this way fill the public track
      record and signal history for every coin - alerts still go only to members watching a pair.</p>
  </details>

  <?php // Delivery, in one place.
        //
        // These four lived inside "Signal engine thresholds", between the BUY
        // score and the ADX filter, because that is where they were added.
        // Nothing about who gets told, how often, or in how many messages is a
        // threshold, and an operator hunting for "why did my member get twelve
        // emails" had no reason to open a card about scores. ?>
  <?php if (\SignalMasterAi\Master::master('alerts')): ?><details class="form-card" data-tab="alerts" open>
    <summary>Who gets alerted, and how often</summary>
<div class="row2">
      <div><label>Browser signal alerts (bullish/bearish notifications)</label>
        <select name="alerts_enabled">
          <option value="1" <?= Database::setting('alerts_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled (users pick coins &amp; timeframes)</option>
          <option value="0" <?= Database::setting('alerts_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select></div>
      <div><label>Minimum setup grade for push/email alerts</label>
        <select name="alert_min_grade">
          <option value="any" <?= Database::setting('alert_min_grade', 'any') === 'any' ? 'selected' : '' ?>>Any signal</option>
          <option value="B" <?= Database::setting('alert_min_grade') === 'B' ? 'selected' : '' ?>>Grade B or better</option>
          <option value="A" <?= Database::setting('alert_min_grade') === 'A' ? 'selected' : '' ?>>Grade A and A+ only (fewer, stronger)</option>
          <option value="A+" <?= Database::setting('alert_min_grade') === 'A+' ? 'selected' : '' ?>>Grade A+ only (strictest - 4+ agreeing categories, higher timeframe agrees, trend confirmed)</option>
        </select>
        <span class="hint">The site-wide floor, and the only rule by default. A signal blocked here
          never reaches a member to be filtered, so nobody can ever receive something below this
          &mdash; whatever they set on their own account.
          <?php $pf = \SignalMasterAi\Publish::floor(); ?>
          <?php if ($pf !== '' && \SignalMasterAi\Publish::RANK[$pf] > (\SignalMasterAi\Publish::RANK[Database::setting('alert_min_grade', 'any')] ?? -1)): ?>
            <br><strong>Currently overridden by the publish floor below:</strong> <a href="#signals">What
            the site publishes</a> is set to <?= sma_e($pf) ?> and above, so alerts actually go out at
            that floor, not this one &mdash; a signal nobody can find on the site is never worth a
            push or an email. Raise this to match if you want the two to read the same here.
          <?php else: ?>
            Also never looser than <a href="#signals">what the site publishes</a>, whichever of the
            two is stricter applies.
          <?php endif; ?></span></div>
      <div><label>May members run their own thresholds?</label>
        <select name="member_engine_profile">
          <option value="0" <?= Database::setting('member_engine_profile', '0') === '0' ? 'selected' : '' ?>>No &mdash; everyone sees the site's engine</option>
          <option value="1" <?= Database::setting('member_engine_profile', '0') === '1' ? 'selected' : '' ?>>Yes &mdash; Conservative / Balanced / Aggressive on their account</option>
        </select>
        <span class="hint">This is the one that changes what a member <em>sees</em>, and it can
          loosen as well as tighten: on Aggressive the buy threshold drops to 2.0 and one agreeing
          category is enough, so their chart can read SELL where your engine reads NEUTRAL. It never
          touches alerts (those are dispatched from the shared analysis), and a personalised reading
          is never stored, so the track record and every published figure stay yours alone. Off by
          default &mdash; if you have tuned your engine, a member judging the site on a looser one is
          usually not what you want.</span></div>
    </div>
    <div class="row2">
      <div><label>May members tighten it further?</label>
        <select name="member_grade_filter">
          <option value="0" <?= Database::setting('member_grade_filter', '0') === '0' ? 'selected' : '' ?>>No &mdash; you decide, for everyone</option>
          <option value="1" <?= Database::setting('member_grade_filter', '0') === '1' ? 'selected' : '' ?>>Yes &mdash; they may ask for fewer, stronger signals</option>
        </select>
        <span class="hint">Only ever <em>stricter</em>: a member can ask for A+ when your floor is B,
          never the reverse. Left off, the grade box on their account shows your setting and is not
          editable. Their <em>direction</em>, quiet hours and daily cap are personal convenience
          rather than signal quality, so those stay theirs either way.
          <?php if (Database::setting('member_grade_filter', '0') === '1'
                   && Database::setting('alert_min_grade', 'any') === 'A+'): ?>
            <br><strong>Your floor is already A+</strong>, the highest grade, so there is nothing
            left for a member to tighten &mdash; the box will not appear.
          <?php endif; ?></span></div>
    </div>
    <div class="row2">
      <div><label>When someone adds a coin that already has a live call</label>
        <select name="alert_on_subscribe">
          <option value="1" <?= Database::setting('alert_on_subscribe', '1') === '1' ? 'selected' : '' ?>>Send it once, straight away</option>
          <option value="0" <?= Database::setting('alert_on_subscribe', '1') === '0' ? 'selected' : '' ?>>Wait for the next change</option>
        </select>
        <span class="hint">Alerts fire on a <em>change</em> of verdict, so a coin sitting on a standing
          BUY has nothing to change to. On "wait", a member who adds twenty coins gets nothing until
          each one happens to turn &mdash; days, on the higher timeframes &mdash; and reasonably
          concludes the alerts are broken. On "send", they get the calls that are live when they
          subscribe, once, in the same single batched email; a call already past its own expiry is
          never sent, because a plan the reader cannot act on is worse than silence. Either way each
          call is recorded the moment it is sent, so nothing is ever sent twice.</span></div>
      <div><label>Treat an identical plan as the same call for at least (hours)</label>
        <input type="number" name="dupe_window_hours" min="0" max="720"
               value="<?= (int)Database::setting('dupe_window_hours', '24') ?>">
        <span class="hint">Same direction, same entry, same stop, same targets on the same pair and
          timeframe is the same call. A trade that closes and sets up again at the identical level an
          hour later gets published, verified and mailed a second time &mdash; the reader is sent the
          same trade twice before lunch and stops reading either. Within the window it is not
          republished at all: no ledger row, no scanner entry, no alert.
          <strong>&ldquo;At least&rdquo; is the important word</strong>: the real window is this or
          the setup's own lifetime on that timeframe, whichever is longer, because a flat number of
          hours means ninety-six candles on a 15m chart and one candle on a daily &mdash; strict
          where it hardly matters and switched off in all but name where it matters most. It stays a
          window rather than a permanent block because price genuinely does return to a level, and
          long enough later that is a new trade. 0 switches the guard off entirely.
          <?php
            // Whether it has ever actually stopped anything. A guard that works
            // silently and a guard that is broken look identical from here, and
            // "is this even on?" is the only question an operator has about it.
            $dupN = (int)Database::setting('dupe_blocked_total', '0');
            $dupAt = (int)Database::setting('dupe_blocked_last', '0');
            if ($dupN > 0) {
                echo '<br><strong>Suppressed ' . number_format($dupN) . ' restatement'
                   . ($dupN === 1 ? '' : 's') . ' so far</strong>'
                   . ($dupAt > 0 ? ', most recently '
                       . sma_e(\SignalMasterAi\View::ago(max(0, time() - $dupAt))) . '.' : '.');
            } else {
                echo '<br>Nothing suppressed yet &mdash; either no plan has repeated, or the guard is off.';
            }
          ?></span></div>
      <div><label>A new plan counts as a different trade once price has moved this much of the risk</label>
        <input type="number" name="reissue_min_risk" step="0.1" min="0.1" max="5"
               value="<?= sma_e(Database::setting('reissue_min_risk', '1.0')) ?>">
        <span class="hint">The other half of the rule, for a call that is <em>still open</em>. While a
          BUY is live, another BUY on the same pair is the same idea restated &mdash; unless the new
          entry is this many times the open plan's own risk away from it, which puts it at a
          different place on the chart and makes it a genuinely different trade. 1.0 means a full
          stop-distance. Lower publishes more second calls; higher publishes fewer. This had no
          control at all until now: it was a number in the code.</span></div>
      <div><label>Full plans per batched email</label>
        <input type="number" name="alert_email_max_detail" min="1" max="200"
               value="<?= (int)Database::setting('alert_email_max_detail', '25') ?>">
        <span class="hint">When many watched pairs flip in one run they go out as a single email. Each
          full plan is about 1.4KB, and Gmail stops rendering a message at roughly 102KB &mdash; around
          sixty signals &mdash; showing "[Message clipped]" and hiding the rest behind a link, with
          nothing to warn you. Past this number the remaining pairs are still listed by name and
          direction, just without their entry/stop/target table, which is on the dashboard anyway. 25
          leaves plenty of headroom.</span></div>
    </div>
    <?php // The site-wide publish floor used to be the row under this one, and
          // it does not belong to alerts at all: it decides what the scanner,
          // the coin pages, the track record and the API show. Filed here it
          // read as a third alert control, so an operator who had set it to A
          // months earlier had no reason to look under Alerts when the scanner
          // showed one row out of forty-seven. It is its own card under
          // Signals, named for what it does. ?>
    <p class="hint" style="margin-top:14px">The floor above is about
      <em>interrupting</em> someone. What the site <strong>publishes at all</strong> is a separate
      decision, currently <strong><?= sma_e(Database::setting('show_min_grade', 'any') === 'any'
        ? 'every signal' : 'grade ' . Database::setting('show_min_grade', 'any') . ' and above') ?></strong>
      &mdash; set it under <a href="#publish"><strong>What the site publishes</strong></a>, on the
      Signals tab. An alert floor below the publish floor cannot fire: nothing below the publish
      floor exists to alert on.</p>
<div class="row2">
      <div><label>When several watched pairs flip in the same run</label>
        <select name="alert_email_batch">
          <option value="1" <?= Database::setting('alert_email_batch', '1') === '1' ? 'selected' : '' ?>>One email listing them all</option>
          <option value="0" <?= Database::setting('alert_email_batch', '1') === '0' ? 'selected' : '' ?>>A separate email for each</option>
        </select>
        <p class="hint">A member watching all their coins on three timeframes can have a dozen
          pairs flip in one run. A dozen separate emails inside a minute reads as spam whatever is
          in them, gets the sending domain filtered, and buries the one setup they wanted. Batched,
          that run is one message with every coin and its plan. A single flip always sends the full
          single-signal email either way. Telegram follows the same setting.</p></div>
      <div><label>Hold new alerts for (minutes, 0 = send every run)</label>
        <input type="number" name="alert_bundle_min" min="0" max="720"
               value="<?= $s('alert_bundle_min', '0') ?>">
        <p class="hint">Coins do not flip at the same minute. Batching already collapses whatever
          turns during one cron run, but a coin at 12:00 and another at 12:10 is still two emails on
          a ten-minute cron. A window holds each new signal until the oldest has waited this long,
          then sends everything together &mdash; the only way "all my coins in one email" is true
          across a morning. The cost is delay: a 30-minute window means a setup can reach the member
          up to 30 minutes after it fired, which suits swing timeframes and does not suit 5m. 0
          keeps the current behaviour. Anything still waiting after 24 hours is dropped rather than
          delivered as a stale plan.</p></div>
    </div>
<div class="row2">
      <div><label>Telegram delivery</label>
        <select name="alert_tg_batch">
          <option value="1" <?= Database::setting('alert_tg_batch', '1') === '1' ? 'selected' : '' ?>>One message listing them all</option>
          <option value="0" <?= Database::setting('alert_tg_batch', '1') === '0' ? 'selected' : '' ?>>A separate message for each</option>
        </select>
        <p class="hint">Same trade-off, and Telegram rate-limits a burst of messages to one chat -
          which silently drops some of them.</p></div>
    </div>
  </details><?php endif; ?>

  <?php if (\SignalMasterAi\Master::master('alerts')): ?><details class="form-card" data-tab="alerts">
    <summary>Watchlist size &amp; bulk "watch all coins"</summary>
    <?php
      $wlCoins = (int)$pdo->query('SELECT COUNT(*) FROM symbols WHERE enabled = 1 AND scan = 1')->fetchColumn();
      $wlTfs = max(1, count((array)($config['market']['intervals'] ?? [])));
    ?>
    <?php // The three watchlist caps moved to Members > How much each tier
          // gets, which is now the one table for every tiered number. Left
          // here they were a SECOND control on the same three settings in the
          // same form - the browser posts only the last, so whichever one an
          // operator happened to be looking at might have done nothing. That
          // is the exact bug this panel keeps being audited for; leaving it in
          // while adding the table would have been shipping it knowingly. ?>
    <p class="hint" style="margin-top:0">A pair is one coin on one timeframe, so a member watching
      10 coins on 3 timeframes is using 30 &mdash; the number people expect to set is the coin
      count, and it is not the same thing. You have <strong><?= $wlCoins ?></strong> scanned coins;
      watching every one on every timeframe would be
      <strong><?= $wlCoins * $wlTfs ?></strong> pairs.</p>
    <p class="hint">Currently
      <?php $wlTierFirst = array_key_first(\SignalMasterAi\Limits::TIERS);
      foreach (\SignalMasterAi\Limits::TIERS as $tk => $tl): ?><?= $tk === $wlTierFirst ? '' : ', ' ?><strong><?= sma_e($tl) ?></strong>
        <?= sma_e(\SignalMasterAi\Limits::label(\SignalMasterAi\Limits::forTier('watch', $tk))) ?><?php
        endforeach; ?>. Set them under
      <a href="#limits"><strong>How much each tier gets</strong></a>, on the Members tab.</p>
    <p class="hint">The limit is enforced where the list is <em>saved</em>, so lowering it does not
      delete anything anyone already has &mdash; existing lists keep working and are trimmed to the
      new figure the next time that member edits theirs. Every watched pair is kept analysed by cron
      whether or not anyone has the site open, so these numbers set the size of the background job.</p>

    <h4 style="margin:18px 0 2px">One-tap "watch the coins that are working"</h4>
    <p class="hint" style="margin-top:2px">Two buttons in the alerts box that watch your best coins
      by <strong>win rate</strong> and by <strong>total return</strong>, ranked from the verified
      track record. They disagree more often than people expect &mdash; a coin can win 70% of the
      time and still lose money if the losses are bigger than the wins &mdash; so both are offered
      and the member picks.</p>
    <div class="row2">
      <div><label>Offer these buttons</label>
        <select name="top_watch_enabled">
          <option value="1" <?= Database::setting('top_watch_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= Database::setting('top_watch_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select></div>
      <?php // The tier select that stood here has gone. It was a SECOND control
            // on top_watch_tier, and the gate table further down the page is
            // the first - two selects with one name in one form, where the
            // browser posts only the last. So an operator who changed the tier
            // here saved the page and watched it snap back to whatever the
            // gate table was showing, with nothing to explain it. Who gets a
            // feature is decided in exactly one place. ?>
      <div><strong>Who gets the best-performing buttons</strong>
        <p class="hint" style="margin:6px 0 0"><?= sma_e(\SignalMasterAi\Gate::tierLabel('top_watch')) ?>.
          Set it with every other gate under
          <a href="#gates"><strong>What free and premium accounts get</strong></a>, on the Members
          tab.</p></div>
    </div>
    <div class="row2">
      <div><label>How many coins each button adds</label>
        <input type="number" name="top_watch_count" min="1" max="200" value="<?= $s('top_watch_count', '10') ?>">
        <p class="hint">Multiplied by the timeframes the member ticks, and still bounded by their
          watchlist limit above.</p></div>
      <div><label>Minimum settled trades to qualify</label>
        <input type="number" name="top_watch_min" min="5" max="1000" value="<?= $s('top_watch_min', '10') ?>">
        <p class="hint">This is the setting that decides whether the feature is honest. A coin with
          three settled trades and three wins is a 100% win rate that means nothing, and with no
          floor those coins take every top slot &mdash; you would be recommending precisely the
          coins there is least reason to trust. Raise it as your record grows.
          <?php
            $tw = \SignalMasterAi\TrackRecord::topCoins('winrate', 5,
                    max(5, (int)Database::setting('top_watch_min', '10')));
          ?>
          <?php if ($tw): ?>
            Right now that gives: <?php foreach ($tw as $i => $c): ?><?= $i ? ', ' : '' ?><strong><?= sma_e($c['symbol']) ?></strong>
              (<?= $c['winRate'] ?>% of <?= $c['settled'] ?>)<?php endforeach; ?>.
          <?php else: ?>
            <strong>Right now no coin qualifies</strong> &mdash; the buttons will say so rather than
            add nothing silently. They start working as trades settle.
          <?php endif; ?></p></div>
    </div>

    <h4 style="margin:18px 0 2px">One-tap "watch everything"</h4>
    <div class="row2">
      <div><label>One-tap watch-all button</label>
        <select name="bulk_watch_enabled">
          <option value="1" <?= Database::setting('bulk_watch_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= Database::setting('bulk_watch_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select></div>
      <div><strong>Who gets the watch-all button</strong>
        <p class="hint" style="margin:6px 0 0"><?= sma_e(\SignalMasterAi\Gate::tierLabel('bulk_watch')) ?>.
          Set it under <a href="#gates"><strong>What free and premium accounts get</strong></a>, on
          the Members tab.</p></div>
    </div>
    <div class="row2">
      <div><label>Max watched <em>pairs</em> for those users (0 = no limit)</label>
        <input type="number" name="bulk_watch_max" value="<?= $s('bulk_watch_max', '0') ?>" min="0">
        <p class="hint">Pairs, not coins &mdash; and the difference is the whole trap. "Watch all my
          coins" is every coin multiplied by every ticked timeframe, so at 60 a member ticking three
          frames gets twenty coins and a button that stops. For your
          <?= (int)$pdo->query('SELECT COUNT(*) FROM symbols WHERE enabled = 1')->fetchColumn() ?>
          enabled coins on three timeframes, covering everything needs
          <?= (int)$pdo->query('SELECT COUNT(*) FROM symbols WHERE enabled = 1')->fetchColumn() * 3 ?>.
          Set 0 for no limit. The cost is real but modest: watched pairs are kept analysed by cron
          whether or not anyone has the site open, so a large list makes each cycle longer &mdash;
          the same budget the scan rotation uses, under Market data.</p></div>
      <div></div>
    </div>
    <p class="hint">Adds a "Watch ALL my coins" button in the alerts box: one tap watches every
      coin the user can access, on the timeframes they tick. Users below the chosen tier do not get
      the button and see an upgrade hint instead &mdash; they can still watch coins one at a time, up
      to their own limit above.</p>
  </details><?php endif; ?>

  <?php if (\SignalMasterAi\Master::master('signals')): ?>
  <?php $showFloor = Database::setting('show_min_grade', 'any'); ?>
  <details class="form-card" data-tab="signals" id="publish" open>
    <summary>What the site publishes</summary>
    <p class="hint" style="margin-top:0">One decision, and it is the one that makes the numbers on
      this panel differ from the numbers on the site. Below the floor a setup is still analysed,
      stored, verified and learned from &mdash; the engine keeps improving on setups nobody was
      shown &mdash; it simply is not published anywhere: not the scanner, not the coin pages, not
      the track record, not the API, not an alert.</p>
    <div class="row2">
      <div><label>Minimum grade shown anywhere on the site</label>
        <select name="show_min_grade">
          <option value="any" <?= $showFloor === 'any' ? 'selected' : '' ?>>Show every signal</option>
          <option value="B" <?= $showFloor === 'B' ? 'selected' : '' ?>>Publish grade B and above</option>
          <option value="A" <?= $showFloor === 'A' ? 'selected' : '' ?>>Publish grade A and A+ only</option>
          <option value="A+" <?= $showFloor === 'A+' ? 'selected' : '' ?>>Publish grade A+ only</option>
        </select>
        <span class="hint">A coin whose only call is below the floor reads as "no published call"
          rather than as a weaker one. The alert floor, under
          <a href="#alerts">Alerts</a>, narrows this further &mdash; it cannot widen it.</span></div>
      <div>
        <?php
        // What the floor is costing right now, counted rather than described.
        //
        // The panel counts stored signals and the site counts published ones,
        // and an operator comparing "582 generated" here with one row on the
        // scanner had nothing on either page to connect them. The gap IS this
        // setting, so this setting is where the gap is stated.
        $pubTotal = (int)$pdo->query('SELECT COUNT(*) FROM signals')->fetchColumn();
        $pubShown = \SignalMasterAi\TrackRecord::publishedCount();
        ?>
        <?php if ($showFloor !== 'any'): ?>
          <p class="hint"><strong><?= number_format($pubShown) ?></strong> of
            <strong><?= number_format($pubTotal) ?></strong> stored signals are published at this
            floor. The rest are the ones the admin lists count and the site does not.</p>
          <p class="hint" style="color:var(--warn-text)">The track record moves with this
            setting. It counts published calls only, which is the honest number &mdash; it answers "how
            did the calls a visitor could see do" &mdash; but raising or lowering the floor rewrites the
            history shown on the performance page, so it is not a dial to try out casually on a live
            site.</p>
        <?php else: ?>
          <p class="hint">All <strong><?= number_format($pubTotal) ?></strong> stored signals are
            published. Leave this on "every signal" unless you deliberately want a quieter, higher
            conviction site &mdash; most operators want the alert floor under
            <a href="#alerts">Alerts</a>, not this one.</p>
        <?php endif; ?>
      </div>
    </div>
  </details><?php endif; ?>

  <?php if (\SignalMasterAi\Master::master('signals')): ?><details class="form-card" data-tab="signals">
    <summary>Trade levels (entry / stop loss / take profit)</summary>
    <div class="row2">
      <div><label>Show trade plan with signals</label>
        <select name="levels_enabled">
          <option value="1" <?= Database::setting('levels_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= Database::setting('levels_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select></div>
      <div><label>Stop loss (ATR &times;)</label><input type="number" step="0.1" name="sl_atr_mult" value="<?= $s('sl_atr_mult', '1.5') ?>">
        <span class="hint">Sets the whole ladder: every published target is exactly 1&times;, 2&times;
          and 3&times; this same distance, always. There is no separate TP1/TP2/TP3 distance to set -
          widen or tighten the stop and the targets move with it.</span></div>
    </div>
    <div class="row2">
      <div><label>Chase-guard distance (ATR &times;)</label>
        <input type="number" step="0.1" name="tp1_atr_mult" value="<?= $s('tp1_atr_mult', '1.0') ?>">
        <span class="hint">Not a price target - it only sets when the site warns "price already
          moved most of the way, this entry is stale" on a setup that triggered a bar or two ago.</span></div>
    </div>
    <div class="row2">
      <div><label>Liquidation clusters</label>
        <select name="liq_clusters_enabled">
          <option value="1" <?= Database::setting('liq_clusters_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= Database::setting('liq_clusters_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select></div>
      <div><label>Leverage tiers to project</label>
        <input type="text" name="liq_leverages" value="<?= $s('liq_leverages', '10,25,50,100') ?>"></div>
    </div>
    <div class="row2">
      <div><label>Cluster lookback (bars, 60 - 1000)</label>
        <input type="number" name="liq_lookback_bars" value="<?= $s('liq_lookback_bars', '240') ?>"></div>
      <div><label>Minimum cluster strength to move a stop (0 - 1)</label>
        <input type="number" step="0.05" min="0" max="1" name="liq_stop_min_weight" value="<?= $s('liq_stop_min_weight', '0.5') ?>"></div>
    </div>
    <p class="hint">Leveraged positions are force-closed a fixed distance from their entry, so busy
      candles seed liquidation prices at knowable levels; where many land together, a cascade is
      waiting. Levels price has already traded through are discarded, so only unspent fuel is
      counted. A stop resting just short of a cluster is moved past it, and the nearest cluster
      ahead of the trade is published as the magnet. Estimated from public candles and volume -
      an approximation of a paid heatmap feed, not a substitute for one.</p>
    <div class="row2">
      <div><label>Time stop: close after N candles without SL/TP1 (1 - 500)</label>
        <input type="number" name="time_stop_bars" value="<?= $s('time_stop_bars', '24') ?>"></div>
      <div><label>Move stop to break-even after TP1</label>
        <select name="breakeven_after_tp1">
          <option value="1" <?= Database::setting('breakeven_after_tp1', '1') === '1' ? 'selected' : '' ?>>Yes (recommended)</option>
          <option value="0" <?= Database::setting('breakeven_after_tp1', '1') === '0' ? 'selected' : '' ?>>No</option>
        </select></div>
    </div>
    <div class="row2">
      <div><label>Share of the position taken off at TP1 (%)</label>
        <input type="number" step="5" min="0" max="100" name="tp1_partial_pct"
               value="<?= $s('tp1_partial_pct', '50') ?>">
        <p class="hint">This is the plan every published R is measured against, so the exit rules on
          the signal card state it and the verifier settles by it. <strong>50</strong> banks half at
          TP1 and lets the rest run to TP2 behind a break-even stop; <strong>0</strong> holds
          everything for TP2; <strong>100</strong> takes it all at TP1 and ignores TP2. Raising it
          lifts the value of trades that tag TP1 and come back, and lowers the value of the ones
          that run.</p>
      </div>
        <div><label>Share taken off at TP2 (%)</label>
          <input type="number" step="5" min="0" max="100" name="tp2_partial_pct"
                 value="<?= $s('tp2_partial_pct', '30') ?>">
          <p class="hint">Whatever is left after both shares runs to <strong>TP3</strong> behind a stop
            that trails to TP1. This is what makes TP3 a real target rather than a number on the card:
            the verifier used to close the whole remainder at TP2, so &quot;TP3 reached&quot; was not a
            rare outcome, it was one the record could not produce. Set this and the TP1 share to add
            up to 100 and there is no runner &mdash; the plan is exactly what it was before.</p>
        </div>
    </div>
    <p class="hint">Levels are computed from the current ATR and nearby swing support/resistance
      (the stop is parked just beyond the swing level when one is close). Every plan also ships
      exit rules: close on an opposite signal flip, optional break-even after TP1, and the time stop.
      Shown with BUY/SELL signals on the site and in alert emails.</p>
  </details><?php endif; ?>

  <?php if (\SignalMasterAi\Master::master('alerts')): ?><details class="form-card" data-tab="alerts" open>
    <summary>Daily email digest</summary>
  <div class="row2">
    <div><label>Send a daily summary to alert-enabled members</label>
      <select name="digest_enabled">
        <option value="1" <?= Database::setting('digest_enabled') === '1' ? 'selected' : '' ?>>Enabled — flips on their coins + site stats</option>
        <option value="0" <?= Database::setting('digest_enabled') !== '1' ? 'selected' : '' ?>>Disabled</option>
      </select></div>
    <div><label>Send after this hour (UTC, 0-23)</label>
      <input type="number" name="digest_hour" value="<?= $s('digest_hour', '8') ?>" min="0" max="23"></div>
  </div>
  <p class="hint">Members with zero flips on their watched coins that day are skipped - no empty
    spam. Requires the cron job and email sending configured above.</p>
</details><?php endif; ?>

<?php if (\SignalMasterAi\Master::master('alerts')): ?><details class="form-card" data-tab="alerts">
    <summary>Telegram alerts</summary>
  <p class="hint">Create a bot in one minute: message <strong>@BotFather</strong> on Telegram, send
    <code>/newbot</code>, and paste the token here. Members then link their chat with one tap on
    their account page and receive signal flips as Telegram messages (same watched coins as email).
    Linking and delivery run through cron - no webhook setup needed.</p>
  <div class="row2">
    <div><label>Telegram signal alerts</label>
      <select name="tg_enabled">
        <option value="1" <?= Database::setting('tg_enabled') === '1' ? 'selected' : '' ?>>Enabled</option>
        <option value="0" <?= Database::setting('tg_enabled') !== '1' ? 'selected' : '' ?>>Disabled</option>
      </select></div>
    <div data-dep="tg_enabled" data-dep-value="1"
         data-dep-note="Telegram alerts are disabled, so this token is not in use. Save it now if you like, then set Telegram signal alerts to Enabled."><label>Bot token <?= Database::setting('tg_bot_token') !== '' ? '(saved - leave blank to keep)' : '' ?></label>
      <input type="password" name="tg_bot_token" value="" autocomplete="new-password" placeholder="123456789:AA..."></div>
  </div>
  <?php if (Database::setting('tg_bot_token') !== ''): ?>
  <p class="hint">Bot:
    <?php $tgU = \SignalMasterAi\Telegram::botUsername(); ?>
    <strong><?= $tgU !== '' ? '@' . sma_e($tgU) : 'token saved (username resolves on first cron run)' ?></strong>
    &middot; linked members: <strong><?= \SignalMasterAi\Telegram::linkedCount() ?></strong></p>
  <?php endif; ?>
</details><?php endif; ?>
<?php if (\SignalMasterAi\Master::master('alerts')): ?><details class="form-card" data-tab="alerts">
    <summary>Public broadcast channels</summary>
  <p class="hint">Telegram delivery reaches members who linked their account. A public channel is
    how this kind of product actually gets discovered. Only setups meeting the grade below are
    posted, once per pair/timeframe/verdict — a channel that posts every marginal signal trains its
    audience to ignore it.</p>
  <div class="row2">
    <div><label>Broadcasting</label>
      <select name="broadcast_enabled">
        <option value="1" <?= Database::setting('broadcast_enabled') === '1' ? 'selected' : '' ?>>Enabled</option>
        <option value="0" <?= Database::setting('broadcast_enabled') !== '1' ? 'selected' : '' ?>>Disabled</option>
      </select></div>
    <div><label>Minimum grade to post</label>
      <select name="broadcast_min_grade">
        <?php foreach (['B' => 'B and up', 'A' => 'A and up (recommended)', 'A+' => 'A+ only'] as $g => $lbl): ?>
          <option value="<?= sma_e($g) ?>" <?= Database::setting('broadcast_min_grade', 'A') === $g ? 'selected' : '' ?>><?= sma_e($lbl) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="row2">
    <div><label>Telegram channel or group ID</label>
      <input type="text" name="broadcast_tg_chat" value="<?= sma_e(Database::setting('broadcast_tg_chat')) ?>" placeholder="@mychannel or -1001234567890">
      <p class="hint">Add the bot configured above as an administrator of the channel.</p></div>
    <div><label>Discord / Slack incoming webhook</label>
      <input type="url" name="broadcast_discord_url" value="<?= sma_e(Database::setting('broadcast_discord_url')) ?>" placeholder="https://discord.com/api/webhooks/...">
    </div>
  </div>
</details><?php endif; ?>

<?php if (\SignalMasterAi\Master::master('signals')): ?><details class="form-card" data-tab="signals">
    <summary>AI explanations (optional)</summary>
  <p class="hint">Turns the fired-rule list into two sentences a person can read, and lets members
    ask follow-up questions about a setup. <strong>The model never influences a signal</strong> — it
    receives the finished analysis and describes it. Letting it decide anything would make the track
    record unreproducible and the audit trail a fiction. With no API key the site behaves exactly as
    it does today.</p>
  <div class="row2">
    <div><label>Explanations</label>
      <select name="ai_enabled">
        <option value="1" <?= Database::setting('ai_enabled') === '1' ? 'selected' : '' ?>>Enabled</option>
        <option value="0" <?= Database::setting('ai_enabled') !== '1' ? 'selected' : '' ?>>Disabled</option>
      </select></div>
    <div><strong>Who can ask follow-up questions</strong>
      <p class="hint" style="margin:6px 0 0"><?= sma_e(\SignalMasterAi\Gate::tierLabel('ai_ask')) ?>.
        Set it under <a href="#gates"><strong>What free and premium accounts get</strong></a>, on
        the Members tab.</p></div>
  </div>
  <div data-dep="ai_enabled" data-dep-value="1"
       data-dep-note="AI explanations are disabled, so the endpoint, model and key below are not in use. Set them now if you like, then switch Explanations to Enabled.">
  <div class="row2">
    <div><label>API endpoint</label>
      <input type="url" name="ai_endpoint" value="<?= sma_e(Database::setting('ai_endpoint', 'https://api.openai.com/v1/chat/completions')) ?>">
      <p class="hint">Any OpenAI-compatible chat endpoint, including a local runner — you are not
        locked to one provider.</p></div>
    <div><label>Model</label>
      <input type="text" name="ai_model" value="<?= sma_e(Database::setting('ai_model', 'gpt-4o-mini')) ?>"></div>
  </div>
  <label>API key <?= Database::setting('ai_api_key') !== '' ? '(saved — leave blank to keep)' : '' ?></label>
  <input type="password" name="ai_api_key" value="" autocomplete="new-password" placeholder="sk-...">
  </div><?php // end of the AI-only block ?>
  <p class="hint">Explanations are cached per verdict, so a chart refreshing every 60 seconds does
    not bill on every refresh.
    <?php $aiFail = \SignalMasterAi\Explain::recentFailures(); ?>
    <?php if ($aiFail > 0): ?>
      <br><span style="color:var(--warn)">▲ <?= (int)$aiFail ?> recent upstream failure<?= $aiFail === 1 ? '' : 's' ?> — check the key and endpoint.</span>
    <?php endif; ?>
  </p>
</details><?php endif; ?>

<details class="form-card" data-tab="members" open>
    <summary>Accounts</summary>
    <div class="row2">
      <div><label>Member registration</label>
        <select name="registration_enabled">
          <option value="1" <?= Database::setting('registration_enabled', '1') === '1' ? 'selected' : '' ?>>Open (visitors can register)</option>
          <option value="0" <?= Database::setting('registration_enabled', '1') === '0' ? 'selected' : '' ?>>Closed</option>
        </select></div>
      <div></div>
    </div>
  </details>

  <?php
  // FOUR SWITCHES THAT HAD NO CONTROL AT ALL.
  //
  // paper_enabled, member_backtest_enabled, api_feed_enabled and
  // webhooks_enabled are read by real code - they hide the nav link, they gate
  // the endpoint, they decide whether the webhook block appears on a member's
  // account page - and none of them could be changed except by editing the
  // settings table by hand. They all default to on, so nothing was broken;
  // what was broken was the panel's claim to be the place you change things.
  //
  // Worse, the gate table below DETECTS when one is off and tells the operator
  // to "switch it on at Paper portfolio, under Signals" - naming a control
  // that did not exist. A dead end printed by the one screen whose job is
  // answering what each tier gets.
  //
  // Whether, not who. Who is the table below, and it stays there.
  ?>
  <details class="form-card" data-tab="members" id="trial" open>
    <summary>Free trial for new accounts</summary>
    <?php $trStats = \SignalMasterAi\Trial::stats(); ?>
    <p class="hint">Premium days, granted once, when a new account becomes usable &mdash; the code
      being accepted where email verification is on, registration itself where it is off. It writes
      to the same expiry a payment writes to, so everything downstream already understands it, and
      it never shortens time somebody already has.</p>
    <div class="row2">
      <div><label>Trial</label>
        <select name="trial_enabled">
          <option value="0"<?= Database::setting('trial_enabled','0') !== '1' ? ' selected' : '' ?>>Off</option>
          <option value="1"<?= Database::setting('trial_enabled','0') === '1' ? ' selected' : '' ?>>On &mdash; every new account gets one</option>
        </select>
        <p class="hint"><strong>Off by default.</strong> An upgrade must never start giving premium
          away on a site that did not ask for it.</p>
      </div>
      <div data-dep="trial_enabled" data-dep-value="1"
           data-dep-note="The trial is off, so nothing below it is granting anything. Set it up now if you like, then switch Trial to On."><label>Length (days)</label>
        <input type="number" name="trial_days" min="0" max="365" step="1"
               value="<?= $s('trial_days', '3') ?>" list="trialDays">
        <datalist id="trialDays"><option value="1"><option value="3"><option value="7"><option value="14"><option value="30"></datalist>
        <p class="hint">1, 3 and 7 are the usual choices &mdash; type any number up to 365.
          0 switches the trial off as surely as the toggle does.</p>
      </div>
    </div>
    <p class="hint" style="margin-top:6px"><strong>Three things stop one person taking it over and
      over</strong>, and none of them is a wall &mdash; together they make casual repetition
      inconvenient, which is what a trial gate is for. What actually limits the damage is that a
      trial grants time, not money.</p>
    <div class="row2">
      <div><label>Trials allowed per IP address</label>
        <input type="number" name="trial_max_per_ip" min="1" max="100" step="1"
               value="<?= $s('trial_max_per_ip', '1') ?>">
        <label style="margin-top:10px">counted over the last (days, 0 = for ever)</label>
        <input type="number" name="trial_ip_days" min="0" max="3650" step="1"
               value="<?= $s('trial_ip_days', '30') ?>">
        <p class="hint">The check that catches the lazy repeat &mdash; and the one that punishes the
          innocent, because a family, an office, a university and most mobile networks share one
          address. A count over a window rather than a ban, so raise it the moment real users start
          hitting it.</p>
      </div>
      <div><label>Same-browser check</label>
        <select name="trial_device_check">
          <option value="1"<?= Database::setting('trial_device_check','1') === '1' ? ' selected' : '' ?>>On</option>
          <option value="0"<?= Database::setting('trial_device_check','1') !== '1' ? ' selected' : '' ?>>Off</option>
        </select>
        <p class="hint">A token planted in the browser on its first visit. Beaten by clearing
          cookies or a private window, which is why it is one signal of three.</p>
        <p class="hint"><strong>Email is the third</strong>, and it is always on: addresses are
          normalised first, so <code>a+1@gmail.com</code>, <code>a.b@gmail.com</code> and
          <code>ab@gmail.com</code> count as the one mailbox they actually are.</p>
      </div>
    </div>
    <?php // table.grid.stack's mobile card layout inherits overflow-wrap:anywhere
          // from its td onto this ::before label - meant for an unbreakable value
          // like a wallet address, but on a plain word like "STARTED" or "RUNNING"
          // it breaks the word itself rather than wrapping at the space the two-word
          // labels already have room to use. Scoped to this card only. ?>
    <style>
      @media (max-width: 860px) {
        #trial table.grid.stack td::before { overflow-wrap: normal; word-break: normal; }
      }
    </style>
    <div class="tbl-scroll" style="margin-top:12px">
    <table class="grid stack">
      <tr class="head"><th>Trials started</th><th>Still running</th><th>Last 7 days</th>
          <th>Last 30 days</th><th>Went on to pay</th></tr>
      <tr>
        <td data-label="Started"><strong><?= (int)$trStats['total'] ?></strong></td>
        <td data-label="Running"><?= (int)$trStats['active'] ?></td>
        <td data-label="7 days"><?= (int)$trStats['last_7'] ?></td>
        <td data-label="30 days"><?= (int)$trStats['last_30'] ?></td>
        <td data-label="Paid"><strong><?= (int)$trStats['converted'] ?></strong></td>
      </tr>
    </table>
    </div>
    <p class="hint">The last column is the only one that says whether the trial is worth running:
      accounts that started a trial and then made a payment for real money afterwards. The ledger
      behind these counts holds no email addresses and no IPs &mdash; all three keys are hashed with
      this install's own secret, because a table kept for years should not also be a list of who
      your visitors are.</p>
  </details>

  <details class="form-card" data-tab="members" id="memtools" open>
    <summary>Member tools &mdash; on or off</summary>
    <p class="hint" style="margin-top:0">Whether these exist on the site at all. <strong>Who</strong>
      may use them is the table below &mdash; a feature switched off here is available to nobody,
      whatever its tier says, and the table says so when that happens.</p>
    <div class="row2">
      <div><label>Paper portfolio &amp; journal</label>
        <select name="paper_enabled">
          <option value="1" <?= Database::setting('paper_enabled', '1') === '1' ? 'selected' : '' ?>>On — members can follow calls on paper</option>
          <option value="0" <?= Database::setting('paper_enabled', '1') === '0' ? 'selected' : '' ?>>Off — hidden from the menu and the API</option>
        </select></div>
      <div><label>Highest leverage a member may take</label>
        <input type="number" min="1" max="<?= (int)\SignalMasterAi\Paper::MAX_LEVERAGE_CEILING ?>"
               step="1" name="paper_max_leverage"
               value="<?= sma_e(Database::setting('paper_max_leverage', '125')) ?>">
        <p class="hint">At <strong>100&times; a 1% move against a position takes the whole
          margin</strong>, so this rung decides how fast a member can empty their simulated
          wallet. A loss can never exceed the amount they committed &mdash; the position is
          liquidated at that point, the way an isolated-margin position is on a real venue
          &mdash; so the rest of the balance is never at risk, but the wallet still goes to
          nothing that much faster. Lower it if the portfolio is meant to teach patience.</p>
        <label style="margin-top:10px">Leverage rungs on the order ticket</label>
        <input type="text" name="paper_leverages" style="font-family:var(--font-mono)"
               value="<?= sma_e(Database::setting('paper_leverages',
                   \SignalMasterAi\Paper::LEVERAGE_LADDER_DEFAULT)) ?>">
        <p class="hint">Comma separated. Anything above the cap above is dropped from the list
          rather than shown and quietly reduced &mdash; a button that does something other than
          what it says is worse than a missing button. In effect now:
          <code><?= sma_e(implode(', ', array_map(
              static fn($n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') . 'x',
              \SignalMasterAi\Paper::leverageLadder()))) ?></code>.</p></div>
    </div>
    <?php // Its own row, not a third column squeezed beside Paper portfolio
          // and Highest leverage - this field's hint runs four sentences and
          // was landing in a column barely wide enough for its own label. ?>
    <div class="row2">
      <div data-dep="paper_enabled" data-dep-value="1"
           data-dep-note="The paper portfolio is off, so no trades are being opened and nothing is being emailed about them."><label>Trade receipts by email</label>
        <select name="trade_email_enabled" aria-label="Trade receipts by email">
          <option value="1" <?= Database::setting('trade_email_enabled', '1') === '1' ? 'selected' : '' ?>>On &mdash; tell a member when their trade opens and closes</option>
          <option value="0" <?= Database::setting('trade_email_enabled', '1') === '0' ? 'selected' : '' ?>>Off &mdash; no email either way</option>
        </select>
        <p class="hint">Two messages per trade at most, sent by the background worker rather than
          from the button, so opening a position never waits on your mail server. The close carries
          the exit price, how it ended, the money, the R, how long it was held and the wallet
          afterwards. Each member can turn their own off on their account page; this switch turns
          it off for everybody.</p></div>
    </div>
    <div class="row2">
      <div><label>Member backtesting</label>
        <select name="member_backtest_enabled">
          <option value="1" <?= Database::setting('member_backtest_enabled', '1') === '1' ? 'selected' : '' ?>>On — members can replay the engine over history</option>
          <option value="0" <?= Database::setting('member_backtest_enabled', '1') === '0' ? 'selected' : '' ?>>Off — hidden from the menu</option>
        </select></div>
    </div>
    <div class="row2">
      <div><label>Signal API feed</label>
        <select name="api_feed_enabled">
          <option value="1" <?= Database::setting('api_feed_enabled', '1') === '1' ? 'selected' : '' ?>>On — token holders can pull the latest verdicts</option>
          <option value="0" <?= Database::setting('api_feed_enabled', '1') === '0' ? 'selected' : '' ?>>Off — the endpoint returns nothing</option>
        </select>
        <p class="hint">The endpoint list and the token instructions are on the
          <a href="api.php">API page</a>.</p></div>
      <div><label>Outgoing webhooks</label>
        <select name="webhooks_enabled">
          <option value="1" <?= Database::setting('webhooks_enabled', '1') === '1' ? 'selected' : '' ?>>On — members can post their alerts to their own URL</option>
          <option value="0" <?= Database::setting('webhooks_enabled', '1') === '0' ? 'selected' : '' ?>>Off — the webhook field is hidden</option>
        </select></div>
    </div>
  </details>

  <?php
  // HOW MUCH, next to WHETHER.
  //
  // The gate table below answers "does this tier get the feature". That is a
  // blunt instrument on its own: a feature is the whole product or nothing,
  // and the only way to make a free account worth having was to give
  // something away entirely. So the site had exactly one tiered number in it -
  // the watchlist cap - and a stranger got the same scanner depth, the same
  // backtests and the same paper portfolio as somebody paying.
  //
  // Every default here is the value that shipped before this table existed,
  // for every tier, so nothing changes until the operator decides it should.
  // A limit that appeared by itself on a live site would be taking something
  // away from people who already had it.
  ?>
  <details class="form-card" data-tab="members" id="limits" open>
    <summary>How much each tier gets</summary>
    <p class="hint" style="margin-top:0"><strong>0 means unlimited.</strong> These are the numbers
      the gate table below cannot express &mdash; a free account that can watch five pairs and run
      three backtests a day is a real account that runs out where somebody using the product
      seriously would notice, which is a reason to pay. A locked door is not. Anything you raise
      above the free allowance is described on the upgrade page automatically.</p>
    <div class="tbl-scroll">
    <table class="perf-table gate-table">
      <tr>
        <th>Limit</th>
        <?php foreach (\SignalMasterAi\Limits::TIERS as $tk => $tl): ?>
          <th class="num"><?= sma_e($tl) ?></th>
        <?php endforeach; ?>
      </tr>
      <?php foreach (\SignalMasterAi\Limits::LIMITS as $lk => $l): ?>
        <?php // Not Limits::live(): that also checks a tier gate against
              // whoever is viewing THIS page (the admin's own account), and
              // this row lists every tier's number at once - there is no
              // one viewer to gate-check here, only "is it switched on for
              // anybody at all." ?>
        <?php $lLive = \SignalMasterAi\Limits::featureOn($lk); ?>
        <tr>
          <td><strong><?= sma_e((string)$l['label']) ?></strong>
            <?php if (!$lLive): ?>
              <span class="hint" style="color:var(--warn)">&nbsp;&mdash; switched off</span>
            <?php endif; ?>
            <div class="hint" style="margin:2px 0 0"><?= sma_e((string)$l['blurb']) ?></div></td>
          <?php foreach (\SignalMasterAi\Limits::TIERS as $tk => $tl): ?>
            <td class="num"><input type="number" min="0" max="<?= (int)($l['max'] ?? 100000) ?>" style="width:90px"
                   name="<?= sma_e((string)$l['keys'][$tk]) ?>"
                   value="<?= (int)\SignalMasterAi\Limits::forTier($lk, $tk) ?>"
                   aria-label="<?= sma_e($l['label'] . ' - ' . $tl) ?>"></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p class="hint"><strong>Signed out</strong> is what a visitor with no account gets.
      A limit set higher for free than for premium is not shown on the upgrade page &mdash; it is
      treated as a mistake rather than dressed up as a benefit.</p>
  </details>

  <details class="form-card" data-tab="members" id="gates" open>
    <summary>What free and premium accounts get</summary>
    <p class="hint" style="margin-top:0">One screen for every gate on the site. Whatever you set to
      <strong>Premium</strong> here is automatically listed on the upgrade page and in the prompt free
      members see &mdash; lock a feature and the sales copy grows a line about it on its own, so the two
      can never disagree. Locked pages stay in the menu with a padlock rather than vanishing: a free
      member cannot want what they cannot see, and a page that disappears reads as a broken site
      rather than a paid one.</p>
    <table class="perf-table gate-table">
      <tr><th>Feature</th><th>Who gets it</th></tr>
      <?php foreach (\SignalMasterAi\Gate::FEATURES as $gk => $gf): ?>
        <?php $cur = \SignalMasterAi\Gate::tier($gk); ?>
        <?php
        // A gate says WHO. It does not say WHETHER, and the two live in
        // different cards - so this table could read "Ask about a signal -
        // Premium" while the AI layer was switched off entirely two cards
        // away, telling an operator their paying members get something
        // nobody gets. On this install two of the nine rows were in exactly
        // that state. The table is the one place that is supposed to answer
        // "what does each tier get" honestly, so it has to say when the
        // answer is "nothing, this is off".
        $gLive = \SignalMasterAi\Premium::featureLive($gk);
        $gWhere = [
            'scanner'   => 'the Signals master switch, at the top of this page',
            'portfolio' => 'Member tools, in the card above',
            'backtest'  => 'Member tools, in the card above',
            'mtf'       => 'the multi-timeframe strip, under Signals',
            'ai_ask'    => 'AI explanations, under Signals',
            'bulk_watch' => 'the watch-all button, in the card above',
            'top_watch' => 'the best-performing buttons, in the card above',
            'telegram'  => 'Telegram alerts, under Alerts - no bot token is set',
            'api_feed'  => 'Member tools, in the card above',
        ][$gk] ?? 'its own card';
        ?>
        <tr>
          <td><strong><?= $gf['icon'] ?> <?= $gf['label'] ?></strong>
            <?php if (!$gLive): ?>
              <span class="hint" style="color:var(--warn)">&nbsp;&mdash; switched off</span>
            <?php endif; ?>
            <div class="hint" style="margin:2px 0 0"><?= sma_e((string)$gf['blurb']) ?></div>
            <?php if (!$gLive): ?>
              <div class="hint" style="margin:2px 0 0;color:var(--warn)">Nobody gets this at the
                moment, whatever the tier says &mdash; switch it on at <?= sma_e($gWhere) ?>.</div>
            <?php endif; ?></td>
          <?php // Short labels. "Members (free account)" is clearer in prose
                // and does not fit the column on a narrow admin screen, where
                // it rendered as "Membe" - a control whose options are cut off
                // mid-word is a control an operator cannot trust. The full
                // meaning is one line below the table instead. ?>
          <td class="gate-pick">
            <select name="<?= sma_e((string)$gf['setting']) ?>">
              <option value="any"  <?= $cur === 'any'  ? 'selected' : '' ?>>Everyone</option>
              <option value="free" <?= $cur === 'free' ? 'selected' : '' ?>>Members</option>
              <option value="paid" <?= $cur === 'paid' ? 'selected' : '' ?>>Premium</option>
            </select>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
    <p class="hint"><strong>Everyone</strong> includes signed-out visitors. <strong>Members</strong>
      means any registered account, free included. <strong>Premium</strong> means a paying account
      only.</p>
    <p class="hint">Watchlist size is a limit rather than a gate, so it has its own card above. Which
      individual coins are premium is set per coin, under Coins.</p>
  </details>

  <details class="form-card" data-tab="members">
    <summary>Discount campaign (banner + popup)</summary>
    <?php
      $promoCode = Database::setting('promo_coupon', '');
      $promoRow = null;
      if ($promoCode !== '') {
          $cs = $pdo->prepare('SELECT * FROM coupons WHERE code = ?');
          $cs->execute([$promoCode]);
          $promoRow = $cs->fetch() ?: null;
      }
      $promoLive = \SignalMasterAi\Promo::active();
      $dtl = static fn(string $k) => ((int)Database::setting($k, '0') > 0)
          ? date('Y-m-d\TH:i', (int)Database::setting($k, '0')) : '';
    ?>
    <p class="hint" style="margin-top:0">Runs a discount across the site: a strip at the top of every
      page, an interrupting panel, or both, with a live countdown. <strong>The percentage and the
      expiry come from the coupon, not from here</strong> &mdash; the same row the checkout reads &mdash;
      so the banner can never advertise a number the checkout will refuse.</p>
    <?php // The status line is the whole point of this card: a campaign that
          // is off has exactly one reason, and an operator should not have to
          // guess which. ?>
    <?php if (Database::setting('promo_enabled', '0') !== '1'): ?>
      <p class="hint"><strong>Status: switched off.</strong></p>
    <?php elseif ($promoCode === ''): ?>
      <p class="hint"><strong>Status: no coupon code set</strong> &mdash; nothing will show. Create a
        coupon under Payments, then put its code below.</p>
    <?php elseif (!$promoRow): ?>
      <p class="hint"><strong>Status: coupon <?= sma_e($promoCode) ?> does not exist</strong> &mdash;
        nothing will show.</p>
    <?php elseif ($promoLive === null): ?>
      <p class="hint"><strong>Status: not running.</strong> The coupon exists but is
        <?php if ((int)$promoRow['enabled'] !== 1): ?>disabled<?php
        elseif ((int)$promoRow['max_uses'] > 0 && (int)$promoRow['used_count'] >= (int)$promoRow['max_uses']): ?>out
          of uses (<?= (int)$promoRow['used_count'] ?> of <?= (int)$promoRow['max_uses'] ?>)<?php
        elseif ((int)$promoRow['expires_at'] > 0 && (int)$promoRow['expires_at'] <= time()): ?>expired<?php
        elseif ((int)Database::setting('promo_starts', '0') > time()): ?>scheduled to start
          <?= date('j M Y H:i', (int)Database::setting('promo_starts', '0')) ?><?php
        else: ?>outside the campaign window<?php endif; ?>.</p>
    <?php else: ?>
      <p class="hint"><strong>Status: live now</strong> &mdash; <?= (int)$promoLive['percent'] ?>% off with
        code <?= sma_e($promoLive['code']) ?><?= $promoLive['ends'] > 0
          ? ', ends in ' . sma_e(\SignalMasterAi\Promo::remaining($promoLive['ends'])) : ', no end date' ?>.
        <?= (int)$promoRow['max_uses'] > 0
          ? (int)($promoRow['max_uses'] - $promoRow['used_count']) . ' uses left.' : 'Unlimited uses.' ?></p>
    <?php endif; ?>
    <div class="row2">
      <div><label>Campaign</label>
        <select name="promo_enabled">
          <option value="0" <?= Database::setting('promo_enabled', '0') === '0' ? 'selected' : '' ?>>Off</option>
          <option value="1" <?= Database::setting('promo_enabled', '0') === '1' ? 'selected' : '' ?>>On</option>
        </select></div>
      <div><label>Coupon code</label>
        <input type="text" name="promo_coupon" value="<?= $s('promo_coupon', '') ?>" placeholder="LAUNCH50">
        <p class="hint">Must match a coupon under Payments. Its discount, expiry and remaining uses
          drive everything above.</p></div>
    </div>
    <div class="row2">
      <div><label>Headline <span class="muted">(optional)</span></label>
        <input type="text" name="promo_headline" value="<?= $s('promo_headline', '') ?>"
               placeholder="<?= $promoLive ? sma_e((int)$promoLive['percent'] . '% off Premium') : '50% off Premium' ?>">
        <p class="hint">Leave blank and it writes itself from the coupon.</p></div>
      <div><label>Body text <span class="muted">(optional)</span></label>
        <input type="text" name="promo_body" value="<?= $s('promo_body', '') ?>"
               placeholder="Weekend only - use the code at checkout."></div>
    </div>
    <div class="row2">
      <div><label>Starts <span class="muted">(blank = now)</span></label>
        <input type="datetime-local" name="promo_starts" value="<?= sma_e($dtl('promo_starts')) ?>">
        <p class="hint">Server time. The campaign appears on its own, with no action from you.</p></div>
      <div><label>Ends <span class="muted">(blank = when the coupon expires)</span></label>
        <input type="datetime-local" name="promo_ends" value="<?= sma_e($dtl('promo_ends')) ?>">
        <p class="hint">Whichever is sooner &mdash; this or the coupon's own expiry &mdash; wins, so the
          countdown can never outlive the code it is counting down to.</p></div>
    </div>
    <div class="row2">
      <div><label>Who sees it</label>
        <select name="promo_audience">
          <?php foreach ([
            'free' => 'Free members (not signed-in guests)',
            'all' => 'Everyone, including signed-out visitors',
            'expired' => 'Lapsed members only (paid before, not now)',
            'new' => 'New free members only',
            'paid' => 'Current premium members only (renewals)',
          ] as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= Database::setting('promo_audience', 'free') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Show it to only this share of them</label>
        <input type="number" name="promo_random_pct" min="0" max="100" value="<?= $s('promo_random_pct', '100') ?>">
        <p class="hint">100 = everyone in that audience. Lower it to test an offer on a slice first.
          The choice is fixed per person for the whole campaign, so nobody sees the banner appear and
          disappear as they browse &mdash; that reads as a broken site and makes the offer look fake.</p></div>
    </div>
    <div class="row2">
      <div><label>"New" member window (days)</label>
        <input type="number" name="promo_new_days" min="1" max="365" value="<?= $s('promo_new_days', '7') ?>">
        <p class="hint">Counts as the first N days after sign-up &mdash; only matters when
          <strong>Who sees it</strong> above is set to "New free members only".</p></div>
    </div>
    <div class="row2">
      <div><label>Or send it to specific people only</label>
        <input type="text" name="promo_emails" value="<?= $s('promo_emails', '') ?>"
               placeholder="someone@example.com, another@example.com">
        <p class="hint">Comma-separated. <strong>If this is filled in it overrides the audience and the
          share above</strong> &mdash; naming an address is an instruction, and quietly dropping that person
          because they fell outside a random slice would be the feature failing at its one job.</p></div>
      <div><label>Ask again after (hours)</label>
        <input type="number" name="promo_repeat_hours" min="1" max="8760" value="<?= $s('promo_repeat_hours', '48') ?>">
        <p class="hint">How long the popup stays away once somebody closes it. The top strip is not
          affected &mdash; closing that hides it for the browsing session only.</p></div>
    </div>
    <div class="row2">
      <div><label>Top strip on every page</label>
        <select name="promo_banner">
          <option value="1" <?= Database::setting('promo_banner', '1') === '1' ? 'selected' : '' ?>>Show</option>
          <option value="0" <?= Database::setting('promo_banner', '1') === '0' ? 'selected' : '' ?>>Hide</option>
        </select></div>
      <div><label>Interrupting popup</label>
        <select name="promo_popup">
          <option value="1" <?= Database::setting('promo_popup', '1') === '1' ? 'selected' : '' ?>>Show</option>
          <option value="0" <?= Database::setting('promo_popup', '1') === '0' ? 'selected' : '' ?>>Hide</option>
        </select>
        <p class="hint">A quiet evergreen discount usually wants the strip and not the popup.</p></div>
    </div>
    <div class="row2">
      <div><label>Countdown timer</label>
        <select name="promo_timer">
          <option value="1" <?= Database::setting('promo_timer', '1') === '1' ? 'selected' : '' ?>>Show a live countdown</option>
          <option value="0" <?= Database::setting('promo_timer', '1') === '0' ? 'selected' : '' ?>>No timer</option>
        </select>
        <p class="hint">Only appears when the campaign has an end date. It counts down to a real
          timestamp, so a tab left open overnight still shows the truth.</p></div>
      <div></div>
    </div>
    <p class="hint">While a campaign is live it replaces the standard upgrade prompt below rather than
      stacking on top of it &mdash; two overlays for one message is how a site stops being read.</p>
  </details>

  <details class="form-card" data-tab="members">
    <summary>Upgrade prompt for free members</summary>
    <p class="hint" style="margin-top:0">A one-off panel telling free members what Premium adds.
      It never appears for a paying member, never for a signed-out visitor, and never again for the
      repeat window below once someone closes it. Everything it lists is read from your live
      settings, so it cannot advertise something you have switched off.</p>
    <?php $pb = \SignalMasterAi\Premium::benefits(); ?>
    <?php if (!$pb): ?>
      <p class="hint"><strong>Nothing on this site is currently premium-only</strong>, so the prompt
        will not show at all &mdash; there is nothing to tell anyone about. Mark some coins as
        premium under Coins, or restrict a feature to paid above, and it starts working.</p>
    <?php else: ?>
      <p class="hint">It currently lists <strong><?= count($pb) ?></strong>
        <?= count($pb) === 1 ? 'benefit' : 'benefits' ?>:
        <?php foreach ($pb as $i => $b): ?><?= $i ? ', ' : '' ?><?= sma_e($b['title']) ?><?php endforeach; ?>.</p>
    <?php endif; ?>
    <div class="row2">
      <div><label>Show the prompt</label>
        <select name="upsell_popup_enabled">
          <option value="1" <?= Database::setting('upsell_popup_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled</option>
          <option value="0" <?= Database::setting('upsell_popup_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
        </select></div>
      <div><label>Also show it to a brand-new account</label>
        <select name="upsell_popup_new">
          <option value="1" <?= Database::setting('upsell_popup_new', '1') === '1' ? 'selected' : '' ?>>Yes, in their first hour</option>
          <option value="0" <?= Database::setting('upsell_popup_new', '1') === '0' ? 'selected' : '' ?>>No, wait for the delay below</option>
        </select>
        <p class="hint">Somebody who has just registered is as interested as they will ever be, so
          telling them then is information rather than pestering. Turn it off if you would rather
          let people settle in first.</p></div>
    </div>
    <div class="row2">
      <div><label>Days after sign-up before showing it</label>
        <input type="number" name="upsell_popup_days" min="0" max="365" value="<?= $s('upsell_popup_days', '3') ?>">
        <p class="hint">Long enough that they have seen the site work, short enough to still be
          deciding.</p></div>
      <div><label>Days before asking again after "Not now"</label>
        <input type="number" name="upsell_popup_repeat" min="1" max="365" value="<?= $s('upsell_popup_repeat', '14') ?>">
        <p class="hint">Set this generously. The way a prompt like this fails is not too few people
          seeing it &mdash; it is everyone learning to close it without reading, and that does not
          undo. The panel tells the member this number, so they dismiss it freely instead of warily.</p></div>
    </div>
  </details>

  <details class="form-card" data-tab="members">
    <summary>Email verification at sign-up</summary>
  <?php
  $otpOn = Database::setting('email_otp', '1') === '1';
  $mailOk = \SignalMasterAi\Mailer::enabled();
  $otpWaiting = \SignalMasterAi\MemberVerify::pendingCount();
  ?>
  <p class="hint">New members are emailed a <?= (int)\SignalMasterAi\MemberVerify::LEN ?>-digit code and
    cannot log in until they type it back. This is what stops a typo (a member who never receives a
    single alert and blames you) and a made-up address (this site emailing signals to a stranger, which
    is how a sending domain gets blacklisted).</p>
  <?php if ($otpOn && !$mailOk): ?>
    <p class="hint" style="color:var(--warn-text)">Verification is switched on but this
      site cannot send email yet, so it is standing itself down and new members are being registered
      without a code. Set up sending under <a href="settings.php#alerts">Settings &rsaquo; Alerts</a>,
      then check delivery on the <a href="emails.php">Email log</a>.</p>
  <?php elseif ($otpOn): ?>
    <p class="hint" style="color:var(--up)">&#10003; Email sending is configured, so codes are being
      required.<?= $otpWaiting > 0 ? ' ' . (int)$otpWaiting . ' account'
          . ($otpWaiting === 1 ? ' is' : 's are') . ' waiting on a code right now &mdash; you can verify'
          . ' or chase them from <a href="members.php" style="color:inherit">Members</a>.' : '' ?></p>
  <?php endif; ?>
  <div class="row2">
    <div><label>Require a code from new members</label>
      <select name="email_otp">
        <option value="1" <?= $otpOn ? 'selected' : '' ?>>On - verify the email address</option>
        <option value="0" <?= !$otpOn ? 'selected' : '' ?>>Off - register straight away</option>
      </select>
      <span class="hint">Turning this off never un-verifies anyone. Members already waiting on a code
        still need one &mdash; release them individually from Members.</span></div>
    <div><label>Code lasts (minutes)</label>
      <input type="number" name="otp_ttl_min" min="2" max="1440"
             value="<?= (int)Database::setting('otp_ttl_min', '15') ?>">
      <span class="hint">Long enough to switch apps and come back. 15 is the usual choice.</span></div>
  </div>
  <div class="row2">
    <div><label>Wrong tries before the code is burned</label>
      <input type="number" name="otp_tries" min="1" max="20"
             value="<?= (int)Database::setting('otp_tries', '5') ?>">
      <span class="hint">Counted in the database, not the browser, so clearing cookies does not hand
        out fresh guesses. Running out costs a new code, not the account.</span></div>
    <div><label>Wait between resends (seconds)</label>
      <input type="number" name="otp_resend_sec" min="15" max="3600"
             value="<?= (int)Database::setting('otp_resend_sec', '60') ?>">
      <span class="hint">Stops the resend button being used to bury someone else's inbox.</span></div>
  </div>
  </details>

  <details class="form-card" data-tab="members">
    <summary>Referrals</summary>
  <p class="hint">Each member gets a code. When someone who arrived through it makes their first
    payment, both sides receive premium days — the reward stays inside the subscription machinery, so
    there are no payouts or balances to account for. Self-referral is blocked and each member can
    only be credited once.</p>
  <div class="row2">
    <div><label>Referral programme</label>
      <select name="referral_enabled">
        <option value="1" <?= Database::setting('referral_enabled') === '1' ? 'selected' : '' ?>>Enabled</option>
        <option value="0" <?= Database::setting('referral_enabled') !== '1' ? 'selected' : '' ?>>Disabled</option>
      </select></div>
    <div><label>Premium days for each side</label>
      <input type="number" min="1" max="365" name="referral_days" value="<?= sma_e(Database::setting('referral_days', '7')) ?>"></div>
  </div>
</details>

<?php if (\SignalMasterAi\Master::master('alerts')): ?><details class="form-card" data-tab="alerts">
    <summary>Email / SMTP</summary>
    <p class="hint" style="margin-top:0">Used by every email the site sends: alerts, the daily
      digest, sign-up verification and password resets.
      <a href="#emailtest"><strong>Send a test email</strong></a> from the last card on this tab
      &mdash; it needs its own form, so it cannot live inside this one.</p>
    <div class="row2">
      <div><label>Email sending</label>
        <select name="smtp_mode" aria-label="Email sending">
          <option value="off" <?= Database::setting('smtp_mode', 'off') === 'off' ? 'selected' : '' ?>>Disabled</option>
          <option value="smtp" <?= Database::setting('smtp_mode') === 'smtp' ? 'selected' : '' ?>>SMTP server</option>
          <option value="phpmail" <?= Database::setting('smtp_mode') === 'phpmail' ? 'selected' : '' ?>>PHP mail()</option>
        </select></div>
    </div>
    <?php // Everything from here down is SMTP-only. Marked rather than hidden:
          // filling the credentials in first and switching the mode on second
          // is a real order of work, and hiding them would forbid it. ?>
    <div data-dep="smtp_mode" data-dep-value="smtp"
         data-dep-note="Email sending is not set to SMTP, so nothing below is in use yet. Fill it in and set Email sending to &quot;SMTP server&quot; above.">
    <?php // Host, port and security are one decision - where the mail goes and
          // how it gets there - so they sit together, and inside the block that
          // only applies when SMTP is the chosen transport. Security used to be
          // beside the transport select, which put an SMTP-only control above
          // the line saying SMTP-only controls follow. ?>
    <div class="row3">
      <div><label>SMTP host</label><input type="text" name="smtp_host" aria-label="SMTP host" value="<?= $s('smtp_host') ?>" placeholder="smtp.gmail.com"></div>
      <div><label>Port</label><input type="number" name="smtp_port" aria-label="Port" value="<?= $s('smtp_port', '587') ?>"></div>
      <div><label>Security</label>
        <select name="smtp_security" aria-label="SMTP security">
          <option value="tls" <?= Database::setting('smtp_security', 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
          <option value="ssl" <?= Database::setting('smtp_security') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
          <option value="none" <?= Database::setting('smtp_security') === 'none' ? 'selected' : '' ?>>None</option>
        </select></div>
    </div>
    <div class="row2">
      <div><label>Username</label><input type="text" name="smtp_user" value="<?= $s('smtp_user') ?>"></div>
      <div><label>Password <?= Database::setting('smtp_pass') !== '' ? '(saved - leave blank to keep)' : '' ?></label>
        <input type="password" name="smtp_pass" value="" autocomplete="new-password"></div>
    </div>
    <div class="row2">
      <div><label>From email</label><input type="text" name="smtp_from_email" value="<?= $s('smtp_from_email') ?>" placeholder="alerts@yourdomain.com"></div>
      <div><label>From name</label><input type="text" name="smtp_from_name" value="<?= $s('smtp_from_name') ?>"></div>
    </div>
    </div><?php // end of the SMTP-only block ?>
    <p class="hint">Members who opt in get an email whenever a coin they watch flips bullish or bearish
      (sent by the cron job). Save settings first, then use the test button below.</p>
  </details><?php endif; ?>

<details class="form-card" data-tab="security">
  <summary>Abuse protection &mdash; logins, the API, and who the visitor is</summary>
  <p class="hint" style="margin-top:0">Three controls that depend on each other, two of which used
    to sit on the Market tab under storage retention. Getting the third one wrong disables the other
    two: if the site cannot tell visitors apart, a per-IP limit counts everyone into one bucket.</p>
  <p class="sub" style="margin-top:14px"><strong>1. Login attempts</strong></p>
  <div class="row2">
    <div><label>Failed attempts before a lockout</label>
      <input type="number" name="login_attempt_limit" min="3" max="50" value="<?= $s('login_attempt_limit', '8') ?>"></div>
    <div><label>Lockout window (seconds)</label>
      <input type="number" name="login_attempt_window" min="60" max="3600" value="<?= $s('login_attempt_window', '900') ?>"></div>
  </div>
  <p class="hint">Counted server-side, per IP <em>and</em> per account name. A failure count kept in
    the session is a count the attacker holds — dropping the cookie resets it, which made the old
    limit worth nothing against a script that fetches a fresh session per guess. Applies to both the
    admin login and the member login, and a correct password clears both counters.</p>

  <div class="row2">
    <div><label>Sign the admin out after this long idle (minutes, 0 = never)</label>
      <input type="number" name="admin_idle_minutes" min="0" max="43200" value="<?= $s('admin_idle_minutes', '720') ?>">
      <p class="hint">Counted from your last request, not from login, so a working day is never
        interrupted &mdash; it is a panel left open and walked away from that ends. This one can
        take payments, read every member's address and download the database, so it should not stay
        live indefinitely on an unattended screen. The session cookie already ends when the browser
        closes; this covers the browser that does not.</p></div>
    <div><label>HTTPS-only (HSTS)</label>
      <select name="hsts_enabled">
        <option value="1" <?= Database::setting('hsts_enabled', '1') === '1' ? 'selected' : '' ?>>On &mdash; tell browsers to refuse http (recommended)</option>
        <option value="0" <?= Database::setting('hsts_enabled', '1') === '0' ? 'selected' : '' ?>>Off</option>
      </select>
      <p class="hint">Sent only on requests that already arrived over https, so it can never lock
        out a site that is not serving TLS yet. Once sent, a browser remembers it for six months and
        <em>will not</em> talk to the domain over plain http in that time &mdash; which is the point,
        and also the reason to switch it off <strong>before</strong> you take https away rather than
        after. No subdomains and no preload list: this app does not know what else is on your
        domain and will not speak for it.</p></div>
  </div>

  <p class="sub" style="margin-top:18px"><strong>2. Analysis requests</strong></p>
  <div class="row2">
    <div><label>API rate limiting</label>
      <select name="rate_limit_enabled">
        <option value="1" <?= Database::setting('rate_limit_enabled', '1') === '1' ? 'selected' : '' ?>>Enabled (recommended)</option>
        <option value="0" <?= Database::setting('rate_limit_enabled', '1') === '0' ? 'selected' : '' ?>>Disabled</option>
      </select>
      <p class="hint">One analysis is a full pass of the rule set, and anonymous visitors can ask for
        one. Without this a single script can pin the CPU and burn the exchange quota. Separate from
        the login throttle above, deliberately: a wrong password and an expensive request are
        different kinds of abuse and deserve different budgets.</p>
    </div>
    <div><label>Budget multiplier</label>
      <input type="number" name="rate_limit_scale" step="0.1" min="0.1" max="20"
             value="<?= sma_e(Database::setting('rate_limit_scale', '1.0')) ?>">
      <?php // Read by RateLimit on every request and settable nowhere - this
            // panel described it as the operator's lever while offering no way
            // to pull it. ?>
      <p class="hint">Scales every per-action budget at once: 0.5 halves them, 2 doubles them. The
        defaults suit a normal site &mdash; a member's chart uses about one analysis a minute
        against a budget of forty. Raise it if you run a busy site behind one address, lower it on
        thin hosting.</p></div>
  </div>

  <p class="sub" style="margin-top:18px"><strong>3. Which address counts as the visitor</strong></p>
  <div class="row2">
    <div><label>Trust <code>X-Forwarded-For</code></label>
      <select name="trust_proxy">
        <option value="0" <?= Database::setting('trust_proxy', '0') === '0' ? 'selected' : '' ?>>No — use the direct connection address</option>
        <option value="1" <?= Database::setting('trust_proxy', '0') === '1' ? 'selected' : '' ?>>Yes — site sits behind Cloudflare / a proxy</option>
      </select>
      <p class="hint"><strong>Turn this on only if a proxy really does sit in front.</strong> Behind
        Cloudflare every visitor arrives as the proxy's address, so both controls above would treat
        the whole internet as one client. Trusting the header without a proxy is the opposite
        mistake: anyone can then set it and bypass both.</p>
    </div>
    <div></div>
  </div>
</details>

<?php // Retabbed from Members and renamed.
      //
      // It held candle retention, gap repair and API rate limiting - not one
      // member setting between them - under a heading an operator reads as
      // "where the GDPR controls are". Somebody looking for how long member
      // data is kept found candle history, and somebody looking for candle
      // history had no reason to open a card filed under Members at all. ?>
<details class="form-card" data-tab="market">
  <summary>Storage &amp; retention &mdash; how long data is kept</summary>
  <?php // Four settings had a save handler and no field: reachable only by
        // editing the database. trust_proxy is the one that matters most now -
        // behind Cloudflare every visitor arrives as the proxy's address, so a
        // per-IP login throttle would count the whole internet into one bucket
        // unless the real client header is trusted. ?>
  <div class="row2">
    <?php
    // What the stored history actually costs, because "1500 bars" is not a
    // size anyone can picture and the number that matters - how much of the
    // hosting quota this is eating - was nowhere on the page.
    $candleRows = (int)$pdo->query('SELECT COUNT(*) FROM candles')->fetchColumn();
    $candleSeries = (int)$pdo->query(
        'SELECT COUNT(*) FROM (SELECT 1 FROM candles GROUP BY symbol, tf) x')->fetchColumn();
    $candleMb = round($candleRows * 190 / 1048576, 1);   // row + unique index, measured
    ?>
    <div><label>Candle history kept per pair/timeframe (bars)</label>
      <input type="number" name="candle_retention_bars" min="0" max="100000" value="<?= $s('candle_retention_bars', '1500') ?>">
      <p class="hint">For coins in the scan rotation. Trimmed daily by cron. The backtester replays
        the most recent 1000, so going below that shortens what it can measure; 0 keeps everything
        and grows forever.</p>
      <p class="hint">Stored now: <strong><?= number_format($candleRows) ?></strong> candles across
        <?= number_format($candleSeries) ?> pair/timeframe series &mdash; roughly
        <strong><?= $candleMb ?> MB</strong> of database.</p>
    </div>
    <div><label>Candle history kept for coins NOT in the scan rotation (bars)</label>
      <input type="number" name="candle_retention_idle_bars" min="300" max="100000"
             value="<?= $s('candle_retention_idle_bars', '320') ?>">
      <p class="hint">The deep window above exists so the backtester has something to replay, and
        nothing backtests a coin the scan never visits. These keep only what the engine reads to
        score a chart &mdash; about 300 bars, for EMA 200 and the Ichimoku displacement. Opening
        such a coin still works; it just re-fetches anything older on demand. On a large imported
        watchlist this is the single biggest saving available.</p>
    </div>
    <div class="row2">
      <?php // Three settings that had a default, a daily prune acting on them,
            // and no field anywhere - reachable only by editing the database.
            //
            // signal_retention_days is the one that matters. It is the ceiling
            // on the site's whole memory: the track record only goes back this
            // far, and so does everything the engine learns from, because the
            // tuner reads settled signals and the prune deletes them. An
            // operator asking "how much history do I keep" had no way to see
            // the answer, let alone change it. ?>
      <?php // WHAT IS ACTUALLY USING THE SPACE, BEFORE ANY OF THESE ARE TOUCHED.
            //
            // Asked whether to cap signals at 90 days to stop the database
            // growing. Measured on a real install the intuition is backwards:
            // candles were 9.2 MB of a 15.5 MB file across 130,506 rows, while
            // every signal ever stored was 207 rows and 1.3 MB. Candles are a
            // CACHE and re-fetch on demand; a deleted signal is gone, and it
            // takes the track record and the tuner's training data with it.
            // So the numbers go above the settings rather than in a sentence
            // underneath them. ?>
      <div style="grid-column:1/-1">
        <?php $stor = []; try { $stor = \SignalMasterAi\Maintenance::storage(6); } catch (Throwable $e) {} ?>
        <?php if ($stor): ?>
          <label>What is using the space right now</label>
          <div class="tbl-scroll">
          <table class="grid stack">
            <tr class="head"><th>Table</th><th>Rows</th><th>Size</th><th></th></tr>
            <?php foreach ($stor as $st): ?>
              <tr>
                <td data-label="Table"><code><?= sma_e($st['table']) ?></code></td>
                <td data-label="Rows"><?= number_format((int)$st['rows']) ?></td>
                <td data-label="Size"><?= $st['bytes'] === null ? '<span class="hint">unknown</span>'
                    : sma_e(number_format((int)round($st['bytes'] / 1024)) . ' KB') ?></td>
                <td data-label="Note" class="hint"><?php
                  echo $st['table'] === 'candles'
                      ? 'A cache. Deleted, it is re-fetched from the exchange on demand &mdash; '
                        . 'trim this one first, with the bars setting above.'
                      : ($st['table'] === 'signals'
                          ? '<strong>The track record and the tuner\'s training data.</strong> '
                            . 'Cannot be rebuilt.'
                          : '&nbsp;'); ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
          </div>
          <p class="hint">If the database is too big, the row at the top is the one to act on.
            Cutting settled signals usually reclaims a fraction of what the candle cache holds and
            costs the one thing on this list that cannot be re-created.</p>
        <?php endif; ?>
      </div>
      <div><label>Settled signals kept (days, 0 = forever)</label>
        <input type="number" name="signal_retention_days" min="0" max="3650"
               value="<?= $s('signal_retention_days', '365') ?>">
        <p class="hint"><strong>This is the ceiling on the site's memory.</strong> The published
          track record reaches back exactly this far, and so does the self-tuning &mdash; it learns
          from settled signals, and these are the settled signals. Raising it costs storage and
          nothing else; lowering it throws away record you cannot get back. 0 keeps everything.</p></div>
      <div><label>Admin audit log kept (days)</label>
        <input type="number" name="audit_retention_days" min="7" max="3650"
               value="<?= $s('audit_retention_days', '180') ?>">
        <p class="hint">Who changed which setting, and what the engine changed by itself. Small
          rows; keep them longer than you think you need.</p>
        <label style="margin-top:10px">Error log kept (days)</label>
        <input type="number" name="error_retention_days" min="1" max="365"
               value="<?= $s('error_retention_days', '30') ?>">
        <label style="margin-top:10px">Sent-email log kept (days, 0 = forever)</label>
        <input type="number" name="email_log_days" min="0" max="3650"
               value="<?= $s('email_log_days', '30') ?>">
        <p class="hint">The record shown on <a href="emails.php">Emails</a> of what was sent to whom,
          and why. This setting always existed and was actually enforced by the nightly prune - it
          just had nowhere on this page to be changed.</p></div>
    </div>
  </div>
  <div class="row2">
    <div><label>Repair gaps in stored candles</label>
      <select name="auto_backfill_gaps">
        <option value="1" <?= Database::setting('auto_backfill_gaps', '1') === '1' ? 'selected' : '' ?>>Enabled — re-fetch missing bars automatically</option>
        <option value="0" <?= Database::setting('auto_backfill_gaps', '1') === '0' ? 'selected' : '' ?>>Disabled — record the gap only</option>
      </select>
      <p class="hint">A series with holes produces wrong indicators silently. Either way the gap is
        flagged on the status page; this decides whether it is also repaired.</p>
    </div>
  </div>
  <?php // API rate limiting and the proxy header moved to Security > Abuse
        // protection, next to the login throttle they interact with. This card
        // is how long data is kept and nothing else; the hint on the rate
        // limit even said "does not affect the login throttle above", written
        // when the two were together and left behind when they were not. ?>
</details>

  <?php // What this button does and does not do. The cards below it - the logo
        // upload, the two testers, the password change, the maintenance tools -
        // carry their own forms and their own buttons, and on a short section
        // like Security this bar lands in the middle of the page between them.
        // Saying so is one line; not saying it is an operator filling in a new
        // password and pressing the big blue button above it. ?>
  <div class="save-bar"><button class="btn" type="submit">Save all settings</button><span class="hint">Saves every section at once. Cards with their own button below save themselves.</span></div>
</form>

<details class="form-card" data-tab="site">
    <summary>Logo</summary>
  <?php $curLogo = Database::setting('custom_logo'); ?>
  <p style="margin:6px 0 10px">
    <?php // THIS WAS SHOWING THE WRONG DEFAULT.
          // assets/brand/logo.svg is not what actually renders anywhere a
          // custom logo has not been set - the header, the sidebar and
          // every login screen all fall back to the three-bar mark
          // (Chrome::mark() / View::brandMark()'s own fallback), never to
          // this file. An operator with no custom logo uploaded was shown a
          // preview of a badge their site does not display. ?>
    <span style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:14px;border:1px solid var(--border);background:var(--bg);vertical-align:top">
      <?php if ($curLogo !== ''): ?>
        <img src="../logo.php?v=<?= sma_e($curLogo) ?>" alt="Current logo" style="max-width:100%;max-height:100%;object-fit:contain">
      <?php else: ?>
        <?= \SignalMasterAi\Chrome::mark(32) ?>
      <?php endif; ?>
    </span>
    <span class="hint" style="display:inline;margin-left:10px;vertical-align:top"><?= $curLogo !== '' ? 'Custom logo active.' : 'Default logo active.' ?></span>
  </p>
  <form method="post" action="settings.php" enctype="multipart/form-data" style="margin-bottom:10px">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="logo_upload">
    <label>Upload a new logo - square works best (PNG/JPG/WEBP/GIF, max 2 MB). Used in the header,
      admin panel and browser tab.</label>
    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" required style="max-width:520px">
    <p style="margin-top:10px"><button class="btn" type="submit">Upload logo</button></p>
  </form>
  <?php if ($curLogo !== ''): ?>
  <form method="post" action="settings.php" onsubmit="return confirm('Remove the custom logo and go back to the default?')">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="logo_remove">
    <button class="btn small gray" type="submit">Remove custom logo</button>
  </form>
  <?php endif; ?>
</details>

<?php // The tester carries its own form, so it cannot sit inside the card it
      // tests - the big settings form would have to nest, which browsers do
      // not allow. It is the last card on the tab instead, and the card it
      // belongs to links down to it. ?>
<details class="form-card" data-tab="market" id="apitest">
    <summary>Test API endpoints</summary>
  <form method="post" action="settings.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="test_apis">
    <p class="hint" style="margin-bottom:10px">Pings every endpoint in your list (plus built-in backups in auto mode) and reports status and latency above the endpoint list.</p>
    <button class="btn gray" type="submit">Test all endpoints now</button>
  </form>
</details>

<?php if (\SignalMasterAi\Master::master('alerts')): ?><details class="form-card" data-tab="alerts" id="emailtest">
    <summary>Send test email</summary>
  <form method="post" action="settings.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="test_email">
    <div class="row2">
      <div><label>Send a test to</label><input type="text" name="test_to" placeholder="you@example.com"></div>
    </div>
    <p style="margin-top:12px"><button class="btn gray" type="submit">Send test email</button></p>
  </form>
</details><?php endif; ?>

<details class="form-card" data-tab="security" open>
    <summary>Change admin password</summary>
  <form method="post" action="settings.php">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="password">
    <label>Current password</label>
    <input type="password" name="current" autocomplete="current-password">
    <div class="row2">
      <div><label>New password (min 8 chars)</label><input type="password" name="new1" autocomplete="new-password"></div>
      <div><label>Repeat new password</label><input type="password" name="new2" autocomplete="new-password"></div>
    </div>
    <p style="margin-top:14px"><button class="btn" type="submit">Change password</button></p>
  </form>
</details>

<?php
// STORED CREDENTIALS.
//
// The operator cannot see the keys - that is the point of every other change
// here - so what they get instead is an honest account of where those keys
// are, what protects them, and the one thing they have to do about it.
$secStat = \SignalMasterAi\Secrets::status();
?>
<details class="form-card" data-tab="security" id="credentials" <?= ($secStat['unreadable'] > 0 || $secStat['plain'] > 0) ? 'open' : '' ?>>
    <summary>Stored credentials
      <?php if ($secStat['unreadable'] > 0): ?>
        <span class="chg-count" style="background:var(--down)"><?= (int)$secStat['unreadable'] ?></span>
      <?php endif; ?></summary>
  <?php if ($secStat['unreadable'] > 0): ?>
    <p class="hint" style="color:var(--down)"><strong><?= (int)$secStat['unreadable'] ?> of your
      <?= (int)$secStat['total'] ?> saved credentials cannot be read back.</strong> They were
      encrypted with a different key than the one this install has now &mdash; which happens when
      <code>data/secret-key.php</code> is replaced or restored from a different backup than the
      database. Nothing is recoverable from here: re-enter those keys and they will be saved against
      the current one. Until then the features that use them behave as if they were never
      configured.</p>
  <?php endif; ?>
  <table class="grid stack" style="margin-bottom:10px">
    <tr><td data-label="Credentials saved"><strong>Credentials saved</strong></td>
        <td><?= (int)$secStat['total'] ?></td></tr>
    <tr><td data-label="Encrypted at rest"><strong>Encrypted at rest</strong></td>
        <td><?= (int)$secStat['encrypted'] ?><?= $secStat['plain'] > 0
            ? ' <span class="hint">(' . (int)$secStat['plain'] . ' still stored as plain text)</span>' : '' ?></td></tr>
    <tr><td data-label="Key held in"><strong>Key held in</strong></td>
        <td><code><?= sma_e((string)$secStat['source']) ?></code></td></tr>
  </table>
  <?php if (!$secStat['available']): ?>
    <p class="hint">This install has <strong>no encryption key</strong>, so credentials are stored as
      plain text exactly as they were before. That happens when the data directory is not writable
      or PHP has no OpenSSL. It is not a new problem &mdash; it is the old one, unimproved &mdash;
      but it is worth fixing: make <code>data/</code> writable and the key is created on the next
      page load.</p>
  <?php else: ?>
    <p class="hint"><strong>Back this file up with your database.</strong> The keys and passwords in
      the settings table are encrypted with it, so a database restored without it keeps the site
      running but every credential has to be typed in again. Keeping the two together in the same
      backup is fine and is what most people will do &mdash; the protection is against the database
      alone travelling somewhere it should not: a dump you email yourself, a shared MySQL server, a
      copy on a laptop.</p>
    <p class="hint">What this does <em>not</em> protect against, stated plainly: anyone who can read
      your <code>data/</code> directory has the key and the database side by side. On shared hosting
      that is the same person who could read the plain text before. The layers that cover that case
      are the deny rules and the exposure check on this page, not this.</p>
  <?php endif; ?>
</details>

<details class="form-card" data-tab="security" id="datadir" open>
  <summary>Where your private files live</summary>
  <?php
  $ddInside = \SignalMasterAi\DataDir::insideWebRoot();
  $ddInv = \SignalMasterAi\DataDir::inventory(SMA_DATA_DIR);
  $ddSuggest = \SignalMasterAi\DataDir::suggestTarget();
  [$ddCan, $ddWhy] = \SignalMasterAi\DataDir::check($ddSuggest);
  $ddExp = \SignalMasterAi\Maintenance::dataExposed();
  $ddOpen = $ddExp['known'] && $ddExp['exposed'] !== [];
  ?>
  <p>Current location: <code><?= sma_e(SMA_DATA_DIR) ?></code>
    <span class="hint">(<?= (int)$ddInv['files'] ?> file(s))</span></p>
  <?php if (!$ddInside): ?>
    <p style="color:var(--good)"><strong>Above the web root.</strong> No web server configuration can serve
      these files, because there is no URL that reaches them. Nothing to do here.</p>
    <p class="hint">To go back, delete <code>data-path.php</code> from the site folder and move the
      directory back to <code>data/</code> yourself &mdash; in that order.</p>
  <?php else: ?>
    <?php if ($ddOpen): ?>
      <p style="color:var(--bad)"><strong>Readable over the web right now.</strong> The check fetched
        <?= sma_e(implode(' and ', $ddExp['exposed'])) ?> from your own address and got the file
        back. That includes the database: member emails, password hashes, API tokens and payment
        records.</p>
    <?php elseif ($ddExp['known']): ?>
      <p style="color:var(--good)">Not reachable over the web at the moment &mdash; your server is honouring
        the deny rules.</p>
    <?php else: ?>
      <p class="hint">The exposure check could not run from here, so whether these files are
        reachable is unknown.</p>
    <?php endif; ?>
    <p class="hint">These files sit inside the folder your web server publishes. A
      <code>.htaccess</code> in there denies them, and nginx never reads <code>.htaccess</code>
      while Apache ignores it unless <code>AllowOverride</code> is on. Moving them above the web
      root removes the question: the server has no path to them however it is configured, on this
      host and on the next one.</p>
    <form method="post" action="settings.php"
          onsubmit="return confirm('Move the data directory? The site will use the new location immediately.')">
      <input type="hidden" name="csrf" value="<?= $csrf ?>">
      <input type="hidden" name="act" value="move_data">
      <label>Move them to</label>
      <input type="text" name="data_target" value="<?= sma_e($ddSuggest) ?>" spellcheck="false">
      <p class="hint"><?= $ddCan
          ? 'This path is usable: it is above the web root, its parent is writable, and nothing is there yet.'
          : 'Suggested path cannot be used as-is &mdash; ' . sma_e($ddWhy) . ' Enter another.' ?></p>
      <p style="margin-top:12px"><button class="btn" type="submit">Move private files out of the web root</button></p>
      <p class="hint">The files are renamed in one step where the filesystem allows it, so there is
        no moment when half of them are in each place. Where it does not, they are copied, checked
        file-for-file and byte-for-byte, and only then removed. If anything fails &mdash; including
        writing the pointer the site needs to find them &mdash; everything is put back and nothing
        changes. <strong>Take a backup first anyway</strong>; it is one click above.</p>
    </form>
    <p class="hint">Prefer to do it in your server config? Deny the directory instead:
      nginx <code>location ^~ /data/ { deny all; return 404; }</code>, or Apache
      <code>&lt;Directory <?= sma_e(SMA_DATA_DIR) ?>&gt; Require all denied &lt;/Directory&gt;</code>.
      Reload this page afterwards and the check re-runs.</p>
  <?php endif; ?>
</details>

<details class="form-card" data-tab="security">
    <summary>Maintenance</summary>
  <form method="post" action="settings.php"
        onsubmit="return confirm('Clear all cached candles? They will be refetched from the API on demand.')">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="clear_cache">
    <button class="btn danger" type="submit">Clear market data cache</button>
  </form>
  <p style="margin-top:14px">
    <a class="btn gray" href="backup.php">Download database backup</a>
    <span class="hint" style="display:inline;margin-left:8px">Full copy of everything - settings,
      members, payments, signals. Grab one before upgrades.</span>
  </p>
</details>
<script>
(function () {
  var tabs = document.querySelectorAll('#settingsTabs a');
  var cards = Array.prototype.slice.call(document.querySelectorAll('.form-card[data-tab]'));
  var find = document.getElementById('settingsFind');
  var count = document.getElementById('settingsFindCount');
  var tabBar = document.getElementById('settingsTabs');
  var lede = document.getElementById('settingsLede');
  var saveBar = document.querySelector('#allSettings .save-bar');
  var mainForm = document.getElementById('allSettings');

  // How many cards are in each section, counted from what was actually
  // rendered. Counting in PHP would mean repeating every master-switch
  // condition that decides whether a card is emitted at all, and the two
  // copies would disagree the first time one of them was edited. Sections a
  // master switch has emptied say so rather than showing a bare zero.
  tabs.forEach(function (a) {
    var t = a.getAttribute('href').slice(1);
    var n = cards.filter(function (c) { return c.dataset.tab === t; }).length;
    var slot = a.querySelector('.tab-n');
    if (slot) { slot.textContent = n; }
    a.title = n + ' section' + (n === 1 ? '' : 's') + ' in ' + a.querySelector('b').textContent;
  });

  // Searching and tabbing both decide which cards are visible, so one thing
  // owns that decision. Two scripts each setting display on the same elements
  // is how a search box ends up showing a result the tab script immediately
  // hides again.
  //
  // Matching runs over the card's text AND its field names, so "fee" finds
  // round_trip_cost_pct even though the word never appears in its label - the
  // thing an operator remembers is rarely the wording on screen.
  var haystack = cards.map(function (c) {
    var names = Array.prototype.slice.call(c.querySelectorAll('[name]'))
      .map(function (el) { return el.getAttribute('name') || ''; }).join(' ');
    return (c.textContent + ' ' + names).toLowerCase();
  });
  var query = '';

  function show(tab) {
    if (query.length >= 2) {
      var hits = 0;
      if (tabBar) { tabBar.style.display = 'none'; }
      if (lede) { lede.style.display = 'none'; }
      cards.forEach(function (c, i) {
        var match = haystack[i].indexOf(query) !== -1;
        c.style.display = match ? '' : 'none';
        if (match) { c.open = true; hits++; }
      });
      if (count) {
        count.textContent = hits
          ? hits + ' section' + (hits === 1 ? '' : 's') + ' match — sections are off while searching'
          : 'nothing matches “' + find.value.trim() + '”';
      }
      // A search can turn up cards from the main form and cards that carry
      // their own, so the save bar has to follow the results too.
      syncSaveBar();
      return;
    }
    if (tabBar) { tabBar.style.display = ''; }
    if (lede) { lede.style.display = ''; }
    if (count) { count.textContent = ''; }
    tabs.forEach(function (a) {
      var on = a.getAttribute('href') === '#' + tab;
      a.classList.toggle('on', on);
      a.setAttribute('aria-current', on ? 'true' : 'false');
      if (on && lede) { lede.textContent = a.getAttribute('data-lede') || ''; }
      // Seven pills do not fit across a phone, so the strip scrolls. Arriving
      // on settings.php#security with the strip scrolled to the left means the
      // one section you asked for is the one off screen. Nudged with scrollLeft
      // rather than scrollIntoView, which would also scroll the page.
      if (on && tabBar && tabBar.scrollWidth > tabBar.clientWidth) {
        var l = a.offsetLeft - tabBar.offsetLeft;
        if (l < tabBar.scrollLeft || l + a.offsetWidth > tabBar.scrollLeft + tabBar.clientWidth) {
          tabBar.scrollLeft = Math.max(0, l - 12);
        }
      }
    });
    cards.forEach(function (c) { c.style.display = c.dataset.tab === tab ? '' : 'none'; });
    syncSaveBar();
  }

  // "Save all settings" is the submit button of the big form. On Overview -
  // master switches and the changed table, both of which carry their own
  // forms and their own buttons - nothing on screen belongs to it, and a
  // sticky bar offering to save a section it cannot save is a bar that
  // teaches an operator to stop trusting it.
  function syncSaveBar() {
    if (!saveBar || !mainForm) { return; }
    var any = cards.some(function (c) {
      return c.style.display !== 'none' && mainForm.contains(c);
    });
    saveBar.style.display = any ? '' : 'none';
  }

  // The hash is either a section name or the id of one card. Both happen: the
  // panel links to settings.php#market, and this page's own POST handlers
  // redirect to settings.php#masters and settings.php#changed. Before
  // Overview existed those two ids matched no section, fell through to the
  // default, and dropped you on a page whose top said "Site" - after an action
  // whose result was somewhere else entirely. A card id now opens the section
  // that card lives in and takes you to it.
  function currentTab() {
    var h = (location.hash || '').slice(1);
    if (!/^[\w-]+$/.test(h)) { return 'overview'; }
    if (document.querySelector('.form-card[data-tab="' + h + '"]')) { return h; }
    var el = document.getElementById(h);
    var card = el ? el.closest('.form-card[data-tab]') : null;
    return card ? card.dataset.tab : 'overview';
  }

  // Landing on a card by id: open it and scroll to it, once, on arrival. Not
  // on every hashchange - clicking a section in the bar rewrites the hash, and
  // scrolling the page from under someone who just pressed a tab is wrong.
  //
  // Accordions inside a card count too. The engine panel sends people here for
  // one specific control - "set it under Settings" - and this page is four
  // thousand pixels of collapsed sections, so landing on the right tab with
  // the right fold still shut is not an answer. Every enclosing <details> is
  // opened, however deep, because opening the inner one while its parent stays
  // shut reveals nothing.
  function revealHashCard() {
    var h = (location.hash || '').slice(1);
    if (!/^[\w-]+$/.test(h)) { return; }
    var el = document.getElementById(h);
    if (!el || el.tagName !== 'DETAILS') { return; }
    for (var n = el; n; n = n.parentElement.closest('details')) {
      n.open = true;
      if (!n.parentElement) { break; }
    }
    el.scrollIntoView({ block: 'start' });
  }

  tabs.forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var t = a.getAttribute('href').slice(1);
      history.replaceState(null, '', '#' + t);
      show(t);
      // Switching section while halfway down a four-thousand-pixel one used to
      // leave you at that scroll position, looking at the middle of something
      // else. The bar is sticky, so the top of the new section is where the
      // bar already is.
      if (tabBar) { tabBar.scrollIntoView({ block: 'start' }); }
    });
  });
  if (find) {
    find.addEventListener('input', function () {
      query = find.value.trim().toLowerCase();
      show(currentTab());
    });
  }
  // A hash change is always a link someone followed, never a tab press: the
  // tab handler rewrites the hash with replaceState, which does not fire this.
  // So a card cross-link ("set it under Timeframes") has to open and scroll to
  // its target the same way arriving on the URL does - switching section and
  // leaving the reader at the top of it, looking for the card the link named,
  // is the trip the link was supposed to save them.
  window.addEventListener('hashchange', function () {
    show(currentTab());
    revealHashCard();
  });
  // Which tab a save lands back on. Read at submit time, not baked in on
  // load, because the operator may have switched tabs since the page opened.
  if (mainForm) {
    mainForm.addEventListener('submit', function () {
      var tabField = document.getElementById('allSettingsTab');
      if (tabField) { tabField.value = currentTab(); }
    });
  }
  show(currentTab());
  revealHashCard();
})();
</script>
<?php admin_footer(); ?>
