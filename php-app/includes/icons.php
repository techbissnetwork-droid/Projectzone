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
