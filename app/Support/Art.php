<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Deterministic vector artwork.
 *
 * Marketplace thumbnails, previews and hero visuals are generated as inline
 * SVG seeded from the product slug: identical on every render, resolution
 * independent, theme-aware, and roughly 1–2 KB instead of a 200 KB screenshot.
 * That removes the single largest image payload a marketplace normally carries.
 */
final class Art
{
    private const PALETTES = [
        ['#3d7bff', '#22d3ee', '#0b1220'],
        ['#8b5cf6', '#f2637e', '#120b1e'],
        ['#2dd4a7', '#3d7bff', '#07161a'],
        ['#f5a524', '#f2637e', '#1c1208'],
        ['#22d3ee', '#2dd4a7', '#06161c'],
        ['#6366f1', '#22d3ee', '#0a0d1f'],
    ];

    private static function seed(string $key): int
    {
        return (int) hexdec(substr(hash('xxh128', $key), 0, 8));
    }

    public static function palette(string $key): array
    {
        return self::PALETTES[self::seed($key) % count(self::PALETTES)];
    }

    /**
     * Abstract browser-frame mockup used for marketplace cards and previews.
     */
    public static function mockup(string $key, string $layout = 'auto', array $options = []): string
    {
        $rand = self::seed($key);
        [$c1, $c2, $bg] = self::palette($key);
        $id = 'a' . substr(hash('xxh128', $key), 0, 8);
        $w = 640;
        $h = 400;

        $layouts = ['hero', 'grid', 'dashboard', 'editorial', 'commerce'];
        if ($layout === 'auto') {
            $layout = $layouts[$rand % count($layouts)];
        }

        $class = $options['class'] ?? '';
        $label = $options['label'] ?? 'Design preview';

        $body = match ($layout) {
            'grid' => self::layoutGrid($c1, $c2),
            'dashboard' => self::layoutDashboard($c1, $c2),
            'editorial' => self::layoutEditorial($c1, $c2),
            'commerce' => self::layoutCommerce($c1, $c2),
            default => self::layoutHero($c1, $c2),
        };

        return <<<SVG
<svg viewBox="0 0 {$w} {$h}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{$label}" preserveAspectRatio="xMidYMid slice" class="{$class}">
<defs>
<linearGradient id="{$id}g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$c1}"/><stop offset="1" stop-color="{$c2}"/></linearGradient>
<radialGradient id="{$id}r" cx="18%" cy="6%" r="82%"><stop offset="0" stop-color="{$c1}" stop-opacity=".38"/><stop offset="1" stop-color="{$c1}" stop-opacity="0"/></radialGradient>
</defs>
<rect width="{$w}" height="{$h}" fill="{$bg}"/>
<rect width="{$w}" height="{$h}" fill="url(#{$id}r)"/>
<g fill="none" stroke="#ffffff" stroke-opacity=".055"><path d="M0 100h640M0 200h640M0 300h640M160 0v400M320 0v400M480 0v400"/></g>
<rect x="28" y="24" width="584" height="34" rx="9" fill="#ffffff" fill-opacity=".05"/>
<circle cx="48" cy="41" r="4.5" fill="{$c1}" fill-opacity=".85"/><circle cx="64" cy="41" r="4.5" fill="#ffffff" fill-opacity=".2"/><circle cx="80" cy="41" r="4.5" fill="#ffffff" fill-opacity=".2"/>
<rect x="100" y="34" width="180" height="14" rx="7" fill="#ffffff" fill-opacity=".08"/>
<g transform="translate(28,76)">{$body}</g>
</svg>
SVG;
    }

    private static function layoutHero(string $c1, string $c2): string
    {
        return <<<SVG
<rect width="330" height="13" rx="6.5" fill="#ffffff" fill-opacity=".22"/>
<rect y="26" width="250" height="13" rx="6.5" fill="#ffffff" fill-opacity=".14"/>
<rect y="58" width="400" height="8" rx="4" fill="#ffffff" fill-opacity=".08"/>
<rect y="74" width="356" height="8" rx="4" fill="#ffffff" fill-opacity=".08"/>
<rect y="104" width="118" height="34" rx="9" fill="url(#a)" opacity="0"/>
<rect y="104" width="118" height="34" rx="9" fill="{$c1}"/>
<rect x="130" y="104" width="104" height="34" rx="9" fill="#ffffff" fill-opacity=".08"/>
<g transform="translate(376,4)">
<rect width="208" height="216" rx="16" fill="#ffffff" fill-opacity=".05"/>
<rect x="18" y="18" width="172" height="96" rx="10" fill="{$c2}" fill-opacity=".28"/>
<circle cx="60" cy="66" r="22" fill="{$c1}" fill-opacity=".6"/>
<rect x="18" y="130" width="130" height="9" rx="4.5" fill="#ffffff" fill-opacity=".16"/>
<rect x="18" y="148" width="96" height="9" rx="4.5" fill="#ffffff" fill-opacity=".1"/>
<rect x="18" y="176" width="172" height="24" rx="8" fill="{$c2}" fill-opacity=".22"/>
</g>
<g transform="translate(0,168)">
<rect width="106" height="58" rx="10" fill="#ffffff" fill-opacity=".06"/>
<rect x="118" width="106" height="58" rx="10" fill="#ffffff" fill-opacity=".06"/>
<rect x="236" width="106" height="58" rx="10" fill="#ffffff" fill-opacity=".06"/>
<rect x="14" y="16" width="46" height="8" rx="4" fill="{$c1}" fill-opacity=".8"/>
<rect x="132" y="16" width="46" height="8" rx="4" fill="{$c2}" fill-opacity=".8"/>
<rect x="250" y="16" width="46" height="8" rx="4" fill="{$c1}" fill-opacity=".55"/>
<rect x="14" y="34" width="72" height="7" rx="3.5" fill="#ffffff" fill-opacity=".1"/>
<rect x="132" y="34" width="72" height="7" rx="3.5" fill="#ffffff" fill-opacity=".1"/>
<rect x="250" y="34" width="72" height="7" rx="3.5" fill="#ffffff" fill-opacity=".1"/>
</g>
SVG;
    }

    private static function layoutGrid(string $c1, string $c2): string
    {
        $cells = '';
        for ($i = 0; $i < 9; $i++) {
            $x = ($i % 3) * 200;
            $y = intdiv($i, 3) * 76;
            $opacity = 0.05 + (($i * 7) % 5) * 0.012;
            $accent = $i % 4 === 0 ? $c1 : ($i % 3 === 0 ? $c2 : '#ffffff');
            $cells .= sprintf(
                '<rect x="%d" y="%d" width="184" height="62" rx="11" fill="#ffffff" fill-opacity="%.3f"/>'
                . '<rect x="%d" y="%d" width="30" height="30" rx="8" fill="%s" fill-opacity=".55"/>'
                . '<rect x="%d" y="%d" width="86" height="7" rx="3.5" fill="#ffffff" fill-opacity=".16"/>'
                . '<rect x="%d" y="%d" width="60" height="6" rx="3" fill="#ffffff" fill-opacity=".09"/>',
                $x, $y, $opacity,
                $x + 14, $y + 16, $accent,
                $x + 54, $y + 20,
                $x + 54, $y + 34
            );
        }
        return '<rect width="240" height="12" rx="6" fill="#ffffff" fill-opacity=".2"/>'
            . '<g transform="translate(0,34)">' . $cells . '</g>';
    }

    private static function layoutDashboard(string $c1, string $c2): string
    {
        $bars = '';
        $heights = [38, 62, 46, 78, 54, 88, 66, 96, 72];
        foreach ($heights as $i => $height) {
            $bars .= sprintf(
                '<rect x="%d" y="%d" width="18" height="%d" rx="5" fill="%s" fill-opacity="%.2f"/>',
                $i * 30,
                104 - $height,
                $height,
                $i % 2 ? $c2 : $c1,
                0.45 + $i * 0.05
            );
        }
        return <<<SVG
<rect width="120" height="11" rx="5.5" fill="#ffffff" fill-opacity=".2"/>
<g transform="translate(0,28)">
<rect width="118" height="52" rx="10" fill="#ffffff" fill-opacity=".06"/>
<rect x="130" width="118" height="52" rx="10" fill="#ffffff" fill-opacity=".06"/>
<rect x="260" width="118" height="52" rx="10" fill="#ffffff" fill-opacity=".06"/>
<rect x="390" width="118" height="52" rx="10" fill="#ffffff" fill-opacity=".06"/>
<rect x="14" y="14" width="40" height="6" rx="3" fill="#ffffff" fill-opacity=".12"/>
<rect x="14" y="28" width="62" height="11" rx="4" fill="{$c1}" fill-opacity=".8"/>
<rect x="144" y="14" width="40" height="6" rx="3" fill="#ffffff" fill-opacity=".12"/>
<rect x="144" y="28" width="54" height="11" rx="4" fill="{$c2}" fill-opacity=".8"/>
<rect x="274" y="14" width="40" height="6" rx="3" fill="#ffffff" fill-opacity=".12"/>
<rect x="274" y="28" width="58" height="11" rx="4" fill="#ffffff" fill-opacity=".3"/>
<rect x="404" y="14" width="40" height="6" rx="3" fill="#ffffff" fill-opacity=".12"/>
<rect x="404" y="28" width="46" height="11" rx="4" fill="#ffffff" fill-opacity=".3"/>
</g>
<g transform="translate(0,104)">
<rect width="330" height="140" rx="12" fill="#ffffff" fill-opacity=".05"/>
<g transform="translate(20,22)">{$bars}</g>
<rect x="350" width="158" height="140" rx="12" fill="#ffffff" fill-opacity=".05"/>
<circle cx="429" cy="66" r="40" fill="none" stroke="{$c1}" stroke-width="14" stroke-opacity=".65"/>
<circle cx="429" cy="66" r="40" fill="none" stroke="{$c2}" stroke-width="14" stroke-dasharray="90 300" stroke-linecap="round"/>
<rect x="370" y="118" width="118" height="7" rx="3.5" fill="#ffffff" fill-opacity=".1"/>
</g>
SVG;
    }

    private static function layoutEditorial(string $c1, string $c2): string
    {
        return <<<SVG
<rect width="356" height="196" rx="14" fill="{$c1}" fill-opacity=".26"/>
<circle cx="130" cy="82" r="34" fill="{$c2}" fill-opacity=".5"/>
<rect x="34" y="140" width="180" height="10" rx="5" fill="#ffffff" fill-opacity=".2"/>
<rect x="34" y="158" width="130" height="8" rx="4" fill="#ffffff" fill-opacity=".12"/>
<g transform="translate(378,0)">
<rect width="206" height="9" rx="4.5" fill="#ffffff" fill-opacity=".2"/>
<rect y="20" width="170" height="7" rx="3.5" fill="#ffffff" fill-opacity=".1"/>
<rect y="34" width="188" height="7" rx="3.5" fill="#ffffff" fill-opacity=".1"/>
<rect y="48" width="150" height="7" rx="3.5" fill="#ffffff" fill-opacity=".1"/>
<rect y="76" width="206" height="1" fill="#ffffff" fill-opacity=".1"/>
<rect y="92" width="206" height="9" rx="4.5" fill="#ffffff" fill-opacity=".18"/>
<rect y="112" width="176" height="7" rx="3.5" fill="#ffffff" fill-opacity=".1"/>
<rect y="126" width="192" height="7" rx="3.5" fill="#ffffff" fill-opacity=".1"/>
<rect y="156" width="96" height="28" rx="8" fill="{$c2}" fill-opacity=".6"/>
</g>
<g transform="translate(0,216)">
<rect width="114" height="12" rx="6" fill="#ffffff" fill-opacity=".14"/>
<rect x="130" width="114" height="12" rx="6" fill="#ffffff" fill-opacity=".08"/>
<rect x="260" width="114" height="12" rx="6" fill="#ffffff" fill-opacity=".08"/>
</g>
SVG;
    }

    private static function layoutCommerce(string $c1, string $c2): string
    {
        $tiles = '';
        for ($i = 0; $i < 4; $i++) {
            $x = $i * 148;
            $tiles .= sprintf(
                '<rect x="%d" width="134" height="118" rx="12" fill="#ffffff" fill-opacity=".06"/>'
                . '<rect x="%d" y="12" width="110" height="62" rx="9" fill="%s" fill-opacity="%.2f"/>'
                . '<rect x="%d" y="84" width="78" height="7" rx="3.5" fill="#ffffff" fill-opacity=".16"/>'
                . '<rect x="%d" y="98" width="46" height="7" rx="3.5" fill="%s" fill-opacity=".7"/>',
                $x,
                $x + 12, $i % 2 ? $c2 : $c1, 0.3 + $i * 0.06,
                $x + 12,
                $x + 12, $i % 2 ? $c1 : $c2
            );
        }
        return <<<SVG
<rect width="180" height="12" rx="6" fill="#ffffff" fill-opacity=".2"/>
<rect x="428" width="156" height="30" rx="9" fill="#ffffff" fill-opacity=".07"/>
<rect x="444" y="11" width="80" height="8" rx="4" fill="#ffffff" fill-opacity=".14"/>
<g transform="translate(0,44)">{$tiles}</g>
<g transform="translate(0,182)">
<rect width="584" height="60" rx="12" fill="{$c1}" fill-opacity=".18"/>
<rect x="20" y="18" width="190" height="10" rx="5" fill="#ffffff" fill-opacity=".22"/>
<rect x="20" y="36" width="140" height="7" rx="3.5" fill="#ffffff" fill-opacity=".12"/>
<rect x="448" y="16" width="116" height="28" rx="8" fill="{$c2}" fill-opacity=".75"/>
</g>
SVG;
    }

    /** Compact square avatar/logo tile for lists and cart lines. */
    public static function tile(string $key, string $initials = ''): string
    {
        [$c1, $c2, $bg] = self::palette($key);
        $id = 't' . substr(hash('xxh128', $key), 0, 8);
        $text = $initials !== ''
            ? '<text x="32" y="40" font-family="ui-sans-serif,system-ui,sans-serif" font-size="20" font-weight="700" fill="#fff" fill-opacity=".92" text-anchor="middle">'
                . htmlspecialchars(mb_substr($initials, 0, 2), ENT_QUOTES) . '</text>'
            : '';
        return <<<SVG
<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
<defs><linearGradient id="{$id}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$c1}"/><stop offset="1" stop-color="{$c2}"/></linearGradient></defs>
<rect width="64" height="64" fill="{$bg}"/><rect width="64" height="64" fill="url(#{$id})" fill-opacity=".55"/>
{$text}</svg>
SVG;
    }
}
