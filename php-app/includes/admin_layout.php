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
    $defaultTheme = get_setting('default_theme', 'auto');
    return '<header class="admin-header">'
        . '<div class="container admin-header-inner">'
        . '<a href="index.php" class="logo" aria-label="TECHBISS admin home">'
        . logo_mark_html(false)
        . '<b>techbiss</b><span class="admin-badge">admin</span>'
        . '</a>'
        . '<nav class="admin-nav" aria-label="Admin sections">' . $links . '</nav>'
        . '<div class="admin-header-actions">'
        . '<button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" aria-pressed="false">'
        . '<span class="theme-icon-wrap">'
        . '<svg class="theme-icon sun" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="1.8"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>'
        . '<svg class="theme-icon moon" viewBox="0 0 24 24" fill="none"><path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        . '</span></button>'
        . '<span class="admin-who">' . e($staff['name']) . '<small>' . e($staff['role']) . '</small></span>'
        . '<a href="logout.php" class="btn btn-ghost btn-sm" aria-label="Log out">' . ico('logout') . ' <span>Log out</span></a>'
        . '</div>'
        . '</div>'
        . '</header>'
        . '<script>(function(){'
        . 'var root=document.documentElement,DEF=' . json_encode($defaultTheme) . ';'
        . 'function apply(t,persist){ if(t){root.setAttribute("data-theme",t);}else{root.removeAttribute("data-theme");} if(persist!==false){try{localStorage.setItem("bloom-theme",t||"");}catch(e){}} var btn=document.getElementById("themeToggle"); if(btn){btn.setAttribute("aria-pressed", String(t==="dark"||(!t&&window.matchMedia("(prefers-color-scheme: dark)").matches)));} }'
        . 'var saved=null; try{saved=localStorage.getItem("bloom-theme");}catch(e){}'
        . 'apply(saved!==null?saved:(DEF!=="auto"?DEF:""), false);'
        . 'document.getElementById("themeToggle").addEventListener("click",function(){'
        . 'var cur=root.getAttribute("data-theme"); var isDark=cur?cur==="dark":window.matchMedia("(prefers-color-scheme: dark)").matches;'
        . 'apply(isDark?"light":"dark");'
        . '});'
        . '})();</script>';
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
