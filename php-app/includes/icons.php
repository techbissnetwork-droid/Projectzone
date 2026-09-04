<?php
// Small subset of the front-end's icon set, ported to PHP for the
// server-rendered admin panel.
$ICONS = [
    'chart' => '<path d="M4 20V10M11 20V4M18 20v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    'shield' => '<path d="M12 3 5 6v5c0 5 3 7.5 7 10 4-2.5 7-5 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    'users' => '<circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 19c.7-3 3-5 5.5-5s4.8 2 5.5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="17" cy="9" r="2.4" stroke="currentColor" stroke-width="1.6"/><path d="M15.8 14c2.1.3 3.8 2 4.4 4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
    'chat' => '<path d="M4 6h16v10H9l-4 3.5V16H4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>',
    'box' => '<path d="M3.5 8 12 4l8.5 4-8.5 4-8.5-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M3.5 8v8L12 20l8.5-4V8M12 12v8" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
    'star' => '<path d="M12 3.4l2.7 5.5 6 .9-4.4 4.2 1 6-5.3-2.8-5.3 2.8 1-6-4.4-4.2 6-.9L12 3.4Z" fill="currentColor"/>',
    'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
    'grid' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6" stroke="currentColor" stroke-width="1.7"/>',
    'plus' => '<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    'edit' => '<path d="M4 20h4L18.5 9.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
    'trash' => '<path d="M4 7h16M9 7V4.8c0-.4.4-.8.8-.8h4.4c.4 0 .8.4.8.8V7M6.5 7l.7 12.3c0 .9.8 1.7 1.7 1.7h6.2c.9 0 1.7-.8 1.7-1.7L17.5 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
    'close' => '<path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>',
    'settings' => '<circle cx="12" cy="12" r="2.8" stroke="currentColor" stroke-width="1.7"/><path d="M19.4 13.5a1.7 1.7 0 0 0 .35 1.9l.05.05a2.1 2.1 0 1 1-3 3l-.05-.05a1.7 1.7 0 0 0-1.9-.35 1.7 1.7 0 0 0-1 1.55V20a2.1 2.1 0 1 1-4.2 0v-.08a1.7 1.7 0 0 0-1.1-1.55 1.7 1.7 0 0 0-1.9.35l-.05.05a2.1 2.1 0 1 1-3-3l.05-.05a1.7 1.7 0 0 0 .35-1.9 1.7 1.7 0 0 0-1.55-1H2a2.1 2.1 0 1 1 0-4.2h.08a1.7 1.7 0 0 0 1.55-1.1 1.7 1.7 0 0 0-.35-1.9l-.05-.05a2.1 2.1 0 1 1 3-3l.05.05a1.7 1.7 0 0 0 1.9.35H8.3a1.7 1.7 0 0 0 1-1.55V2a2.1 2.1 0 1 1 4.2 0v.08a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.9-.35l.05-.05a2.1 2.1 0 1 1 3 3l-.05.05a1.7 1.7 0 0 0-.35 1.9V8.3a1.7 1.7 0 0 0 1.55 1H22a2.1 2.1 0 1 1 0 4.2h-.08a1.7 1.7 0 0 0-1.55 1Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/>',
];

function ico(string $name, string $cls = ''): string
{
    global $ICONS;
    $path = $ICONS[$name] ?? $ICONS['star'];
    return '<svg class="icon ' . e($cls) . '" viewBox="0 0 24 24" fill="none" aria-hidden="true">' . $path . '</svg>';
}

function blob_icon(string $name, string $size = '', bool $soft = false): string
{
    return '<div class="blob-icon ' . e($size) . ' ' . ($soft ? 'soft' : '') . '">' . ico($name) . '</div>';
}
