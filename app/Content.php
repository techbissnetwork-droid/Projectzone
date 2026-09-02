<?php
declare(strict_types=1);

/**
 * Everything the admin can edit that is not a plain setting:
 * repeatable content blocks, navigation menus and custom pages.
 */
final class Content
{
    /** The repeatable lists, and what each column means in the admin form. */
    public const KINDS = [
        'stat' => [
            'name'  => 'Hero statistics',
            'hint'  => 'The three figures under the home page hero.',
            'label' => ['Caption', 'Services under one roof'],
            'title' => ['Value', '12'],
            'body'  => [null, null],
            'meta1' => [null, null],
        ],
        'journey' => [
            'name'  => 'Offline → online journey',
            'hint'  => 'The scroll-driven transformation. One row per stage, in order.',
            'label' => ['Stage word', 'OFFLINE'],
            'title' => ['Headline', 'A business that only exists in one place'],
            'body'  => ['Paragraph', 'Paper records, phone orders, manual follow-ups…'],
            'meta1' => ['Rail caption', 'Offline'],
        ],
        'process' => [
            'name'  => 'How we work',
            'hint'  => 'The scroll-reactive timeline. One row per stage.',
            'label' => ['Stage name', 'Discover'],
            'title' => ['Timing label', 'week 1'],
            'body'  => ['Paragraph', 'We understand your business, customers and goals.'],
            'extra' => ['Checklist', "Business & operations audit\nCustomer journey mapping"],
            'meta1' => ['Caption under the visual', 'mapping business logic'],
        ],
        'pillar' => [
            'name'  => 'Technology pillars',
            'hint'  => 'The trust section — Secure, Scalable, Fast and so on.',
            'label' => ['Title', 'Secure'],
            'title' => ['Indicator caption', 'certificate valid · auto-renew'],
            'body'  => ['Paragraph', 'TLS 1.3, firewalled, hardened and access-audited.'],
            'meta1' => [null, null],
        ],
        'arch' => [
            'name'  => 'Architecture diagram',
            'hint'  => 'Nodes in the system diagram. Layer 0 = source, 1 = core, 2 = services, 3 = infrastructure.',
            'label' => ['Node name', 'WEBSITE'],
            'title' => ['Caption', 'Marketing & conversion'],
            'body'  => [null, null],
            'meta1' => ['Layer (0–3)', '2'],
        ],
        'transform' => [
            'name'  => 'Business transformations',
            'hint'  => 'Restaurant → online ordering, and the rest.',
            'label' => ['Business type', 'Restaurant'],
            'title' => ['What we build', 'Online ordering system'],
            'body'  => ['Paragraph', 'Digital menu, delivery ordering, reservations…'],
            'extra' => ['Feature chips', "Menu & ordering\nReservations\nDigital payments"],
            'meta1' => [null, null],
        ],
    ];

    /** Sections that have both a summary and a full version, and where the
     *  full one lives when the home page shows only the summary. */
    public const DUAL = [
        'journey'   => 'transformation.php',
        'arch'      => 'technology.php',
        'process'   => 'process.php',
        'transform' => 'transformation.php#types',
        'pillars'   => 'technology.php#pillars',
    ];

    public static function mode(string $section): string
    {
        if (!isset(self::DUAL[$section])) {
            return 'full';
        }
        return Settings::get('mode_' . $section, 'compact') === 'full' ? 'full' : 'compact';
    }

    /** Sections of the home page, in the order the admin can rearrange. */
    public const SECTIONS = [
        'journey'     => 'Offline → online transformation',
        'services'    => 'Service modules',
        'arch'        => 'System architecture',
        'process'     => 'How we work',
        'work'        => 'Selected work',
        'transform'   => 'Business transformations',
        'pillars'     => 'Technology pillars',
        'marketplace' => 'Marketplace strip',
        'quote'       => 'Statement quote',
    ];

    /**
     * True once a query has failed because the schema is behind the code.
     * Every reader below degrades to empty instead of taking the site down,
     * so an administrator can still sign in and run the update.
     */
    private static bool $schemaBehind = false;

    public static function schemaBehind(): bool
    {
        return self::$schemaBehind;
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(string $kind, bool $activeOnly = true): array
    {
        static $cache = [];
        $key = $kind . ($activeOnly ? '.a' : '.x');
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $sql = 'SELECT * FROM content_items WHERE kind = :k'
             . ($activeOnly ? ' AND is_active = 1' : '')
             . ' ORDER BY sort_order, id';
        try {
            return $cache[$key] = Database::all($sql, ['k' => $kind]);
        } catch (PDOException) {
            self::$schemaBehind = true;
            return $cache[$key] = [];
        }
    }

    /** Home page sections in the admin's chosen order, skipping disabled ones. */
    public static function sectionOrder(): array
    {
        $saved = array_filter(array_map('trim', explode(',', Settings::get('home_sections', ''))));
        $out = [];
        foreach ($saved as $k) {
            if (isset(self::SECTIONS[$k]) && Settings::bool('section_' . $k, true)) {
                $out[] = $k;
            }
        }
        foreach (array_keys(self::SECTIONS) as $k) {
            if (!in_array($k, $out, true) && !in_array($k, $saved, true) && Settings::bool('section_' . $k, true)) {
                $out[] = $k;
            }
        }
        return $out;
    }

    /** @return array<int,array{label:string,href:string,new_tab:bool}> */
    public static function nav(string $location): array
    {
        static $cache = [];
        if (isset($cache[$location])) {
            return $cache[$location];
        }
        try {
            $rows = Database::all(
                'SELECT n.*, p.slug AS page_slug, p.status AS page_status
                 FROM nav_items n LEFT JOIN pages p ON p.id = n.page_id
                 WHERE n.location = :l AND n.is_active = 1 ORDER BY n.sort_order, n.id',
                ['l' => $location]
            );
        } catch (PDOException) {
            self::$schemaBehind = true;
            return $cache[$location] = [];
        }
        $out = [];
        foreach ($rows as $r) {
            if ($r['page_id'] !== null) {
                if (($r['page_status'] ?? '') !== 'published') {
                    continue;
                }
                $href = url('page.php?slug=' . urlencode((string)$r['page_slug']));
            } else {
                $raw = (string)($r['url'] ?? '');
                if ($raw === '') {
                    continue;
                }
                $href = preg_match('~^(https?://|mailto:|tel:|#)~i', $raw) ? $raw : url($raw);
            }
            $out[] = ['label' => (string)$r['label'], 'href' => $href, 'new_tab' => (bool)$r['new_tab']];
        }
        return $cache[$location] = $out;
    }

    public static function page(string $slug): ?array
    {
        try {
            return Database::one("SELECT * FROM pages WHERE slug = :s AND status = 'published'", ['s' => $slug]);
        } catch (PDOException) {
            self::$schemaBehind = true;
            return null;
        }
    }

    /** The logo: an uploaded image when set, otherwise the built-in mark. */
    public static function logo(string $class = 'brand__mark'): string
    {
        $img = Settings::get('logo_image', '');
        if ($img !== '') {
            return '<span class="' . e($class) . ' brand__mark--img"><img src="' . e(url($img))
                 . '" alt="" width="120" height="32"></span>';
        }
        return '<span class="' . e($class) . '" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none">'
             . '<path d="M12 2 21.5 7v10L12 22 2.5 17V7z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>'
             . '<path d="M12 22V12l9.5-5M12 12 2.5 7" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" opacity=".55"/>'
             . '</svg></span>';
    }
}
