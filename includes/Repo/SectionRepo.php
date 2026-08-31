<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Cache;

/**
 * Editable content blocks. Every homepage band — hero copy, the offline-business
 * problem list, the transformation chain, the trust points — is stored here so
 * an administrator can rewrite the site without touching PHP.
 */
final class SectionRepo extends BaseRepo
{
    protected string $table = 'page_sections';
    protected string $orderBy = 'sort_order ASC, id ASC';

    /** @return array<string,array<string,mixed>> keyed by section_key, each with `items` */
    public function forPage(string $pageKey = 'home'): array
    {
        /** @var array<string,array<string,mixed>> $out */
        $out = Cache::remember('sections.' . $pageKey, function () use ($pageKey): array {
            $sections = $this->db()->all(
                'SELECT * FROM page_sections WHERE page_key = ? AND is_published = 1 ORDER BY sort_order ASC, id ASC',
                [$pageKey]
            );
            if ($sections === []) {
                return [];
            }
            $ids   = array_map(static fn ($s) => (int) $s['id'], $sections);
            $place = implode(',', array_fill(0, count($ids), '?'));
            $items = $this->db()->all(
                "SELECT * FROM section_items WHERE section_id IN ($place) AND is_published = 1 ORDER BY sort_order ASC, id ASC",
                $ids
            );
            $bySection = [];
            foreach ($items as $item) {
                $bySection[(int) $item['section_id']][] = $item;
            }
            $map = [];
            foreach ($sections as $section) {
                $section['items'] = $bySection[(int) $section['id']] ?? [];
                $map[(string) $section['section_key']] = $section;
            }
            return $map;
        }, 600);
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function items(int $sectionId): array
    {
        return $this->db()->all(
            'SELECT * FROM section_items WHERE section_id = ? ORDER BY sort_order ASC, id ASC',
            [$sectionId]
        );
    }

    /** Replace a section's items wholesale from an admin form submission. */
    public function replaceItems(int $sectionId, array $rows): void
    {
        $this->db()->transaction(function ($db) use ($sectionId, $rows): void {
            $db->run('DELETE FROM section_items WHERE section_id = ?', [$sectionId]);
            $order = 0;
            foreach ($rows as $row) {
                $title = trim((string) ($row['title'] ?? ''));
                $descr = trim((string) ($row['description'] ?? ''));
                if ($title === '' && $descr === '') {
                    continue;
                }
                $db->insert('section_items', [
                    'section_id'   => $sectionId,
                    'title'        => mb_substr($title, 0, 190),
                    'description'  => mb_substr($descr, 0, 500),
                    'icon'         => mb_substr(trim((string) ($row['icon'] ?? '')), 0, 60),
                    'value'        => mb_substr(trim((string) ($row['value'] ?? '')), 0, 60),
                    'url'          => mb_substr(trim((string) ($row['url'] ?? '')), 0, 500),
                    'is_published' => 1,
                    'sort_order'   => ++$order,
                ]);
            }
        });
        \Techbiss\Core\Cache::flush();
    }

    /** @return array<int,array<string,mixed>> every section for the admin list */
    public function allWithCounts(string $pageKey = 'home'): array
    {
        return $this->db()->all(
            'SELECT s.*, (SELECT COUNT(*) FROM section_items i WHERE i.section_id = s.id) AS item_count
             FROM page_sections s WHERE s.page_key = ? ORDER BY s.sort_order ASC, s.id ASC',
            [$pageKey]
        );
    }
}
