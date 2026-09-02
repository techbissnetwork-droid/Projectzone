<?php
/**
 * Definitions for the tables the admin edits in the same way — list, add,
 * edit, reorder, delete. admin/resource.php reads these; adding a new
 * editable table means adding an entry here and nothing else.
 *
 * Field types: text, textarea, lines, number, money, date, select, check,
 *              image, slug.
 */

function resources(): array
{
    return [

        'services' => [
            'title'     => 'Services',
            'singular'  => 'Service',
            'table'     => 'services',
            'blurb'     => 'The ten things you sell. These appear on the home page, the services page and in the contact form.',
            'order'     => 'sort, id',
            'slug_from' => 'title',
            'list'      => ['title' => 'Service', 'subtitle' => 'Strapline', 'is_active' => 'Live'],
            'fields'    => [
                ['title',    'Name',        'text',     'half', ''],
                ['subtitle', 'Strapline',   'text',     'half', 'The short line under the name.'],
                ['slug',     'Web address', 'slug',     'half', 'Used for the link on the services page.'],
                ['icon',     'Icon',        'text',     'half', 'A single character, e.g. ◈ ◐ ◇ ▣ ⛨ ✉ ◎ ✦ ⟲ ☎'],
                ['body',     'Description', 'textarea', 'full', ''],
                ['bullets',  'What is included', 'lines', 'full', 'One per line.'],
                ['sort',     'Order',       'number',   'half', 'Lower numbers come first.'],
                ['is_active','Show on the site', 'check', 'half', ''],
            ],
        ],

        'industries' => [
            'title'     => 'Industries',
            'singular'  => 'Industry',
            'table'     => 'industries',
            'blurb'     => 'The sector cards on the industries page.',
            'order'     => 'sort, id',
            'slug_from' => 'title',
            'list'      => ['title' => 'Sector', 'is_active' => 'Live'],
            'fields'    => [
                ['title',    'Name',        'text',     'half', ''],
                ['slug',     'Web address', 'slug',     'half', ''],
                ['icon',     'Icon',        'text',     'half', 'A single character.'],
                ['sort',     'Order',       'number',   'half', ''],
                ['body',     'Description', 'textarea', 'full', ''],
                ['bullets',  'What they need', 'lines', 'full', 'One per line.'],
                ['is_active','Show on the site', 'check', 'half', ''],
            ],
        ],

        'packages' => [
            'title'    => 'Pricing packages',
            'singular' => 'Package',
            'table'    => 'packages',
            'blurb'    => 'Build packages are one-off. Care plans are monthly. Both appear on the pricing page.',
            'order'    => 'kind, sort, id',
            'list'     => ['name' => 'Package', 'kind' => 'Type', 'price' => 'Price', 'is_active' => 'Live'],
            'fields'   => [
                ['name',       'Name',   'text',   'half', ''],
                ['kind',       'Type',   'select', 'half', '', ['build' => 'Build (one-off)', 'care' => 'Care plan (monthly)']],
                ['price',      'Price',  'text',   'half', 'Numbers only, no currency symbol. Leave blank for "Quoted".'],
                ['period',     'Period', 'text',   'half', 'e.g. One-off, per month, To scope'],
                ['blurb',      'Who it is for', 'text', 'full', 'One line, shown under the price.'],
                ['features',   'What is included', 'lines', 'full', 'One per line.'],
                ['is_featured','Highlight this one', 'check', 'half', 'Marks it as "most chosen".'],
                ['sort',       'Order',  'number', 'half', ''],
                ['is_active',  'Show on the site', 'check', 'half', ''],
            ],
        ],

        'addons' => [
            'title'    => 'Add-ons',
            'singular' => 'Add-on',
            'table'    => 'addons',
            'blurb'    => 'The extras listed on the pricing page.',
            'order'    => 'sort, id',
            'list'     => ['name' => 'Add-on', 'price' => 'Price'],
            'fields'   => [
                ['name',  'Name',  'text',   'half', ''],
                ['price', 'Price', 'text',   'half', 'Numbers only. Leave blank for "Quoted".'],
                ['blurb', 'What it covers', 'text', 'full', ''],
                ['sort',  'Order', 'number', 'half', ''],
            ],
        ],

        'faqs' => [
            'title'    => 'FAQs',
            'singular' => 'Question',
            'table'    => 'faqs',
            'blurb'    => 'Shown on the services, pricing and contact pages.',
            'order'    => 'page, sort, id',
            'list'     => ['question' => 'Question', 'page' => 'Page'],
            'fields'   => [
                ['question', 'Question', 'text',   'full', ''],
                ['answer',   'Answer',   'textarea', 'full', ''],
                ['page',     'Which page', 'select', 'half', '', ['services' => 'Services & contact', 'pricing' => 'Pricing']],
                ['sort',     'Order',    'number', 'half', ''],
            ],
        ],

        'testimonials' => [
            'title'    => 'Testimonials',
            'singular' => 'Testimonial',
            'table'    => 'testimonials',
            'blurb'    => 'The rotating quotes on the home page. Only publish quotes you have permission to use.',
            'order'    => 'sort, id',
            'list'     => ['author' => 'Who', 'role' => 'Role', 'is_active' => 'Live'],
            'fields'   => [
                ['quote',    'Quote',  'textarea', 'full', 'Without quotation marks — the design adds them.'],
                ['author',   'Name',   'text',   'half', ''],
                ['role',     'Role or sector', 'text', 'half', 'e.g. Retail, 4 locations'],
                ['sort',     'Order',  'number', 'half', ''],
                ['is_active','Show on the site', 'check', 'half', ''],
            ],
        ],

        'portfolio' => [
            'title'      => 'Portfolio',
            'singular'   => 'Completed project',
            'table'      => 'portfolio',
            'blurb'      => 'Work you have finished. Set visibility to "Admin only" to keep a project in here without publishing it.',
            'order'      => 'is_featured DESC, sort, id DESC',
            'slug_from'  => 'title',
            'timestamps' => true,
            'image'      => 'cover_image',
            'list'       => [
                'cover_image'  => 'Image',
                'title'        => 'Project',
                'sector'       => 'Sector',
                'visibility'   => 'Visibility',
                'completed_on' => 'Completed',
            ],
            'view'   => 'project.php?slug=',
            'fields' => [
                ['title',        'Project title', 'text',   'half', ''],
                ['client_name',  'Client',        'text',   'half', 'Leave blank if they asked not to be named.'],
                ['slug',         'Web address',   'slug',   'half', ''],
                ['sector',       'Sector',        'text',   'half', 'e.g. Retail, Healthcare, Trades'],
                ['summary',      'One-line summary', 'text', 'full', 'Shown on the cards. Keep it to a sentence.'],
                ['body',         'The story',     'textarea', 'full', 'Leave a blank line between paragraphs.'],
                ['services_used','What you did',  'lines',  'half', 'One per line, e.g. Websites, Hosting.'],
                ['tech',         'Built with',    'lines',  'half', 'One per line, e.g. PHP, MySQL.'],
                ['live_url',     'Live address',  'text',   'half', 'https://…  Leave blank if it is not public.'],
                ['completed_on', 'Completed on',  'date',   'half', ''],
                ['cover_image',  'Cover image',   'image',  'full', 'Landscape works best. Under 4MB.'],
                ['visibility',   'Visibility',    'select', 'half', 'Admin only keeps it out of the public site entirely.',
                    ['public' => 'Public — show on the website', 'private' => 'Admin only — internal record']],
                ['is_featured',  'Feature this one', 'check', 'half', 'Featured projects come first.'],
                ['sort',         'Order',         'number', 'half', ''],
            ],
        ],

        'products' => [
            'title'      => 'Marketplace',
            'singular'   => 'Premade project',
            'table'      => 'products',
            'blurb'      => 'Finished projects people can buy. Orders arrive under Orders.',
            'order'      => 'is_featured DESC, sort, id DESC',
            'slug_from'  => 'title',
            'timestamps' => true,
            'image'      => 'cover_image',
            'list'       => [
                'cover_image' => 'Image',
                'title'       => 'Project',
                'category'    => 'Category',
                'price'       => 'Price',
                'is_active'   => 'Listed',
            ],
            'view'   => 'product.php?slug=',
            'fields' => [
                ['title',      'Title',        'text',   'half', ''],
                ['category',   'Category',     'text',   'half', 'e.g. Hospitality, Retail, Trades'],
                ['slug',       'Web address',  'slug',   'half', ''],
                ['pages',      'Pages included', 'number', 'half', 'Shown on the card. Zero hides it.'],
                ['summary',    'One-line summary', 'text', 'full', ''],
                ['body',       'Full description', 'textarea', 'full', 'Leave a blank line between paragraphs.'],
                ['features',   'What is included', 'lines', 'half', 'One per line.'],
                ['tech',       'Built with',   'lines',  'half', 'One per line.'],
                ['price',      'Price',        'money',  'half', 'Numbers only.'],
                ['sale_price', 'Sale price',   'money',  'half', 'Leave blank if it is not on sale.'],
                ['demo_url',   'Demo address', 'text',   'half', 'https://…  Leave blank if there is no demo.'],
                ['cover_image','Cover image',  'image',  'full', ''],
                ['is_active',  'List on the marketplace', 'check', 'half', ''],
                ['is_featured','Feature this one', 'check', 'half', ''],
                ['sort',       'Order',        'number', 'half', ''],
            ],
        ],
    ];
}

function resource(string $type): ?array
{
    $all = resources();
    if (!isset($all[$type])) {
        return null;
    }
    return $all[$type] + ['key' => $type];
}
