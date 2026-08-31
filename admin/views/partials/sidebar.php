<?php
/**
 * Admin navigation. Sections only appear when the signed-in role can use them,
 * so a Content Manager never sees an empty Commerce group.
 * @var string $currentPath @var array $badges
 */
use Techbiss\Core\Auth;

$groups = [
    [
        'label' => '',
        'items' => [
            ['label' => 'Dashboard', 'url' => '/admin', 'icon' => 'dashboard', 'permission' => 'dashboard.view', 'exact' => true],
        ],
    ],
    [
        'label' => 'Content',
        'items' => [
            ['label' => 'Pages',        'url' => '/admin/pages',        'icon' => 'file',     'permission' => 'content.manage'],
            ['label' => 'Homepage',     'url' => '/admin/homepage',     'icon' => 'window',   'permission' => 'content.manage'],
            ['label' => 'Services',     'url' => '/admin/services',     'icon' => 'layers',   'permission' => 'content.manage'],
            ['label' => 'Portfolio',    'url' => '/admin/portfolio',    'icon' => 'image',    'permission' => 'portfolio.manage'],
            ['label' => 'Industries',   'url' => '/admin/industries',   'icon' => 'building', 'permission' => 'content.manage'],
            ['label' => 'Blog',         'url' => '/admin/blog',         'icon' => 'edit',     'permission' => 'blog.manage'],
            ['label' => 'Testimonials', 'url' => '/admin/testimonials', 'icon' => 'quote',    'permission' => 'content.manage'],
            ['label' => 'FAQs',         'url' => '/admin/faqs',         'icon' => 'help',     'permission' => 'content.manage'],
            ['label' => 'Process steps','url' => '/admin/process_steps','icon' => 'list',     'permission' => 'content.manage'],
            ['label' => 'Statistics',   'url' => '/admin/stats',        'icon' => 'chart',    'permission' => 'content.manage'],
        ],
    ],
    [
        'label' => 'Taxonomies',
        'items' => [
            ['label' => 'Portfolio categories', 'url' => '/admin/portfolio_categories',   'icon' => 'grid', 'permission' => 'portfolio.manage'],
            ['label' => 'Technologies',       'url' => '/admin/portfolio_technologies', 'icon' => 'tag',  'permission' => 'portfolio.manage'],
            ['label' => 'Blog categories',    'url' => '/admin/blog_categories',        'icon' => 'grid', 'permission' => 'blog.manage'],
            ['label' => 'Blog tags',          'url' => '/admin/blog_tags',              'icon' => 'tag',  'permission' => 'blog.manage'],
            ['label' => 'Project categories', 'url' => '/admin/project_categories',     'icon' => 'grid', 'permission' => 'projects.manage'],
        ],
    ],
    [
        'label' => 'Commerce',
        'items' => [
            ['label' => 'Packages',          'url' => '/admin/packages',       'icon' => 'package',  'permission' => 'packages.manage'],
            ['label' => 'Add-ons',           'url' => '/admin/package_addons', 'icon' => 'plus',     'permission' => 'packages.manage'],
            ['label' => 'Purchases',         'url' => '/admin/purchases',      'icon' => 'money',    'permission' => 'purchases.manage', 'badge' => 'purchases'],
            ['label' => 'Premade projects',  'url' => '/admin/projects',       'icon' => 'rocket',   'permission' => 'projects.manage'],
            ['label' => 'Project enquiries', 'url' => '/admin/project-orders', 'icon' => 'inbox',    'permission' => 'project_orders.manage', 'badge' => 'project_orders'],
            ['label' => 'Customers',         'url' => '/admin/customers',      'icon' => 'users',    'permission' => 'customers.manage'],
        ],
    ],
    [
        'label' => 'Leads',
        'items' => [
            ['label' => 'Contact messages', 'url' => '/admin/messages',    'icon' => 'inbox', 'permission' => 'leads.view', 'badge' => 'messages'],
            ['label' => 'Quote requests',   'url' => '/admin/quotes',      'icon' => 'send',  'permission' => 'leads.view', 'badge' => 'quotes'],
            ['label' => 'Subscribers',      'url' => '/admin/subscribers', 'icon' => 'mail',  'permission' => 'leads.view'],
        ],
    ],
    [
        'label' => 'Website',
        'items' => [
            ['label' => 'Navigation',     'url' => '/admin/navigation',            'icon' => 'link',     'permission' => 'navigation.manage'],
            ['label' => 'Media library',  'url' => '/admin/media',                 'icon' => 'image',    'permission' => 'media.manage'],
            ['label' => 'Global settings','url' => '/admin/settings',              'icon' => 'settings', 'permission' => 'settings.manage'],
            ['label' => 'SEO',            'url' => '/admin/settings?group=seo',    'icon' => 'search',   'permission' => 'seo.manage'],
        ],
    ],
    [
        'label' => 'System',
        'items' => [
            ['label' => 'Admin users',  'url' => '/admin/users',  'icon' => 'user',     'permission' => 'users.manage'],
            ['label' => 'Roles',        'url' => '/admin/roles',  'icon' => 'lock',     'permission' => 'roles.manage'],
            ['label' => 'Activity log', 'url' => '/admin/logs',   'icon' => 'clock',    'permission' => 'logs.view'],
            ['label' => 'Maintenance',  'url' => '/admin/system', 'icon' => 'database', 'permission' => 'settings.manage'],
        ],
    ],
];

$isActive = static function (array $item) use ($currentPath): bool {
    $path = parse_url($item['url'], PHP_URL_PATH) ?? $item['url'];
    if (!empty($item['exact'])) {
        return $currentPath === $path || $currentPath === rtrim($path, '/') . '/';
    }
    return $currentPath === $path || str_starts_with($currentPath, $path . '/');
};
?>
<aside class="sidebar" id="admin-sidebar" aria-label="Admin sidebar">
    <div class="sidebar__head">
        <a class="brand" href="<?= e(url('/admin')) ?>">
            <svg class="brand__mark" aria-hidden="true" focusable="false"><use href="#tb-mark"/></svg>
            <span class="brand__text" style="font-size:.92rem"><?= e($settings->get('site_name', 'TECHBISS')) ?></span>
        </a>
    </div>

    <nav class="sidebar__nav" aria-label="Admin sections">
        <?php foreach ($groups as $group):
            $visible = array_values(array_filter($group['items'], static fn ($i) => Auth::can($i['permission'])));
            if ($visible === []) { continue; }
        ?>
        <div class="sidebar__group">
            <?php if ($group['label'] !== ''): ?>
            <div class="sidebar__label"><?= e($group['label']) ?></div>
            <?php endif; ?>
            <?php foreach ($visible as $item):
                $active = $isActive($item);
                $count  = !empty($item['badge']) ? (int) ($badges[$item['badge']] ?? 0) : 0;
            ?>
            <a class="sidebar__link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($item['url'])) ?>"
               <?= $active ? 'aria-current="page"' : '' ?>>
                <?= icon($item['icon']) ?>
                <span><?= e($item['label']) ?></span>
                <?php if ($count > 0): ?>
                <span class="sidebar__count"><?= $count > 99 ? '99+' : $count ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__foot">
        <div class="row row--tight" style="margin-bottom:.6rem">
            <span class="avatar avatar--sm"><?= e(initials((string) ($user['name'] ?? '?'))) ?></span>
            <div style="min-width:0;flex:1">
                <div style="font-size:var(--fs-xs);font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= e($user['name'] ?? '') ?>
                </div>
                <div style="font-size:.68rem;color:var(--text-faint)"><?= e($user['role_name'] ?? '') ?></div>
            </div>
        </div>
        <form method="post" action="<?= e(url('/admin/logout')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm btn--block" type="submit"><?= icon('logout') ?>Sign out</button>
        </form>
    </div>
</aside>
