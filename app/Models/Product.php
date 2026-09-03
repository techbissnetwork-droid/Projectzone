<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Product
{
    public const CATEGORIES = [
        'websites' => 'Websites',
        'themes' => 'Themes',
        'templates' => 'Templates',
        'dashboards' => 'Dashboards',
        'ecommerce' => 'Ecommerce',
    ];

    public const SORTS = [
        'featured' => 'Featured',
        'popular' => 'Most popular',
        'rating' => 'Highest rated',
        'newest' => 'Recently updated',
        'price-asc' => 'Price: low to high',
        'price-desc' => 'Price: high to low',
    ];

    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function featured(int $limit = 3): array
    {
        return $this->db->select(
            "SELECT * FROM products WHERE status = 'published' AND featured = 1
             ORDER BY sales_count DESC LIMIT " . max(1, $limit)
        );
    }

    /**
     * @param array{category?:string,q?:string,sort?:string,max?:float} $filters
     * @return list<array<string,mixed>>
     */
    public function search(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        [$where, $bindings] = $this->buildWhere($filters);
        $order = $this->orderBy((string) ($filters['sort'] ?? 'featured'));

        return $this->db->select(
            "SELECT * FROM products WHERE {$where} ORDER BY {$order} LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset),
            $bindings
        );
    }

    public function count(array $filters = []): int
    {
        [$where, $bindings] = $this->buildWhere($filters);
        return (int) $this->db->value("SELECT COUNT(*) FROM products WHERE {$where}", $bindings, 0);
    }

    /** @return array<string,int> category => count */
    public function categoryCounts(): array
    {
        $rows = $this->db->select(
            "SELECT category, COUNT(*) AS total FROM products WHERE status = 'published' GROUP BY category"
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['category']] = (int) $row['total'];
        }
        return $counts;
    }

    private function buildWhere(array $filters): array
    {
        $where = ["status = 'published'"];
        $bindings = [];

        if (!empty($filters['category']) && isset(self::CATEGORIES[$filters['category']])) {
            $where[] = 'category = :category';
            $bindings['category'] = $filters['category'];
        }

        if (!empty($filters['q'])) {
            $where[] = '(LOWER(name) LIKE :q OR LOWER(tagline) LIKE :q OR LOWER(tags) LIKE :q)';
            $bindings['q'] = '%' . strtolower(trim((string) $filters['q'])) . '%';
        }

        if (!empty($filters['max'])) {
            $where[] = 'price <= :max';
            $bindings['max'] = (float) $filters['max'];
        }

        return [implode(' AND ', $where), $bindings];
    }

    private function orderBy(string $sort): string
    {
        return match ($sort) {
            'popular' => 'sales_count DESC, rating DESC',
            'rating' => 'rating DESC, reviews_count DESC',
            'newest' => 'updated_at DESC, released_at DESC',
            'price-asc' => 'price ASC, name ASC',
            'price-desc' => 'price DESC, name ASC',
            default => 'featured DESC, sales_count DESC',
        };
    }

    public function find(string $slug): ?array
    {
        $row = $this->db->first("SELECT * FROM products WHERE slug = ? AND status = 'published' LIMIT 1", [$slug]);
        return $row === null ? null : self::decode($row);
    }

    public function findById(int $id): ?array
    {
        $row = $this->db->first('SELECT * FROM products WHERE id = ? LIMIT 1', [$id]);
        return $row === null ? null : self::decode($row);
    }

    /** @return list<array<string,mixed>> */
    public function related(string $slug, string $category, int $limit = 3): array
    {
        return $this->db->select(
            "SELECT * FROM products WHERE status = 'published' AND slug != ? AND category = ?
             ORDER BY sales_count DESC LIMIT " . max(1, $limit),
            [$slug, $category]
        );
    }

    /** Decode the JSON columns into arrays for the view layer. */
    public static function decode(array $row): array
    {
        foreach (['tags', 'features', 'specs', 'includes', 'pages'] as $column) {
            $decoded = json_decode((string) ($row[$column] ?? '[]'), true);
            $row[$column] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    /** Price for a licence tier, falling back sensibly when a tier is unpriced. */
    public static function priceFor(array $product, string $tier): float
    {
        return match ($tier) {
            'extended' => (float) ($product['extended_price'] ?? $product['price']),
            'enterprise' => (float) ($product['enterprise_price'] ?? $product['extended_price'] ?? $product['price']),
            default => (float) $product['price'],
        };
    }

    public const TIERS = [
        'standard' => ['label' => 'Standard', 'blurb' => 'One production site. Twelve months of updates and email support.'],
        'extended' => ['label' => 'Extended', 'blurb' => 'Up to five sites, client work permitted, priority support.'],
        'enterprise' => ['label' => 'Enterprise', 'blurb' => 'Unlimited internal sites, source escrow, SLA-backed support.'],
    ];
}
