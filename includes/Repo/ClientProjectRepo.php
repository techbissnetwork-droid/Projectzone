<?php
declare(strict_types=1);

namespace Techbiss\Repo;

/**
 * The job book (`client_projects`) — never shown on the website, kept so a
 * client can be contacted again about renewals and maintenance.
 */
final class ClientProjectRepo extends BaseRepo
{
    protected string $table = 'client_projects';

    /**
     * Every domain, hosting, SSL certificate or maintenance date due within
     * $days, or already overdue, across every project still open. Four date
     * columns live on the same row and any of them can come due
     * independently, so each is its own branch of the union rather than one
     * column — a project with both its domain and its SSL certificate
     * expiring the same week comes back as two rows.
     *
     * @return array<int,array<string,mixed>>
     */
    public function dueSoon(int $days = 30): array
    {
        $days = max(1, $days);
        return $this->db()->all(
            "SELECT * FROM (
                SELECT p.id, p.name, p.status, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
                       'Domain' AS due_type, p.domain_renews_on AS due_date
                FROM client_projects p LEFT JOIN customers c ON c.id = p.customer_id
                WHERE p.domain_renews_on IS NOT NULL AND p.status <> 'ended'
                UNION ALL
                SELECT p.id, p.name, p.status, c.name, c.email, c.phone,
                       'Hosting', p.hosting_renews_on
                FROM client_projects p LEFT JOIN customers c ON c.id = p.customer_id
                WHERE p.hosting_renews_on IS NOT NULL AND p.status <> 'ended'
                UNION ALL
                SELECT p.id, p.name, p.status, c.name, c.email, c.phone,
                       'SSL certificate', p.ssl_expires_on
                FROM client_projects p LEFT JOIN customers c ON c.id = p.customer_id
                WHERE p.ssl_expires_on IS NOT NULL AND p.status <> 'ended'
                UNION ALL
                SELECT p.id, p.name, p.status, c.name, c.email, c.phone,
                       'Maintenance', p.maintenance_due
                FROM client_projects p LEFT JOIN customers c ON c.id = p.customer_id
                WHERE p.maintenance_due IS NOT NULL AND p.status <> 'ended'
             ) due
             WHERE due_date <= DATE_ADD(CURDATE(), INTERVAL " . $days . " DAY)
             ORDER BY due_date ASC"
        );
    }
}
