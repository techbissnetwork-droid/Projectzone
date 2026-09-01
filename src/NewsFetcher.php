<?php
declare(strict_types=1);

namespace SignalMasterAi;

use PDO;

/**
 * Fully automated news aggregation from trusted sources.
 *
 * Sources live in the news_sources table (admin-managed) and are refreshed
 * lazily whenever news is requested and the per-source TTL has expired - no
 * manual action is ever needed. An optional cron endpoint (cron.php) can
 * refresh in the background for busy sites.
 *
 * Legal posture: we store and display only what feeds are published to
 * syndicate - headline, link and a short plain-text excerpt - always with
 * source attribution, always linking readers to the original publisher.
 * Full articles are never copied. Old headlines are pruned automatically.
 */
class NewsFetcher
{
    private PDO $pdo;
    private int $ttl;

    public function __construct()
    {
        $this->pdo = Database::pdo();
        $this->ttl = max(60, (int)Database::setting('news_cache_ttl', '300'));
    }

    /** Refresh every enabled source whose cache has expired. Never throws. */
    public function refreshIfStale(): array
    {
        $report = [];
        $now = time();
        $stmt = $this->pdo->query('SELECT * FROM news_sources WHERE enabled = 1');
        foreach ($stmt->fetchAll() as $src) {
            if ($now - (int)$src['fetched_at'] < $this->ttl) {
                continue;
            }
            // Mark first so parallel requests don't hammer a slow feed.
            $this->pdo->prepare('UPDATE news_sources SET fetched_at = ? WHERE id = ?')
                ->execute([$now, $src['id']]);
            try {
                $added = $src['kind'] === 'ff_calendar'
                    ? $this->fetchCalendar($src)
                    : $this->fetchRss($src);
                $report[$src['name']] = "ok ($added items)";
            } catch (\Throwable $e) {
                $report[$src['name']] = 'error: ' . $e->getMessage();
            }
        }
        $this->prune();
        return $report;
    }

    /** Latest stored headlines, newest first. */
    /**
     * Newest headlines, with every link re-checked on the way out.
     *
     * The scheme is validated when an item is INGESTED, and that was treated
     * as sufficient. It is not: the check guards one path into the table, and
     * the browser sets whatever comes back as an anchor's href. A
     * `javascript:` URL surviving in that column - from a restored backup, a
     * hand-edited row, an import written later by someone who did not know
     * about the ingest rule - becomes a click that runs script on the site's
     * own origin, delivered by the site to every visitor.
     *
     * Re-checking at read costs one preg_match per headline and does not care
     * how the row got there, which is the property that matters.
     */
    public function latest(int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT source_name, title, url, summary, published_at
             FROM news_items ORDER BY published_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $out = [];
        foreach ($stmt as $row) {
            if (!preg_match('#^https?://#i', (string)($row['url'] ?? ''))) {
                // Keep the headline, drop the link. A story with no link is
                // still information; a link nobody vetted is a liability.
                $row['url'] = '';
            }
            $out[] = $row;
        }
        return $out;
    }

    /** Upcoming calendar events from now on, soonest first. */
    public function upcomingEvents(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT title, country, impact, event_time, forecast, previous, url
             FROM calendar_events WHERE event_time >= ? ORDER BY event_time ASC LIMIT ?'
        );
        $stmt->bindValue(1, time() - 900);          // include events started <15m ago ("happening now")
        $stmt->bindValue(2, max(1, min(60, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ------------------------------------------------------------ internals

    private function http(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT      => 'SignalMasterAi/1.0 (+news aggregator; headline/link only)',
            CURLOPT_ENCODING       => '',
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false) {
            throw new \RuntimeException("request failed: $err");
        }
        if ($code !== 200) {
            throw new \RuntimeException("HTTP $code");
        }
        return $body;
    }

    private function fetchRss(array $src): int
    {
        $body = $this->http($src['url']);
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            throw new \RuntimeException('invalid XML');
        }

        // RSS 2.0 (<channel><item>) or Atom (<entry>)
        $items = [];
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $it) {
                $items[] = [
                    'title' => (string)$it->title,
                    'url'   => trim((string)$it->link),
                    'desc'  => (string)($it->description ?? ''),
                    'date'  => (string)($it->pubDate ?? ''),
                ];
            }
        } elseif (isset($xml->entry)) {
            foreach ($xml->entry as $it) {
                $href = '';
                foreach ($it->link as $l) {
                    $attrs = $l->attributes();
                    if (!isset($attrs['rel']) || (string)$attrs['rel'] === 'alternate') {
                        $href = (string)$attrs['href'];
                        break;
                    }
                }
                $items[] = [
                    'title' => (string)$it->title,
                    'url'   => $href,
                    'desc'  => (string)($it->summary ?? ''),
                    'date'  => (string)($it->updated ?? $it->published ?? ''),
                ];
            }
        }

        $now = time();
        $ins = $this->pdo->prepare(
            ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT OR IGNORE'
                : 'INSERT IGNORE')
            . ' INTO news_items (source_name, title, url, url_hash, summary, published_at, fetched_at)
               VALUES (?,?,?,?,?,?,?)'
        );
        $added = 0;
        foreach (array_slice($items, 0, 40) as $it) {
            $title = self::cleanText($it['title'], 280);
            $url = $it['url'];
            if ($title === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            $ts = $it['date'] !== '' ? strtotime($it['date']) : false;
            $ins->execute([
                $src['name'],
                $title,
                mb_substr($url, 0, 500),
                sha1($url),
                self::cleanText($it['desc'], 240),   // short excerpt only
                $ts !== false ? $ts : $now,
                $now,
            ]);
            $added += $ins->rowCount();
        }
        return $added;
    }

    private function fetchCalendar(array $src): int
    {
        $body = $this->http($src['url']);
        // Feed declares windows-1252; normalise to UTF-8 for the XML parser.
        if (stripos($body, 'windows-1252') !== false) {
            $converted = @iconv('windows-1252', 'UTF-8//IGNORE', $body);
            if ($converted !== false) {
                $body = str_ireplace('windows-1252', 'UTF-8', $converted);
            }
        }
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($xml === false || !isset($xml->event)) {
            throw new \RuntimeException('invalid calendar XML');
        }

        // ForexFactory feed times are US Eastern.
        $tz = new \DateTimeZone('America/New_York');
        $rows = [];
        foreach ($xml->event as $ev) {
            $date = trim((string)$ev->date);          // e.g. 07-24-2026
            $time = trim((string)$ev->time);          // e.g. 8:30am | All Day | Tentative
            if ($date === '') {
                continue;
            }
            $when = null;
            if (preg_match('/^\d{1,2}:\d{2}(am|pm)$/i', $time)) {
                $dt = \DateTime::createFromFormat('m-d-Y g:ia', "$date $time", $tz);
                if ($dt !== false) {
                    $when = $dt->getTimestamp();
                }
            }
            if ($when === null) {
                $dt = \DateTime::createFromFormat('m-d-Y H:i', "$date 00:00", $tz);
                if ($dt === false) {
                    continue;
                }
                $when = $dt->getTimestamp();
            }
            $rows[] = [
                self::cleanText((string)$ev->title, 200),
                self::cleanText((string)$ev->country, 12),
                self::cleanText((string)$ev->impact, 16),
                $when,
                self::cleanText((string)$ev->forecast, 40),
                self::cleanText((string)$ev->previous, 40),
                mb_substr(trim((string)$ev->url), 0, 500),
            ];
        }
        if (!$rows) {
            throw new \RuntimeException('calendar feed contained no events');
        }

        // Replace wholesale - the feed is the source of truth for the week.
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM calendar_events');
            $ins = $this->pdo->prepare(
                'INSERT INTO calendar_events (title, country, impact, event_time, forecast, previous, url)
                 VALUES (?,?,?,?,?,?,?)'
            );
            foreach ($rows as $r) {
                $ins->execute($r);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return count($rows);
    }

    /** Automatic retention: drop old headlines and past events. */
    private function prune(): void
    {
        $days = max(1, (int)Database::setting('news_retention_days', '30'));
        $this->pdo->prepare('DELETE FROM news_items WHERE published_at < ?')
            ->execute([time() - $days * 86400]);
        $this->pdo->prepare('DELETE FROM calendar_events WHERE event_time < ?')
            ->execute([time() - 2 * 86400]);
    }

    private static function cleanText(string $s, int $max): string
    {
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? '');
        if (mb_strlen($s) > $max) {
            $s = rtrim(mb_substr($s, 0, $max - 1)) . '…';
        }
        return $s;
    }
}
