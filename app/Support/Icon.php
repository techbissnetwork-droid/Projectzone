<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Inline SVG icon set. Icons are emitted inline rather than fetched as a
 * sprite so they cost zero requests and paint with the first byte of HTML.
 * Every path is drawn on a 24×24 grid with a 1.6 stroke.
 */
final class Icon
{
    private const PATHS = [
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-up-right' => '<path d="M7 17 17 7M8 7h9v9"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'check' => '<path d="m4 12.5 5 5L20 6.5"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'x-circle' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'alert' => '<path d="M12 3.5 2.8 19.5h18.4z"/><path d="M12 9.5v4M12 16.8v.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
        'filter' => '<path d="M3 5h18M6.5 12h11M10 19h4"/>',
        'shield' => '<path d="M12 3 4.5 6v6c0 4.4 3.1 7.9 7.5 9 4.4-1.1 7.5-4.6 7.5-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'users' => '<circle cx="9" cy="8.5" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16.5 5.4a3.5 3.5 0 0 1 0 6.2M18 20a6.4 6.4 0 0 0-2-4.7"/>',
        'user' => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.8 20a7.2 7.2 0 0 1 14.4 0"/>',
        'briefcase' => '<rect x="3" y="7.5" width="18" height="12" rx="2"/><path d="M9 7.5V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1.5M3 12.5h18"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 12.5 9 5 9-5M3 16.5l9 5 9-5"/>',
        'compass' => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
        'cart' => '<path d="M3 4h2.2l2.3 11h9.6l2.1-8H6"/><circle cx="9.5" cy="19" r="1.4"/><circle cx="17" cy="19" r="1.4"/>',
        'spark' => '<path d="M12 3v5M12 16v5M3 12h5M16 12h5"/><path d="m6.8 6.8 2.8 2.8M14.4 14.4l2.8 2.8M17.2 6.8l-2.8 2.8M9.6 14.4l-2.8 2.8"/>',
        'trend' => '<path d="M3 17.5 9.5 11l4 4L21 7.5"/><path d="M15.5 7.5H21v5.5"/>',
        'cube' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9z"/><path d="M12 12 20 7.5M12 12v9M12 12 4 7.5"/>',
        'bank' => '<path d="M3 10 12 4l9 6"/><path d="M5.5 10v8M10 10v8M14 10v8M18.5 10v8M3 20h18"/>',
        'pulse' => '<path d="M3 12h4l2.5-6 4 12 2.5-6h5"/>',
        'route' => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.5 6H14a3.5 3.5 0 0 1 0 7h-4a3.5 3.5 0 0 0 0 7h5.5"/>',
        'building' => '<rect x="4" y="3.5" width="16" height="17" rx="1.5"/><path d="M8 8h2M14 8h2M8 12h2M14 12h2M10 20.5v-4h4v4"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
        'zap' => '<path d="M13 3 5 13.5h6L11 21l8-10.5h-6z"/>',
        'gauge' => '<path d="M4 17a9 9 0 1 1 16 0"/><path d="m12 13 4-3.5"/><circle cx="12" cy="14" r="1.4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.2 2"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3.5V6M16 3.5V6"/>',
        'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="2"/><path d="m3.6 6.8 8.4 6 8.4-6"/>',
        'phone' => '<path d="M6.5 3.5h3l1.5 4-2 1.4a12 12 0 0 0 6 6l1.4-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7a2 2 0 0 1 2-2.2z"/>',
        'pin' => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'download' => '<path d="M12 4v11M7.5 11 12 15.5 16.5 11M4.5 19.5h15"/>',
        'upload' => '<path d="M12 20V9M7.5 13.5 12 9l4.5 4.5M4.5 4.5h15"/>',
        'rocket' => '<path d="M13.5 4.5c3.5 0 6 2.5 6 6 0 4.2-4.6 8.2-7.5 10-2.9-1.8-7.5-5.8-7.5-10 0-3.5 2.5-6 6-6z"/><circle cx="12" cy="10" r="2.2"/><path d="M9 18.5 7 21M15 18.5 17 21"/>',
        'server' => '<rect x="3.5" y="4" width="17" height="6.5" rx="1.5"/><rect x="3.5" y="13.5" width="17" height="6.5" rx="1.5"/><path d="M7 7.2v.01M7 16.7v.01"/>',
        'database' => '<ellipse cx="12" cy="6" rx="7.5" ry="3"/><path d="M4.5 6v12c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3V6"/><path d="M4.5 12c0 1.7 3.4 3 7.5 3s7.5-1.3 7.5-3"/>',
        'code' => '<path d="m8 8-4.5 4L8 16M16 8l4.5 4L16 16M13.5 5.5l-3 13"/>',
        'terminal' => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><path d="m7.5 10 2.5 2-2.5 2M13 14h4"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 14.5a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1v.2a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-2.8-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H3.5a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 2.7-1.1V3.5a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.8 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7h.2a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1.1z"/>',
        'grid' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'file' => '<path d="M13.5 3.5H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9z"/><path d="M13.5 3.5V9H19"/>',
        'book' => '<path d="M4 5.5A2 2 0 0 1 6 3.5h13v14H6a2 2 0 0 0-2 2z"/><path d="M4 19.5a2 2 0 0 1 2-2h13v3H6a2 2 0 0 1-2-1z"/>',
        'tag' => '<path d="M3.5 11.5V4.5h7l9.5 9.5-7 7z"/><circle cx="7.6" cy="8.4" r="1.3"/>',
        'star' => '<path d="m12 3.6 2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.8l5.9-.8z"/>',
        'heart' => '<path d="M12 20s-7.5-4.7-7.5-9.7A4.3 4.3 0 0 1 12 7.4a4.3 4.3 0 0 1 7.5 2.9c0 5-7.5 9.7-7.5 9.7z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"/>',
        'moon' => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'external' => '<path d="M14 4h6v6M20 4l-8.5 8.5"/><path d="M18 14v4.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14-4.5L4 9"/><path d="M4 5v4h4"/><path d="M4 13a8 8 0 0 0 14 4.5L20 15"/><path d="M20 19v-4h-4"/>',
        'play' => '<path d="M8 5.5v13l11-6.5z"/>',
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'ticket' => '<path d="M3.5 9V6.5a1.5 1.5 0 0 1 1.5-1.5h14A1.5 1.5 0 0 1 20.5 6.5V9a3 3 0 0 0 0 6v2.5a1.5 1.5 0 0 1-1.5 1.5H5a1.5 1.5 0 0 1-1.5-1.5V15a3 3 0 0 0 0-6z"/><path d="M13 5v3M13 11v2M13 16v3"/>',
        'folder' => '<path d="M3.5 6.5A1.5 1.5 0 0 1 5 5h4l2 2.5h8A1.5 1.5 0 0 1 20.5 9v8.5A1.5 1.5 0 0 1 19 19H5a1.5 1.5 0 0 1-1.5-1.5z"/>',
        'linkedin' => '<rect x="3.5" y="3.5" width="17" height="17" rx="2.5"/><path d="M8 10.5v6M8 7.6v.01M12 16.5v-3.2a1.9 1.9 0 0 1 3.8 0v3.2M12 10.5v1"/>',
        'github' => '<path d="M9 19.5c-4 1.2-4-2.2-5.5-2.7m11 5v-3.4c0-1 .1-1.4-.5-2 2.6-.3 5-1.3 5-5.5a4.3 4.3 0 0 0-1.2-3 4 4 0 0 0-.1-3s-1-.3-3.3 1.2a11.3 11.3 0 0 0-6 0C6.1 4.1 5.1 4.4 5.1 4.4a4 4 0 0 0-.1 3 4.3 4.3 0 0 0-1.2 3c0 4.2 2.4 5.2 5 5.5-.6.6-.6 1.2-.5 2v3.4"/>',
        'x-social' => '<path d="M4 4l7.5 9.5L4.4 20M20 4l-7.4 8M9.5 4H4l16 16h-5.5"/>',
        'youtube' => '<rect x="2.5" y="6" width="19" height="12" rx="3.5"/><path d="m10 9.5 5 2.5-5 2.5z"/>',
    ];

    public static function has(string $name): bool
    {
        return isset(self::PATHS[$name]);
    }

    /**
     * @param array{class?:string,size?:int|string,stroke?:float|string,label?:string,fill?:bool} $options
     */
    public static function render(string $name, array $options = []): string
    {
        $path = self::PATHS[$name] ?? self::PATHS['info'];
        $class = $options['class'] ?? '';
        $size = $options['size'] ?? null;
        $stroke = $options['stroke'] ?? 1.6;
        $label = $options['label'] ?? null;
        $filled = (bool) ($options['fill'] ?? false);

        $attributes = [
            'viewBox' => '0 0 24 24',
            'fill' => $filled ? 'currentColor' : 'none',
            'stroke' => $filled ? 'none' : 'currentColor',
            'stroke-width' => $filled ? null : (string) $stroke,
            'stroke-linecap' => $filled ? null : 'round',
            'stroke-linejoin' => $filled ? null : 'round',
            'class' => $class !== '' ? $class : null,
            'width' => $size !== null ? (string) $size : null,
            'height' => $size !== null ? (string) $size : null,
            'aria-hidden' => $label === null ? 'true' : null,
            'role' => $label !== null ? 'img' : null,
            'focusable' => 'false',
        ];

        $rendered = '';
        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $rendered .= ' ' . $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
            }
        }

        $title = $label !== null
            ? '<title>' . htmlspecialchars($label, ENT_QUOTES) . '</title>'
            : '';

        return '<svg' . $rendered . '>' . $title . $path . '</svg>';
    }
}
