<?php
declare(strict_types=1);

namespace Techbiss\Repo;

use Techbiss\Core\Cache;

/**
 * Key/value site configuration. Everything the public site renders from the
 * settings table is read through here so it can be cached in one place.
 */
final class SettingsRepo extends BaseRepo
{
    protected string $table = 'settings';

    /** @var array<string,string>|null */
    private static ?array $loaded = null;

    /** @return array<string,string> */
    public function map(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }
        /** @var array<string,string> $map */
        $map = Cache::remember('settings.map', function (): array {
            $out = [];
            foreach ($this->db()->all('SELECT key_name, value FROM settings') as $row) {
                $out[(string) $row['key_name']] = (string) ($row['value'] ?? '');
            }
            return $out;
        }, 600);
        return self::$loaded = $map;
    }

    public function get(string $key, string $default = ''): string
    {
        $map = $this->map();
        $val = $map[$key] ?? '';
        return $val === '' ? $default : $val;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $map = $this->map();
        if (!array_key_exists($key, $map) || $map[$key] === '') {
            return $default;
        }
        return in_array($map[$key], ['1', 'true', 'yes', 'on'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->get($key, '');
        return is_numeric($v) ? (int) $v : $default;
    }

    /** @return array<int,string> */
    public function list(string $key): array
    {
        $v = $this->get($key, '');
        return $v === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $v))));
    }

    /** @return array<int,array<string,mixed>> */
    public function group(string $group): array
    {
        return $this->db()->all(
            'SELECT * FROM settings WHERE group_name = ? ORDER BY sort_order ASC, id ASC',
            [$group]
        );
    }

    /** @return array<int,string> */
    public function groups(): array
    {
        return array_map('strval', $this->db()->column('SELECT DISTINCT group_name FROM settings ORDER BY group_name'));
    }

    public function set(string $key, string $value): void
    {
        $exists = $this->db()->int('SELECT COUNT(*) FROM settings WHERE key_name = ?', [$key]) > 0;
        if ($exists) {
            $this->db()->run('UPDATE settings SET value = ?, updated_at = ? WHERE key_name = ?', [$value, date('Y-m-d H:i:s'), $key]);
        } else {
            $this->db()->insert('settings', [
                'group_name' => 'general',
                'key_name'   => $key,
                'value'      => $value,
                'type'       => 'text',
                'label'      => $key,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        self::$loaded = null;
        Cache::forget('settings.map');
        Cache::flush();
    }

    /** @param array<string,string> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    /** Social links that actually have a URL configured. @return array<int,array{key:string,label:string,url:string}> */
    public function socialLinks(): array
    {
        $labels = [
            'social_linkedin'  => 'LinkedIn',
            'social_x'         => 'X',
            'social_facebook'  => 'Facebook',
            'social_instagram' => 'Instagram',
            'social_youtube'   => 'YouTube',
            'social_github'    => 'GitHub',
            'social_dribbble'  => 'Dribbble',
        ];
        $out = [];
        foreach ($labels as $key => $label) {
            $url = $this->get($key);
            if ($url !== '') {
                $out[] = ['key' => str_replace('social_', '', $key), 'label' => $label, 'url' => $url];
            }
        }
        return $out;
    }

    /**
     * Settings worth filling in that are still empty, each pointing at the
     * admin group that holds it. Purely informational — nothing here is
     * required, and the admin checklist that reads this is dismissible.
     *
     * @return array<int,array{key:string,label:string,group:string}>
     */
    public function setupChecklist(): array
    {
        $items = [
            'logo'          => ['label' => 'Company logo', 'group' => 'general'],
            'favicon'       => ['label' => 'Favicon', 'group' => 'general'],
            'whatsapp'      => ['label' => 'WhatsApp number or chat link', 'group' => 'contact'],
            'contact_phone' => ['label' => 'Phone number', 'group' => 'contact'],
            'address'       => ['label' => 'Business address', 'group' => 'contact'],
            'seo_og_image'  => ['label' => 'Social share image', 'group' => 'seo'],
        ];
        $missing = [];
        foreach ($items as $key => $meta) {
            if ($this->get($key) === '') {
                $missing[] = ['key' => $key, 'label' => $meta['label'], 'group' => $meta['group']];
            }
        }
        return $missing;
    }
}
