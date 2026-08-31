<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Database;

/**
 * Prepaid package purchases.
 *
 * Payment is never captured on the website — a purchase is recorded as a
 * request with a pending payment status, and an administrator confirms it once
 * money has actually arrived through whichever channel was agreed.
 */
final class PurchaseRepo
{
    public const PAYMENT_STATUSES = ['pending', 'paid', 'refunded', 'cancelled'];
    public const PACKAGE_STATUSES = ['pending', 'active', 'expiring', 'expired', 'cancelled'];
    public const RENEWAL_STATUSES = ['not_due', 'due', 'renewed', 'declined'];

    private function db(): Database
    {
        return Database::instance();
    }

    public function create(array $data, array $addons = []): int
    {
        return $this->db()->transaction(function (Database $db) use ($data, $addons): int {
            $now = date('Y-m-d H:i:s');
            $id  = $db->insert('package_purchases', $data + ['created_at' => $now, 'updated_at' => $now]);
            foreach ($addons as $addon) {
                $db->insert('purchase_addons', [
                    'purchase_id' => $id,
                    'addon_id'    => isset($addon['id']) ? (int) $addon['id'] : null,
                    'name'        => mb_substr((string) $addon['name'], 0, 190),
                    'price'       => round((float) $addon['price'], 2),
                ]);
            }
            return $id;
        });
    }

    public function find(int $id): ?array
    {
        $row = $this->db()->first(
            'SELECT p.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                    c.business_name, c.country AS customer_country, pk.slug AS package_slug
             FROM package_purchases p
             JOIN customers c ON c.id = p.customer_id
             LEFT JOIN packages pk ON pk.id = p.package_id
             WHERE p.id = ?',
            [$id]
        );
        if ($row !== null) {
            $row['addons'] = $this->addons($id);
        }
        return $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function addons(int $purchaseId): array
    {
        return $this->db()->all('SELECT * FROM purchase_addons WHERE purchase_id = ? ORDER BY id', [$purchaseId]);
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function paginate(int $page, int $perPage, string $search = '', string $paymentStatus = '', string $packageStatus = ''): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(p.reference LIKE ? OR c.name LIKE ? OR c.email LIKE ? OR p.package_name LIKE ?)';
            $like     = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($paymentStatus !== '') {
            $where[]  = 'p.payment_status = ?';
            $params[] = $paymentStatus;
        }
        if ($packageStatus !== '') {
            $where[]  = 'p.package_status = ?';
            $params[] = $packageStatus;
        }
        $w      = implode(' AND ', $where);
        $total  = $this->db()->int("SELECT COUNT(*) FROM package_purchases p JOIN customers c ON c.id = p.customer_id WHERE $w", $params);
        $offset = max(0, (min($page, 1000000) - 1) * $perPage); // clamp page: an absurd ?page must not overflow the int product into a float and corrupt the SQL OFFSET
        $items  = $this->db()->all(
            "SELECT p.*, c.name AS customer_name, c.email AS customer_email, c.business_name
             FROM package_purchases p JOIN customers c ON c.id = p.customer_id
             WHERE $w ORDER BY p.created_at DESC, p.id DESC LIMIT " . (int) $perPage . " OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total];
    }

    /** @return array<int,array<string,mixed>> */
    public function forCustomer(int $customerId): array
    {
        return $this->db()->all(
            'SELECT * FROM package_purchases WHERE customer_id = ? ORDER BY created_at DESC',
            [$customerId]
        );
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db()->update('package_purchases', $data, 'id', $id);
    }

    public function delete(int $id): void
    {
        $this->db()->delete('package_purchases', 'id', $id);
    }

    /** Push an expiry date out by N months — the manual validity extension. */
    public function extend(int $id, int $months): bool
    {
        $row = $this->db()->first('SELECT expires_at, starts_at FROM package_purchases WHERE id = ?', [$id]);
        if ($row === null || $months <= 0) {
            return false;
        }
        $base = $row['expires_at'] ?: ($row['starts_at'] ?: date('Y-m-d'));
        $new  = \Techbiss\Core\Dates::addMonths((string) $base, $months);
        $this->update($id, ['expires_at' => $new, 'package_status' => 'active', 'renewal_status' => 'renewed']);
        return true;
    }

    /**
     * Recompute lifecycle statuses from the stored dates. Called on dashboard
     * load so "Expiring Soon" and "Expired" reflect reality without a cron job.
     */
    public function refreshStatuses(int $expiringWindowDays = 30): void
    {
        $today    = date('Y-m-d');
        $soon     = date('Y-m-d', time() + $expiringWindowDays * 86400);
        $this->db()->run(
            "UPDATE package_purchases SET package_status = 'expired', renewal_status = 'due'
             WHERE expires_at IS NOT NULL AND expires_at < ? AND package_status IN ('active','expiring')",
            [$today]
        );
        $this->db()->run(
            "UPDATE package_purchases SET package_status = 'expiring', renewal_status = 'due'
             WHERE expires_at IS NOT NULL AND expires_at >= ? AND expires_at <= ? AND package_status = 'active'",
            [$today, $soon]
        );
    }

    /** @return array<string,int|float> */
    public function summary(): array
    {
        return [
            'total'      => $this->db()->int('SELECT COUNT(*) FROM package_purchases'),
            'pending'    => $this->db()->int("SELECT COUNT(*) FROM package_purchases WHERE payment_status = 'pending'"),
            'paid'       => $this->db()->int("SELECT COUNT(*) FROM package_purchases WHERE payment_status = 'paid'"),
            'active'     => $this->db()->int("SELECT COUNT(*) FROM package_purchases WHERE package_status = 'active'"),
            'expiring'   => $this->db()->int("SELECT COUNT(*) FROM package_purchases WHERE package_status = 'expiring'"),
            'expired'    => $this->db()->int("SELECT COUNT(*) FROM package_purchases WHERE package_status = 'expired'"),
            'revenue'    => (float) $this->db()->value("SELECT COALESCE(SUM(total_amount),0) FROM package_purchases WHERE payment_status = 'paid'", [], 0),
            'pipeline'   => (float) $this->db()->value("SELECT COALESCE(SUM(total_amount),0) FROM package_purchases WHERE payment_status = 'pending'", [], 0),
            'savings'    => (float) $this->db()->value("SELECT COALESCE(SUM(discount_amount),0) FROM package_purchases WHERE payment_status = 'paid'", [], 0),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function export(): array
    {
        return $this->db()->all(
            'SELECT p.reference, c.name AS customer, c.email, p.package_name, p.currency, p.regular_price,
                    p.prepaid_price, p.addons_total, p.discount_amount, p.total_amount,
                    p.payment_status, p.package_status, p.purchased_at, p.starts_at, p.expires_at
             FROM package_purchases p JOIN customers c ON c.id = p.customer_id
             ORDER BY p.created_at DESC'
        );
    }
}
