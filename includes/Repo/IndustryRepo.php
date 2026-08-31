<?php
declare(strict_types=1);

namespace Techbiss\Repo;

final class IndustryRepo extends BaseRepo
{
    protected string $table = 'industries';

    public function publishedBySlug(string $slug): ?array
    {
        return $this->db()->first('SELECT * FROM industries WHERE slug = ? AND is_published = 1', [$slug]);
    }

    /** @return array<int,array<string,mixed>> */
    public function featured(int $limit = 8): array
    {
        return $this->db()->all(
            'SELECT * FROM industries WHERE is_published = 1 ORDER BY is_featured DESC, sort_order ASC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<int,array{id:int,name:string}> */
    public function options(): array
    {
        return $this->db()->all('SELECT id, name FROM industries WHERE is_published = 1 ORDER BY sort_order ASC');
    }

    /** @return array<int,int> */
    public function serviceIds(int $industryId): array
    {
        return array_map('intval', $this->db()->column(
            'SELECT service_id FROM industry_services WHERE industry_id = ?',
            [$industryId]
        ));
    }

    public function syncServices(int $industryId, array $serviceIds): void
    {
        $this->db()->transaction(function ($db) use ($industryId, $serviceIds): void {
            $db->run('DELETE FROM industry_services WHERE industry_id = ?', [$industryId]);
            $order = 0;
            foreach (array_unique(array_map('intval', $serviceIds)) as $sid) {
                if ($sid <= 0) {
                    continue;
                }
                $db->insert('industry_services', [
                    'industry_id' => $industryId,
                    'service_id'  => $sid,
                    'sort_order'  => ++$order,
                ]);
            }
        });
        \Techbiss\Core\Cache::flush();
    }

    /** @return array<int,array<string,mixed>> */
    public function forSitemap(): array
    {
        return $this->db()->all('SELECT slug, updated_at FROM industries WHERE is_published = 1 ORDER BY sort_order');
    }
}
