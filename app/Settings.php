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
        try {
            foreach (Database::all('SELECT skey, svalue FROM settings') as $row) {
                self::$cache[$row['skey']] = $row['svalue'];
            }
        } catch (PDOException) {
            /* Schema behind the code: fall back to defaults so the admin can
               still sign in and run the database update. */
            self::$cache = self::defaults();
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
            'foot_col1'        => 'Services',
            'foot_col2'        => 'Company',
            'foot_col3'        => 'Clients',

            /* appearance */
            'logo_image'       => '',
            'favicon_image'    => '',
            'font_display'     => 'Inter Tight',
            'font_body'        => 'Inter',
            'radius_scale'     => 'normal',
            'bg_base'          => '#06070A',
            'text_primary'     => '#EEF1F6',
            'text_muted'       => '#98A1B0',
            'custom_css'       => '',
            'custom_head'      => '',
            'custom_body_end'  => '',

            /* home page sections */
            'home_sections'    => 'journey,services,arch,process,work,transform,pillars,marketplace,quote',
            'section_journey'  => '1',
            'section_services' => '1',
            'section_arch'     => '1',
            'section_process'  => '1',
            'section_work'     => '1',
            'section_transform'=> '1',
            'section_pillars'  => '1',
            'section_marketplace' => '1',
            'section_quote'    => '1',

            /* section headings */
            'journey_eyebrow'  => 'The transformation',
            'services_eyebrow' => 'The ecosystem',
            'services_title'   => "Everything your business\nneeds to exist online.",
            'services_lede'    => 'Commission one module, or let us run the entire stack — domain to analytics — as a single system.',
            'arch_eyebrow'     => 'Systems, not pages',
            'arch_title'       => "We build digital systems,\nnot just websites.",
            'arch_lede'        => 'Brand, front-end, application, backend, database, payments, hosting, security and analytics — designed as one architecture so nothing is bolted on later.',
            'arch_flow'        => 'Brand → Website → App → Backend → Database → Payments → Hosting → Security → Analytics',
            'process_eyebrow'  => 'The experience',
            'process_title'    => 'How we work',
            'work_eyebrow'     => 'Selected builds',
            'work_title'       => "Businesses that moved\ntheir operations online.",
            'work_lede'        => 'Delivered projects, with the scope and the outcome.',
            'transform_eyebrow'=> 'Your business, digitised',
            'transform_title'  => "You already have a business.\nWe build the digital version of it.",
            'pillars_eyebrow'  => 'Behind the experience',
            'pillars_title'    => "The infrastructure\nis part of the design.",
            'pillars_lede'     => 'The things nobody notices until they fail — certificates, backups, response times, capacity — are the things we monitor on your behalf.',
            'mkt_eyebrow'      => 'Ready to launch',
            'mkt_title'        => "Premade projects,\nyours today.",
            'mkt_lede'         => 'Complete systems we have already built and tested. Buy the source, we install and brand it for you.',
            'cta_title_a'      => 'Ready to take your',
            'cta_title_b'      => 'business',
            'cta_title_c'      => 'online?',
            'cta_lede'         => 'Tell us what you’re building. We’ll figure out the technology.',
            'cta_primary'      => 'Start Your Project',
            'cta_secondary'    => 'Talk to us',

            /* SEO & analytics */
            'meta_suffix'      => '',
            'og_image'         => '',
            'analytics_id'     => '',
            'robots_index'     => '1',
        ];
    }
}
