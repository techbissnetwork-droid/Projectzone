<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Database;

/**
 * Enquiries about premade projects.
 *
 * Kept separate from package purchases because the lifecycle genuinely differs:
 * a premade project carries no published price. Someone asks about one, the
 * price is agreed over WhatsApp or email, and an administrator records the
 * figure here afterwards — quoted_amount stays NULL until they do.
 *
 * No money is ever captured on the website; payment is marked paid by hand once
 * it has actually arrived.
 */
final class ProjectOrderRepo
{
    public const PAYMENT_STATUSES = ['pending', 'paid', 'refunded', 'cancelled'];
    public const ORDER_STATUSES   = ['new', 'discussing', 'quoted', 'in_setup', 'delivered', 'cancelled'];

    /** Human labels for the enquiry lifecycle. @return array<string,string> */
    public static function orderStatusLabels(): array
    {
        return [
            'new'        => 'New enquiry',
            'discussing' => 'In conversation',
            'quoted'     => 'Quoted',
            'in_setup'   => 'In setup',
            'delivered'  => 'Delivered',
            'cancelled'  => 'Cancelled',
        ];
    }

    /** How the customer asked to be reached. @return array<string,string> */
    public static function contactLabels(): array
    {
        return ['whatsapp' => 'WhatsApp', 'email' => 'Email', 'phone' => 'Phone'];
    }

    private function db(): Database
    {
        return Database::instance();
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->db()->insert('project_orders', $data + ['created_at' => $now, 'updated_at' => $now]);
    }

    public function find(int $id): ?array
    {
        return $this->db()->first(
            'SELECT o.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                    c.business_name, c.country AS customer_country, p.slug AS project_slug
             FROM project_orders o
             JOIN customers c ON c.id = o.customer_id
             LEFT JOIN premade_projects p ON p.id = o.project_id
             WHERE o.id = ?',
            [$id]
        );
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function paginate(int $page, int $perPage, string $search = '', string $paymentStatus = '', string $orderStatus = ''): array
    {
        $where  = ['1'];
        $params = [];
        if ($search !== '') {
            $where[]  = '(o.reference LIKE ? OR c.name LIKE ? OR c.email LIKE ? OR o.project_name LIKE ?)';
            $like     = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($paymentStatus !== '') {
            $where[]  = 'o.payment_status = ?';
            $params[] = $paymentStatus;
        }
        if ($orderStatus !== '') {
            $where[]  = 'o.order_status = ?';
            $params[] = $orderStatus;
        }
        $w      = implode(' AND ', $where);
        $total  = $this->db()->int("SELECT COUNT(*) FROM project_orders o JOIN customers c ON c.id = o.customer_id WHERE $w", $params);
        $offset = max(0, (min($page, 1000000) - 1) * $perPage); // clamp page: an absurd ?page must not overflow the int product into a float and corrupt the SQL OFFSET
        $items  = $this->db()->all(
            "SELECT o.*, c.name AS customer_name, c.email AS customer_email, c.business_name
             FROM project_orders o JOIN customers c ON c.id = o.customer_id
             WHERE $w ORDER BY o.created_at DESC, o.id DESC LIMIT " . (int) $perPage . " OFFSET $offset",
            $params
        );
        return ['items' => $items, 'total' => $total];
    }


    public function update(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db()->update('project_orders', $data, 'id', $id);
    }

    public function delete(int $id): void
    {
        $this->db()->delete('project_orders', 'id', $id);
    }

    /** @return array<string,int|float> */
    public function summary(): array
    {
        return [
            'total'     => $this->db()->int('SELECT COUNT(*) FROM project_orders'),
            'pending'   => $this->db()->int("SELECT COUNT(*) FROM project_orders WHERE payment_status = 'pending'"),
            'paid'      => $this->db()->int("SELECT COUNT(*) FROM project_orders WHERE payment_status = 'paid'"),
            'new'       => $this->db()->int("SELECT COUNT(*) FROM project_orders WHERE order_status = 'new'"),
            'in_setup'  => $this->db()->int("SELECT COUNT(*) FROM project_orders WHERE order_status = 'in_setup'"),
            'delivered' => $this->db()->int("SELECT COUNT(*) FROM project_orders WHERE order_status = 'delivered'"),
            'revenue'   => (float) $this->db()->value("SELECT COALESCE(SUM(quoted_amount),0) FROM project_orders WHERE payment_status = 'paid'", [], 0),
            'pipeline'  => (float) $this->db()->value("SELECT COALESCE(SUM(quoted_amount),0) FROM project_orders WHERE payment_status = 'pending' AND quoted_amount IS NOT NULL", [], 0),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function export(): array
    {
        return $this->db()->all(
            'SELECT o.reference, c.name AS customer, c.email, c.phone, o.project_name,
                    o.preferred_contact, o.currency, o.quoted_amount, o.domain_name,
                    o.payment_status, o.order_status, o.ordered_at, o.delivered_at
             FROM project_orders o JOIN customers c ON c.id = o.customer_id
             ORDER BY o.created_at DESC'
        );
    }
}
