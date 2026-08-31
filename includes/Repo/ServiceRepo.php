<?php
declare(strict_types=1);

namespace Techbiss\Repo;

final class ServiceRepo extends BaseRepo
{
    protected string $table = 'services';

    public function publishedBySlug(string $slug): ?array
    {
        return $this->db()->first('SELECT * FROM services WHERE slug = ? AND is_published = 1', [$slug]);
    }

    /** @return array<int,array<string,mixed>> */
    public function featured(int $limit = 6): array
    {
        $rows = $this->db()->all(
            'SELECT * FROM services WHERE is_published = 1 AND is_featured = 1 ORDER BY sort_order ASC LIMIT ' . max(1, $limit)
        );
        if (count($rows) < $limit) {
            $need = $limit - count($rows);
            $rows = array_merge($rows, $this->db()->all(
                'SELECT * FROM services WHERE is_published = 1 AND is_featured = 0 ORDER BY sort_order ASC LIMIT ' . $need
            ));
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function features(int $serviceId): array
    {
        return $this->db()->all(
            'SELECT * FROM service_features WHERE service_id = ? ORDER BY sort_order ASC, id ASC',
            [$serviceId]
        );
    }

    public function replaceFeatures(int $serviceId, array $rows): void
    {
        $this->db()->transaction(function ($db) use ($serviceId, $rows): void {
            $db->run('DELETE FROM service_features WHERE service_id = ?', [$serviceId]);
            $order = 0;
            foreach ($rows as $row) {
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $db->insert('service_features', [
                    'service_id'  => $serviceId,
                    'title'       => mb_substr($title, 0, 190),
                    'description' => mb_substr(trim((string) ($row['description'] ?? '')), 0, 500),
                    'icon'        => mb_substr(trim((string) ($row['icon'] ?? '')), 0, 60),
                    'sort_order'  => ++$order,
                ]);
            }
        });
        \Techbiss\Core\Cache::flush();
    }

    /** @return array<int,array{id:int,name:string}> */
    public function options(): array
    {
        return $this->db()->all('SELECT id, name FROM services WHERE is_published = 1 ORDER BY sort_order ASC');
    }

    /** Services linked to an industry. @return array<int,array<string,mixed>> */
    public function forIndustry(int $industryId): array
    {
        return $this->db()->all(
            'SELECT s.* FROM services s
             JOIN industry_services m ON m.service_id = s.id
             WHERE m.industry_id = ? AND s.is_published = 1
             ORDER BY m.sort_order ASC, s.sort_order ASC',
            [$industryId]
        );
    }

    /**
     * Services that genuinely go with this one.
     *
     * Ranked by how many industries recommend both, which is a relationship the
     * admin already maintains. Before this, every service page showed the first
     * three services in sort order — so SEO "paired with" the same three things
     * as maintenance did.
     *
     * @return array<int,array<string,mixed>>
     */
    public function pairedWith(int $serviceId, int $limit = 3): array
    {
        $rows = $this->db()->all(
            'SELECT s.*, COUNT(*) AS shared
             FROM services s
             JOIN industry_services a ON a.service_id = s.id
             JOIN industry_services b ON b.industry_id = a.industry_id
             WHERE b.service_id = ? AND s.id <> ? AND s.is_published = 1
             GROUP BY s.id
             ORDER BY shared DESC, s.sort_order ASC
             LIMIT ' . max(1, $limit),
            [$serviceId, $serviceId]
        );

        return $rows;
    }

    /** Services credited on a portfolio project. @return array<int,array<string,mixed>> */
    public function forPortfolio(int $portfolioId): array
    {
        return $this->db()->all(
            'SELECT s.* FROM services s
             JOIN portfolio_services m ON m.service_id = s.id
             WHERE m.portfolio_id = ? AND s.is_published = 1
             ORDER BY s.sort_order ASC',
            [$portfolioId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forSitemap(): array
    {
        return $this->db()->all('SELECT slug, updated_at FROM services WHERE is_published = 1 ORDER BY sort_order');
    }
}
