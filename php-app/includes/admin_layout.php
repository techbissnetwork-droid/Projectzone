<?php
const ADMIN_NAV = [
    ['path' => 'index.php', 'label' => 'Dashboard', 'icon' => 'grid'],
    ['path' => 'businesses.php', 'label' => 'Businesses', 'icon' => 'box'],
    ['path' => 'tickets.php', 'label' => 'Tickets', 'icon' => 'chat'],
    ['path' => 'products.php', 'label' => 'Products', 'icon' => 'star'],
    ['path' => 'staff.php', 'label' => 'Staff', 'icon' => 'users'],
    ['path' => 'settings.php', 'label' => 'Settings', 'icon' => 'settings'],
];

function admin_header(array $staff, string $active): string
{
    $links = '';
    foreach (ADMIN_NAV as $item) {
        $cls = $item['path'] === $active ? 'admin-nav-link active' : 'admin-nav-link';
        $links .= '<a href="' . e($item['path']) . '" class="' . $cls . '">' . e($item['label']) . '</a>';
    }
    return '<header class="admin-header">'
        . '<div class="container admin-header-inner">'
        . '<a href="index.php" class="logo" aria-label="TECHBISS admin home">'
        . '<span class="logo-mark"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="6" fill="var(--accent-1)"/><rect x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="#fff2ea"/><rect x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="#fff2ea"/></svg></span>'
        . '<b>techbiss</b><span class="admin-badge">admin</span>'
        . '</a>'
        . '<nav class="admin-nav" aria-label="Admin sections">' . $links . '</nav>'
        . '<div class="admin-header-actions">'
        . '<span class="admin-who">' . e($staff['name']) . '<small>' . e($staff['role']) . '</small></span>'
        . '<a href="logout.php" class="btn btn-ghost btn-sm" aria-label="Log out">' . ico('logout') . ' <span>Log out</span></a>'
        . '</div>'
        . '</div>'
        . '</header>';
}

function admin_bottomnav(string $active): string
{
    $primary = array_slice(ADMIN_NAV, 0, 3);
    $overflow = array_slice(ADMIN_NAV, 3);
    $activeInOverflow = false;

    $items = '';
    foreach ($primary as $item) {
        $cls = $item['path'] === $active ? 'dock-item active' : 'dock-item';
        $items .= '<a href="' . e($item['path']) . '" class="' . $cls . '">' . ico($item['icon']) . '<span>' . e($item['label']) . '</span></a>';
    }

    $panel = '';
    foreach ($overflow as $item) {
        $isActive = $item['path'] === $active;
        if ($isActive) {
            $activeInOverflow = true;
        }
        $cls = $isActive ? 'dock-menu-link active' : 'dock-menu-link';
        $panel .= '<a href="' . e($item['path']) . '" class="' . $cls . '">' . ico($item['icon']) . '<span>' . e($item['label']) . '</span></a>';
    }

    $summaryCls = $activeInOverflow ? 'dock-item active' : 'dock-item';
    $items .= '<details class="dock-menu"><summary class="' . $summaryCls . '">' . ico('menu') . '<span>Menu</span></summary>'
        . '<div class="dock-menu-panel">' . $panel . '</div></details>';

    return '<nav class="bottom-dock admin-bottomnav" aria-label="Admin quick access">' . $items . '</nav>';
}

function admin_flash_html(): string
{
    $f = get_flash();
    if (!$f) {
        return '';
    }
    $tone = $f['type'] === 'error' ? 'danger' : 'success';
    return '<p class="badge ' . $tone . '" style="margin-bottom:20px;">' . e($f['message']) . '</p>';
}
