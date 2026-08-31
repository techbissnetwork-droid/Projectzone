<?php
declare(strict_types=1);

namespace Techbiss\Repo;

final class AddonRepo extends BaseRepo
{
    protected string $table = 'package_addons';

    /** @return array<int,array<string,mixed>> */
    public function publishedAll(): array
    {
        return $this->db()->all('SELECT * FROM package_addons WHERE is_published = 1 ORDER BY sort_order ASC, id ASC');
    }

    /** @param array<int,int> $ids @return array<int,array<string,mixed>> */
    public function byIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn ($i) => $i > 0));
        if ($ids === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($ids), '?'));
        return $this->db()->all(
            "SELECT * FROM package_addons WHERE id IN ($place) AND is_published = 1 ORDER BY sort_order ASC",
            $ids
        );
    }
}
