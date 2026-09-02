<?php
declare(strict_types=1);

/** Site-wide, admin-editable configuration stored in the settings table. */
final class Settings
{
    private static array $cache = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$cache = [];
        foreach (Database::all('SELECT skey, svalue FROM settings') as $row) {
            self::$cache[$row['skey']] = $row['svalue'];
        }
        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string
    {
        self::load();
        $v = self::$cache[$key] ?? null;
        return ($v === null || $v === '') ? $default : (string)$v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        self::load();
        if (!array_key_exists($key, self::$cache) || self::$cache[$key] === null || self::$cache[$key] === '') {
            return $default;
        }
        return in_array(strtolower((string)self::$cache[$key]), ['1', 'true', 'yes', 'on'], true);
    }

    public static function set(string $key, ?string $value): void
    {
        self::load();
        $now = now();
        if (array_key_exists($key, self::$cache)) {
            Database::run('UPDATE settings SET svalue = :v, updated_at = :u WHERE skey = :k',
                ['v' => $value, 'u' => $now, 'k' => $key]);
        } else {
            Database::run('INSERT INTO settings (skey, svalue, updated_at) VALUES (:k, :v, :u)',
                ['k' => $key, 'v' => $value, 'u' => $now]);
        }
        self::$cache[$key] = $value;
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $k => $v) {
            self::set((string)$k, $v === null ? null : (string)$v);
        }
    }

    /** @return array<string,string> */
    public static function defaults(): array
    {
        return [
            'site_name'        => 'TECHBISS',
            'site_tagline'     => 'Digital transformation for businesses ready to move forward.',
            'site_description' => 'TECHBISS builds the complete digital presence of your business — websites, apps, hosting, security, email, e-commerce, automation and payments.',
            'hero_eyebrow'     => 'Digital transformation · est. for the next decade',
            'hero_title_a'     => 'Your Business.',
            'hero_title_b'     => 'Built for the',
            'hero_title_c'     => 'Digital World.',
            'hero_lede'        => 'From offline operations to a complete online ecosystem — TECHBISS builds, launches and powers everything your business needs to grow online.',
            'hero_cta_primary' => 'Start Your Digital Journey',
            'hero_cta_secondary' => 'Explore What We Build',
            'quote'            => 'We don’t just build websites. We build the entire digital presence of your business.',
            'contact_email'    => 'team@techbiss.com',
            'contact_phone'    => '',
            'contact_address'  => '',
            'contact_hours'    => 'Mon–Fri · 9:00–18:00',
            'accent_color'     => '#8FB0FF',
            'accent_warm'      => '#E7BB8D',
            'currency'         => 'NPR',
            'currency_symbol'  => 'Rs',
            'social_linkedin'  => '',
            'social_facebook'  => '',
            'social_x'         => '',
            'social_github'    => '',
            'show_marketplace' => '1',
            'show_portfolio'   => '1',
            'expiry_warn_days' => '45',
            'payment_instructions' => "Transfer the order amount and send us the reference number.\nWe confirm every order manually within one business day.",
            'footer_note'      => 'Digital transformation for businesses ready to move forward.',
        ];
    }
}
