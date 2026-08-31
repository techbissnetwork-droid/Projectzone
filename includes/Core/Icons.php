<?php
declare(strict_types=1);

namespace Techbiss\Core;

/**
 * Inline SVG icon set.
 *
 * Icons are inlined rather than loaded from a sprite or an icon font: there is
 * no extra request, they inherit currentColor, and they cannot cause a layout
 * shift. All paths are drawn on a 24×24 grid with a 1.5 stroke.
 */
final class Icons
{
    /** @var array<string,string> icon name → path markup */
    private const PATHS = [
        // Brand / product
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
        'server'    => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
        'window'    => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 6.5h.01M10 6.5h.01"/>',
        'layers'    => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'device'    => '<rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'palette'   => '<path d="M12 21a9 9 0 1 1 9-9c0 2-1.6 3-3.2 3H16a2 2 0 0 0-1.6 3.2A1.8 1.8 0 0 1 12 21Z"/><circle cx="8" cy="11" r="1"/><circle cx="12" cy="8" r="1"/><circle cx="16" cy="11" r="1"/>',
        'cart'      => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.2l2.3 11.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.2L21 7H6"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.9-3.9"/>',
        'spark'     => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6.3 6.3l2.8 2.8M14.9 14.9l2.8 2.8M17.7 6.3l-2.8 2.8M9.1 14.9l-2.8 2.8"/>',
        'shield'    => '<path d="M12 3 4.5 6v6c0 4.4 3.1 7.9 7.5 9 4.4-1.1 7.5-4.6 7.5-9V6L12 3Z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'trend'     => '<path d="m3 17 5.5-5.5 3.5 3.5L21 6"/><path d="M15 6h6v6"/>',
        'chat'      => '<path d="M20 15a2 2 0 0 1-2 2H8l-4 3.5V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2Z"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'rocket'    => '<path d="M13.5 3.5c3.5 0 7 3.5 7 7 0 0-2.2 4.6-6 7.2l-4.2-4.2C13 9.7 13.5 3.5 13.5 3.5Z"/><path d="M10.3 13.5 6.5 17.3M8.5 20c-1.5 1-4 1.5-4 1.5s.5-2.5 1.5-4"/><circle cx="15.5" cy="8.5" r="1.5"/>',
        'building'  => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01M10 21v-4.5h4V21"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'inbox'     => '<path d="M3.5 12.5h4l1.5 3h6l1.5-3h4"/><path d="M5.2 5h13.6l2.2 7.5V18a1.5 1.5 0 0 1-1.5 1.5H4.5A1.5 1.5 0 0 1 3 18v-5.5L5.2 5Z"/>',
        'pin'       => '<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.5 19.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 14a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 7.5l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 3.6V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.5 1.5l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1Z"/>',
        'crown'     => '<path d="m3 7 4 4 5-6 5 6 4-4v10.5A1.5 1.5 0 0 1 19.5 19h-15A1.5 1.5 0 0 1 3 17.5V7Z"/>',
        'package'   => '<path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9L12 3Z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
        'sparkle'   => '<path d="M12 3.5 13.8 9l5.7 1.8-5.7 1.8L12 18l-1.8-5.4L4.5 10.8 10.2 9 12 3.5Z"/><path d="M18.5 16.5 19.3 19l2.2.8-2.2.8-.8 2.4"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 12.5h18"/>',
        'factory'   => '<path d="M3 20V10l5 3V10l5 3V10l5 3V6h3v14H3Z"/><path d="M7 16h.01M12 16h.01M17 16h.01"/>',
        'chart'     => '<path d="M4 20V9M10 20V4M16 20v-7M22 20H2"/>',
        'key'       => '<circle cx="8" cy="12" r="4"/><path d="M12 12h9M17.5 12v3.5M20 12v2.5"/>',
        'hardhat'   => '<path d="M3.5 17.5h17M5.5 15.5V13a6.5 6.5 0 0 1 13 0v2.5"/><path d="M10 7.5V4h4v3.5"/>',
        'compass'   => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5.5-5.5 2 2-5.5 5.5-2Z"/>',
        'graduation'=> '<path d="m12 4 10 4.5-10 4.5L2 8.5 12 4Z"/><path d="M6 11v4.5c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V11"/>',
        'heartbeat' => '<path d="M3 12h4l2-4 3 8 2.5-5 1.5 3h5"/><path d="M20.8 8.6A4.6 4.6 0 0 0 12 6.6a4.6 4.6 0 0 0-8.8 2"/>',
        'bed'       => '<path d="M3 18v-8M3 14h18v4M21 18v-4a3 3 0 0 0-3-3h-7v3"/><circle cx="7" cy="10.5" r="2"/>',
        'shop'      => '<path d="M4 9h16v10a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19V9Z"/><path d="M3 9 5 4h14l2 5M9 20.5V14h6v6.5"/>',
        'utensils'  => '<path d="M6 3v8a2.5 2.5 0 0 0 5 0V3M8.5 11v10"/><path d="M17 3c-1.5 1.5-2 3-2 5s.7 3 2 3v10"/>',

        // UI
        'check'       => '<path d="m5 12.5 4.5 4.5L19 7"/>',
        'check-circle'=> '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.2 2.4 2.4 4.6-5"/>',
        'x'           => '<path d="M6 6l12 12M18 6 6 18"/>',
        'x-circle'    => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
        'minus'       => '<path d="M5 12h14"/>',
        'arrow-right' => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'arrow-left'  => '<path d="M20 12H5M11 6l-6 6 6 6"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'chevron-down'=> '<path d="m6 9 6 6 6-6"/>',
        'chevron-right'=> '<path d="m9 6 6 6-6 6"/>',
        'chevron-left'=> '<path d="m15 6-6 6 6 6"/>',
        'menu'        => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'star'        => '<path d="m12 3.8 2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8L3.5 10l5.9-.9L12 3.8Z"/>',
        'quote'       => '<path d="M9.5 6C6.5 7.3 5 9.7 5 13v5h6v-6H8c0-2 .5-3.4 2.3-4.3L9.5 6Zm9 0c-3 1.3-4.5 3.7-4.5 7v5h6v-6h-3c0-2 .5-3.4 2.3-4.3L18.5 6Z"/>',
        'phone'       => '<path d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7 2 2 0 0 1 6.5 3.5Z"/>',
        'whatsapp'    => '<path d="M3.5 20.5 5 16.6A8 8 0 1 1 8 19.4l-4.5 1.1Z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5.6 0 1-.4 1-1v-.8l-1.8-.7-.9 1a5.6 5.6 0 0 1-2.3-2.3l1-.9-.7-1.8H10c-.6 0-1 .4-1 1Z"/>',
        'linkedin'    => '<rect x="3.5" y="3.5" width="17" height="17" rx="2.5"/><path d="M8 10.5V16M8 7.6v.01M12 16v-3.2c0-1 .7-1.8 1.7-1.8s1.8.8 1.8 1.8V16M12 10.5V16"/>',
        'facebook'    => '<path d="M14.5 8.5H16V6h-1.8c-1.8 0-3.2 1.4-3.2 3.2v1.3H9v2.5h2v6.5h2.5V13h2l.5-2.5h-2.5V9.5c0-.6.4-1 1-1Z"/>',
        'instagram'   => '<rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="3.8"/><path d="M17 7h.01"/>',
        'youtube'     => '<rect x="2.5" y="6" width="19" height="12" rx="3.5"/><path d="m10.5 9.5 5 2.5-5 2.5v-5Z"/>',
        'github'      => '<path d="M9 20c-4 1.2-4-2.2-5.5-2.7M15 21v-3.4a2.9 2.9 0 0 0-.8-2.3c2.7-.3 5.5-1.3 5.5-6a4.7 4.7 0 0 0-1.3-3.2 4.3 4.3 0 0 0-.1-3.2s-1-.3-3.4 1.3a11.7 11.7 0 0 0-6 0C6.5 2.6 5.5 2.9 5.5 2.9a4.3 4.3 0 0 0-.1 3.2A4.7 4.7 0 0 0 4 9.3c0 4.7 2.8 5.7 5.5 6a2.9 2.9 0 0 0-.8 2.3V21"/>',
        'dribbble'    => '<circle cx="12" cy="12" r="9"/><path d="M5 8.5c4.5.5 9.5-.5 13-3M3.5 14c4-2 9.5-2 13.5 1.5M9 3.8c3 3.8 5 8.6 5.5 16"/>',
        'sun'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'        => '<path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5Z"/>',
        'edit'        => '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17v3Z"/><path d="m14.5 6.5 3 3"/>',
        'trash'       => '<path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M6 7l1 12.5A1.5 1.5 0 0 0 8.5 21h7a1.5 1.5 0 0 0 1.5-1.5L18 7"/>',
        'eye'         => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'     => '<path d="M4 4l16 16M10 6a8.6 8.6 0 0 1 2-.2c6 0 9.5 6.2 9.5 6.2a15 15 0 0 1-3 3.6M6.5 8.2A15 15 0 0 0 2.5 12S6 18.2 12 18.2c1 0 1.9-.2 2.7-.5"/>',
        'copy'        => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'upload'      => '<path d="M12 15V4M8 8l4-4 4 4"/><path d="M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/>',
        'download'    => '<path d="M12 4v11M8 11l4 4 4-4"/><path d="M4 15v3.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V15"/>',
        'image'       => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-4.5 4 3.5 3-2.5 4 3.5"/>',
        'users'       => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.7a3.5 3.5 0 0 1 0 6.6M17.5 14.2A6.5 6.5 0 0 1 21.5 20"/>',
        'user'        => '<circle cx="12" cy="8" r="3.8"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'lock'        => '<rect x="4.5" y="10" width="15" height="10.5" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'logout'      => '<path d="M15 5.5V4a1.5 1.5 0 0 0-1.5-1.5h-8A1.5 1.5 0 0 0 4 4v16a1.5 1.5 0 0 0 1.5 1.5h8A1.5 1.5 0 0 0 15 20v-1.5"/><path d="M10 12h11M17.5 8.5 21 12l-3.5 3.5"/>',
        'dashboard'   => '<rect x="3" y="3" width="7.5" height="8.5" rx="1.5"/><rect x="13.5" y="3" width="7.5" height="5" rx="1.5"/><rect x="13.5" y="10.5" width="7.5" height="10.5" rx="1.5"/><rect x="3" y="14" width="7.5" height="7" rx="1.5"/>',
        'file'        => '<path d="M13 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M13 3v6h6"/>',
        'tag'         => '<path d="M3.5 11.2V4.5A1 1 0 0 1 4.5 3.5h6.7a2 2 0 0 1 1.4.6l7.3 7.3a2 2 0 0 1 0 2.8l-5.7 5.7a2 2 0 0 1-2.8 0L4.1 12.6a2 2 0 0 1-.6-1.4Z"/><path d="M8 8h.01"/>',
        'calendar'    => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'filter'      => '<path d="M3.5 5h17l-6.5 8v6l-4 2v-8L3.5 5Z"/>',
        'external'    => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 19V8a1.5 1.5 0 0 1 1.5-1.5H10"/>',
        'alert'       => '<path d="M12 3.5 21.5 20H2.5L12 3.5Z"/><path d="M12 10v4M12 17h.01"/>',
        'help'        => '<circle cx="12" cy="12" r="9"/><path d="M9.8 9.5a2.3 2.3 0 1 1 3 2.2c-.5.2-.8.7-.8 1.3v.5M12 16.8h.01"/>',
        'grid'        => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'drag'        => '<circle cx="9" cy="6" r="1.3"/><circle cx="15" cy="6" r="1.3"/><circle cx="9" cy="12" r="1.3"/><circle cx="15" cy="12" r="1.3"/><circle cx="9" cy="18" r="1.3"/><circle cx="15" cy="18" r="1.3"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.3l3.2 1.9"/>',
        'link'        => '<path d="M10.5 13.5a4 4 0 0 0 5.7 0l2.8-2.8a4 4 0 1 0-5.7-5.7l-1.4 1.4"/><path d="M13.5 10.5a4 4 0 0 0-5.7 0l-2.8 2.8a4 4 0 1 0 5.7 5.7l1.4-1.4"/>',
        'refresh'     => '<path d="M20 12a8 8 0 1 1-2.5-5.8"/><path d="M20 4v4.5h-4.5"/>',
        'database'    => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'money'       => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.8"/><path d="M6 10v.01M18 14v.01"/>',
        'target'      => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/>',
        'send'        => '<path d="m21 3-9.5 9.5M21 3l-6.5 18-3.5-8.5L2.5 9 21 3Z"/>',
    ];

    public static function svg(string $name, string $class = 'icon'): string
    {
        $path = self::PATHS[$name] ?? self::PATHS['spark'];
        return '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8')
            . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . $path . '</svg>';
    }

    public static function has(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }

    /** @return array<int,string> icon names offered in admin pickers */
    public static function names(): array
    {
        return array_keys(self::PATHS);
    }
}
