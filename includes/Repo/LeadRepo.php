<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Database;

/** Contact messages, quote/journey requests and newsletter subscribers. */
final class LeadRepo
{
    private function db(): Database
    {
        return Database::instance();
    }

    // -----------------------------------------------------------------
    // Contact messages
    // -----------------------------------------------------------------

    public function createMessage(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->db()->insert('contact_messages', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function messages(int $page, int $perPage, string $search = '', string $status = ''): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(name LIKE ? OR email LIKE ? OR company LIKE ? OR message LIKE ?)';
            $like     = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where[]  = 'status = ?';
            $params[] = $status;
        }
        $w      = implode(' AND ', $where);
        $total  = $this->db()->int("SELECT COUNT(*) FROM contact_messages WHERE $w", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $items  = $this->db()->all(
            "SELECT * FROM contact_messages WHERE $w ORDER BY created_at DESC, id DESC LIMIT " . (int) $perPage . " OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    public function message(int $id): ?array
    {
        return $this->db()->first('SELECT * FROM contact_messages WHERE id = ?', [$id]);
    }

    public function updateMessage(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db()->update('contact_messages', $data, 'id', $id);
    }

    public function deleteMessage(int $id): void
    {
        $this->db()->delete('contact_messages', 'id', $id);
    }

    /** @return array<int,array<string,mixed>> everything matching a filter, for CSV export */
    public function exportMessages(string $status = ''): array
    {
        if ($status !== '') {
            return $this->db()->all('SELECT * FROM contact_messages WHERE status = ? ORDER BY created_at DESC', [$status]);
        }
        return $this->db()->all('SELECT * FROM contact_messages ORDER BY created_at DESC');
    }

    // -----------------------------------------------------------------
    // Quote / journey requests
    // -----------------------------------------------------------------

    public function createQuote(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $data['reference'] = $data['reference'] ?? $this->nextReference('TBQ', 'quote_requests');
        return $this->db()->insert('quote_requests', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function quotes(int $page, int $perPage, string $search = '', string $status = '', string $source = ''): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(q.name LIKE ? OR q.email LIKE ? OR q.business_name LIKE ? OR q.reference LIKE ?)';
            $like     = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where[]  = 'q.status = ?';
            $params[] = $status;
        }
        if ($source !== '') {
            $where[]  = 'q.source = ?';
            $params[] = $source;
        }
        $w      = implode(' AND ', $where);
        $total  = $this->db()->int("SELECT COUNT(*) FROM quote_requests q WHERE $w", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $items  = $this->db()->all(
            "SELECT q.*, p.name AS package_name, i.name AS industry_name
             FROM quote_requests q
             LEFT JOIN packages p ON p.id = q.package_id
             LEFT JOIN industries i ON i.id = q.industry_id
             WHERE $w ORDER BY q.created_at DESC, q.id DESC LIMIT " . (int) $perPage . " OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    public function quote(int $id): ?array
    {
        return $this->db()->first(
            'SELECT q.*, p.name AS package_name, i.name AS industry_name
             FROM quote_requests q
             LEFT JOIN packages p ON p.id = q.package_id
             LEFT JOIN industries i ON i.id = q.industry_id
             WHERE q.id = ?',
            [$id]
        );
    }

    public function updateQuote(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db()->update('quote_requests', $data, 'id', $id);
    }

    public function deleteQuote(int $id): void
    {
        $this->db()->delete('quote_requests', 'id', $id);
    }

    /** @return array<int,array<string,mixed>> */
    public function exportQuotes(string $status = ''): array
    {
        if ($status !== '') {
            return $this->db()->all('SELECT * FROM quote_requests WHERE status = ? ORDER BY created_at DESC', [$status]);
        }
        return $this->db()->all('SELECT * FROM quote_requests ORDER BY created_at DESC');
    }

    // -----------------------------------------------------------------
    // Newsletter
    // -----------------------------------------------------------------

    /** @return array{status:string,id:int} */
    public function subscribe(string $email, string $name, string $source, string $ip): array
    {
        $existing = $this->db()->first('SELECT id, status FROM newsletter_subscribers WHERE email = ?', [$email]);
        $now      = date('Y-m-d H:i:s');
        if ($existing !== null) {
            if ((string) $existing['status'] === 'subscribed') {
                return ['status' => 'already', 'id' => (int) $existing['id']];
            }
            $this->db()->update('newsletter_subscribers', ['status' => 'subscribed', 'updated_at' => $now], 'id', (int) $existing['id']);
            return ['status' => 'resubscribed', 'id' => (int) $existing['id']];
        }
        $id = $this->db()->insert('newsletter_subscribers', [
            'email'         => $email,
            'name'          => $name,
            'status'        => 'subscribed',
            'source'        => $source,
            'confirm_token' => \Techbiss\Core\Str::randomToken(16),
            'ip_address'    => $ip,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        return ['status' => 'new', 'id' => $id];
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function subscribers(int $page, int $perPage, string $search = '', string $status = ''): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(email LIKE ? OR name LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($status !== '') {
            $where[]  = 'status = ?';
            $params[] = $status;
        }
        $w      = implode(' AND ', $where);
        $total  = $this->db()->int("SELECT COUNT(*) FROM newsletter_subscribers WHERE $w", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $items  = $this->db()->all(
            "SELECT * FROM newsletter_subscribers WHERE $w ORDER BY created_at DESC LIMIT " . (int) $perPage . " OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    public function deleteSubscriber(int $id): void
    {
        $this->db()->delete('newsletter_subscribers', 'id', $id);
    }

    public function setSubscriberStatus(int $id, string $status): void
    {
        $this->db()->update('newsletter_subscribers', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')], 'id', $id);
    }

    /** @return array<int,array<string,mixed>> */
    public function exportSubscribers(): array
    {
        return $this->db()->all('SELECT email, name, status, source, created_at FROM newsletter_subscribers ORDER BY created_at DESC');
    }

    // -----------------------------------------------------------------

    /** Sequential, human-readable reference such as TBQ-2026-0042. */
    public function nextReference(string $prefix, string $table): string
    {
        $allowed = ['quote_requests' => 'quote_requests', 'package_purchases' => 'package_purchases'];
        $t       = $allowed[$table] ?? 'quote_requests';
        $year    = date('Y');
        $count   = $this->db()->int("SELECT COUNT(*) FROM `$t` WHERE YEAR(created_at) = ?", [(int) $year]);
        do {
            $ref    = sprintf('%s-%s-%04d', $prefix, $year, ++$count);
            $taken  = $this->db()->int("SELECT COUNT(*) FROM `$t` WHERE reference = ?", [$ref]) > 0;
        } while ($taken && $count < 100000);
        return $ref;
    }

    /**
     * Simple per-IP submission throttle. Public forms are open by definition, so
     * this limits how often the same address can post regardless of any token.
     */
    public function recentSubmissionCount(string $ip, int $seconds = 300): int
    {
        $since = date('Y-m-d H:i:s', time() - $seconds);
        $a = $this->db()->int('SELECT COUNT(*) FROM contact_messages WHERE ip_address = ? AND created_at > ?', [$ip, $since]);
        $b = $this->db()->int('SELECT COUNT(*) FROM quote_requests   WHERE ip_address = ? AND created_at > ?', [$ip, $since]);
        return $a + $b;
    }
}
