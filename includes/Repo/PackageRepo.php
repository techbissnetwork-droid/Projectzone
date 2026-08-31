<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Cache;

/**
 * Packages and the prepaid pricing model.
 *
 * Pricing rule: a saving is only ever shown when the administrator has entered
 * a prepaid price that is genuinely lower than the regular price. Discounts are
 * derived from those two stored numbers — never invented, never hard-coded.
 */
final class PackageRepo extends BaseRepo
{
    protected string $table = 'packages';

    /** @return array<int,array<string,mixed>> */
    public function publishedWithFeatures(): array
    {
        $packages = $this->db()->all('SELECT * FROM packages WHERE is_published = 1 ORDER BY sort_order ASC, id ASC');
        if ($packages === []) {
            return [];
        }
        $ids   = array_map(static fn ($p) => (int) $p['id'], $packages);
        $place = implode(',', array_fill(0, count($ids), '?'));
        $feats = $this->db()->all(
            "SELECT * FROM package_features WHERE package_id IN ($place) ORDER BY sort_order ASC, id ASC",
            $ids
        );
        $byPackage = [];
        foreach ($feats as $f) {
            $byPackage[(int) $f['package_id']][] = $f;
        }
        foreach ($packages as &$p) {
            $p['features'] = $byPackage[(int) $p['id']] ?? [];
            $p['pricing']  = self::pricing($p);
        }
        unset($p);
        return $packages;
    }

    public function publishedBySlug(string $slug): ?array
    {
        $p = $this->db()->first('SELECT * FROM packages WHERE slug = ? AND is_published = 1', [$slug]);
        if ($p === null) {
            return null;
        }
        $p['features'] = $this->features((int) $p['id']);
        $p['addons']   = $this->addonsFor((int) $p['id']);
        $p['pricing']  = self::pricing($p);
        return $p;
    }

    public function findWithRelations(int $id): ?array
    {
        $p = $this->find($id);
        if ($p === null) {
            return null;
        }
        $p['features'] = $this->features($id);
        $p['addons']   = $this->addonsFor($id);
        $p['pricing']  = self::pricing($p);
        return $p;
    }

    /**
     * Derive the display pricing for a package.
     *
     * @return array{regular:float,prepaid:?float,has_discount:bool,saving:float,percent:int,payable:float,is_custom:bool}
     */
    public static function pricing(array $package): array
    {
        $isCustom = (int) ($package['is_custom_quote'] ?? 0) === 1;
        $regular  = round((float) ($package['regular_price'] ?? 0), 2);
        $prepaidRaw = $package['prepaid_price'] ?? null;
        $prepaid  = ($prepaidRaw === null || $prepaidRaw === '') ? null : round((float) $prepaidRaw, 2);

        $hasDiscount = !$isCustom && $prepaid !== null && $regular > 0 && $prepaid > 0 && $prepaid < $regular;
        $saving      = $hasDiscount ? round($regular - $prepaid, 2) : 0.0;
        $percent     = $hasDiscount && $regular > 0 ? (int) round(($saving / $regular) * 100) : 0;
        $payable     = $hasDiscount ? $prepaid : ($prepaid !== null && $prepaid > 0 && !$isCustom ? $prepaid : $regular);

        return [
            'regular'      => $regular,
            'prepaid'      => $prepaid,
            'has_discount' => $hasDiscount,
            'saving'       => $saving,
            'percent'      => $percent,
            'payable'      => round($payable, 2),
            'is_custom'    => $isCustom,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function features(int $packageId): array
    {
        return $this->db()->all(
            'SELECT * FROM package_features WHERE package_id = ? ORDER BY sort_order ASC, id ASC',
            [$packageId]
        );
    }

    public function replaceFeatures(int $packageId, array $rows): void
    {
        $this->db()->transaction(function ($db) use ($packageId, $rows): void {
            $db->run('DELETE FROM package_features WHERE package_id = ?', [$packageId]);
            $order = 0;
            foreach ($rows as $row) {
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $db->insert('package_features', [
                    'package_id'   => $packageId,
                    'title'        => mb_substr($title, 0, 190),
                    'description'  => mb_substr(trim((string) ($row['description'] ?? '')), 0, 500),
                    'is_included'  => !empty($row['is_included']) ? 1 : 0,
                    'is_highlight' => !empty($row['is_highlight']) ? 1 : 0,
                    'sort_order'   => ++$order,
                ]);
            }
        });
        Cache::flush();
    }

    /** Add-ons offered with a specific package. @return array<int,array<string,mixed>> */
    public function addonsFor(int $packageId): array
    {
        return $this->db()->all(
            'SELECT a.* FROM package_addons a
             JOIN package_addon_map m ON m.addon_id = a.id
             WHERE m.package_id = ? AND a.is_published = 1
             ORDER BY a.sort_order ASC, a.id ASC',
            [$packageId]
        );
    }

    /** @return array<int,int> */
    public function addonIds(int $packageId): array
    {
        return array_map('intval', $this->db()->column(
            'SELECT addon_id FROM package_addon_map WHERE package_id = ?',
            [$packageId]
        ));
    }

    public function syncAddons(int $packageId, array $addonIds): void
    {
        $this->db()->transaction(function ($db) use ($packageId, $addonIds): void {
            $db->run('DELETE FROM package_addon_map WHERE package_id = ?', [$packageId]);
            foreach (array_unique(array_map('intval', $addonIds)) as $aid) {
                if ($aid > 0) {
                    $db->insert('package_addon_map', ['package_id' => $packageId, 'addon_id' => $aid]);
                }
            }
        });
        Cache::flush();
    }

    /** @return array<int,array{id:int,name:string}> */
    public function options(): array
    {
        return $this->db()->all('SELECT id, name FROM packages WHERE is_published = 1 ORDER BY sort_order ASC');
    }

    /** @return array<int,array<string,mixed>> */
    public function forSitemap(): array
    {
        return $this->db()->all('SELECT slug, updated_at FROM packages WHERE is_published = 1 ORDER BY sort_order');
    }

    /** Every feature title across published packages — the comparison table's row set. @return array<int,string> */
    public function comparisonRows(): array
    {
        return array_map('strval', $this->db()->column(
            'SELECT f.title FROM package_features f
             JOIN packages p ON p.id = f.package_id AND p.is_published = 1
             GROUP BY f.title
             ORDER BY MIN(p.sort_order) ASC, MIN(f.sort_order) ASC'
        ));
    }
}
