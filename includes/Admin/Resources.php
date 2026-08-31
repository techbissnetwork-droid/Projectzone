<?php
declare(strict_types=1);

namespace Techbiss\Admin;

/**
 * Declarative registry for the straightforward content types.
 *
 * Each entry describes a table, its list columns and its form fields; one
 * generic controller then handles create / edit / delete / publish / reorder
 * for all of them. Content types with genuinely bespoke behaviour — portfolio,
 * packages, purchases, media, navigation, settings, users — have their own
 * controllers instead of being forced through this.
 */
final class Resources
{
    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        return [
            // -------------------------------------------------------------
            'services' => [
                'table'      => 'services',
                'singular'   => 'Service',
                'plural'     => 'Services',
                'icon'       => 'layers',
                'permission' => 'content.manage',
                'group'      => 'Content',
                'public_url' => '/services/{slug}',
                'orderable'  => true,
                'searchable' => ['name', 'tagline', 'short_description'],
                'columns'    => [
                    ['key' => 'name', 'label' => 'Service', 'primary' => true, 'sub' => 'tagline'],
                    ['key' => 'slug', 'label' => 'Slug', 'type' => 'mono'],
                    ['key' => 'is_featured', 'label' => 'Featured', 'type' => 'toggle'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 190],
                    ['key' => 'slug', 'label' => 'URL slug', 'type' => 'slug', 'from' => 'name'],
                    ['key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'max' => 255, 'hint' => 'One line shown under the service name.'],
                    ['key' => 'short_description', 'label' => 'Short description', 'type' => 'textarea', 'max' => 500, 'hint' => 'Used on cards and listings.'],
                    ['key' => 'description', 'label' => 'Full description', 'type' => 'richtext'],
                    ['key' => 'deliverables', 'label' => 'Deliverables', 'type' => 'lines', 'hint' => 'One per line.'],
                    ['key' => 'process_note', 'label' => 'Process note', 'type' => 'textarea', 'max' => 1000],
                    ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon'],
                    ['key' => 'accent', 'label' => 'Accent colour', 'type' => 'accent'],
                    ['key' => 'image', 'label' => 'Image', 'type' => 'media'],
                    ['key' => 'starting_price', 'label' => 'Starting price', 'type' => 'decimal', 'hint' => 'Leave empty to show “scoped to requirements” instead.'],
                    ['key' => 'price_note', 'label' => 'Price note', 'type' => 'text', 'max' => 120],
                    ['key' => 'is_featured', 'label' => 'Feature on the homepage', 'type' => 'bool'],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool', 'default' => 1],
                    ['key' => 'seo_title', 'label' => 'SEO title', 'type' => 'text', 'max' => 190, 'group' => 'seo'],
                    ['key' => 'seo_description', 'label' => 'Meta description', 'type' => 'textarea', 'max' => 320, 'group' => 'seo'],
                    ['key' => 'og_image', 'label' => 'Social share image', 'type' => 'media', 'group' => 'seo'],
                ],
                'repeater' => [
                    'title'   => 'Key points',
                    'table'   => 'service_features',
                    'foreign' => 'service_id',
                    'fields'  => [
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                        ['key' => 'description', 'label' => 'Description', 'type' => 'text'],
                    ],
                ],
            ],

            // -------------------------------------------------------------
            'industries' => [
                'table'      => 'industries',
                'singular'   => 'Industry',
                'plural'     => 'Industries',
                'icon'       => 'building',
                'permission' => 'content.manage',
                'group'      => 'Content',
                'public_url' => '/industries/{slug}',
                'orderable'  => true,
                'searchable' => ['name', 'tagline', 'short_description'],
                'columns'    => [
                    ['key' => 'name', 'label' => 'Industry', 'primary' => true, 'sub' => 'tagline'],
                    ['key' => 'slug', 'label' => 'Slug', 'type' => 'mono'],
                    ['key' => 'is_featured', 'label' => 'Featured', 'type' => 'toggle'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 190],
                    ['key' => 'slug', 'label' => 'URL slug', 'type' => 'slug', 'from' => 'name'],
                    ['key' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'max' => 255],
                    ['key' => 'short_description', 'label' => 'Short description', 'type' => 'textarea', 'max' => 500],
                    ['key' => 'description', 'label' => 'Full description', 'type' => 'richtext'],
                    ['key' => 'challenges', 'label' => 'Common challenges', 'type' => 'lines', 'hint' => 'One per line.'],
                    ['key' => 'solutions', 'label' => 'How we approach it', 'type' => 'lines', 'hint' => 'One per line.'],
                    ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon'],
                    ['key' => 'image', 'label' => 'Image', 'type' => 'media'],
                    ['key' => 'services', 'label' => 'Related services', 'type' => 'services'],
                    ['key' => 'is_featured', 'label' => 'Feature on the homepage', 'type' => 'bool'],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool', 'default' => 1],
                    ['key' => 'seo_title', 'label' => 'SEO title', 'type' => 'text', 'max' => 190, 'group' => 'seo'],
                    ['key' => 'seo_description', 'label' => 'Meta description', 'type' => 'textarea', 'max' => 320, 'group' => 'seo'],
                    ['key' => 'og_image', 'label' => 'Social share image', 'type' => 'media', 'group' => 'seo'],
                ],
            ],

            // -------------------------------------------------------------
            'pages' => [
                'table'      => 'pages',
                'singular'   => 'Page',
                'plural'     => 'Pages',
                'icon'       => 'file',
                'permission' => 'content.manage',
                'group'      => 'Content',
                'public_url' => '/{slug}',
                'orderable'  => true,
                'searchable' => ['title', 'slug'],
                'columns'    => [
                    ['key' => 'title', 'label' => 'Page', 'primary' => true, 'sub' => 'subtitle'],
                    ['key' => 'slug', 'label' => 'URL', 'type' => 'mono'],
                    ['key' => 'template', 'label' => 'Template'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 190],
                    ['key' => 'slug', 'label' => 'URL slug', 'type' => 'slug', 'from' => 'title'],
                    ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text', 'max' => 120],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'max' => 500],
                    ['key' => 'content', 'label' => 'Content', 'type' => 'richtext'],
                    ['key' => 'template', 'label' => 'Template', 'type' => 'select', 'options' => ['default' => 'Default', 'legal' => 'Legal document']],
                    ['key' => 'hero_image', 'label' => 'Hero image', 'type' => 'media'],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool', 'default' => 1],
                    ['key' => 'noindex', 'label' => 'Hide from search engines', 'type' => 'bool', 'group' => 'seo'],
                    ['key' => 'seo_title', 'label' => 'SEO title', 'type' => 'text', 'max' => 190, 'group' => 'seo'],
                    ['key' => 'seo_description', 'label' => 'Meta description', 'type' => 'textarea', 'max' => 320, 'group' => 'seo'],
                    ['key' => 'og_image', 'label' => 'Social share image', 'type' => 'media', 'group' => 'seo'],
                ],
            ],

            // -------------------------------------------------------------
            'testimonials' => [
                'table'      => 'testimonials',
                'singular'   => 'Testimonial',
                'plural'     => 'Testimonials',
                'icon'       => 'quote',
                'permission' => 'content.manage',
                'group'      => 'Content',
                'orderable'  => true,
                'searchable' => ['client_name', 'company', 'quote'],
                'notice'     => 'Only publish feedback a client has actually given you and agreed to share. Never write a testimonial on a client’s behalf.',
                'columns'    => [
                    ['key' => 'client_name', 'label' => 'Client', 'primary' => true, 'sub' => 'company'],
                    ['key' => 'rating', 'label' => 'Rating', 'type' => 'rating'],
                    ['key' => 'is_featured', 'label' => 'Featured', 'type' => 'toggle'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'client_name', 'label' => 'Client name', 'type' => 'text', 'required' => true, 'max' => 190],
                    ['key' => 'company', 'label' => 'Company', 'type' => 'text', 'max' => 190],
                    ['key' => 'position', 'label' => 'Position', 'type' => 'text', 'max' => 190],
                    ['key' => 'quote', 'label' => 'Testimonial', 'type' => 'textarea', 'required' => true, 'max' => 2000],
                    ['key' => 'rating', 'label' => 'Rating', 'type' => 'select', 'options' => [5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star', 0 => 'No rating']],
                    ['key' => 'image', 'label' => 'Photo', 'type' => 'media'],
                    ['key' => 'portfolio_id', 'label' => 'Related project', 'type' => 'lookup', 'lookup' => ['table' => 'portfolio', 'label' => 'title']],
                    ['key' => 'is_featured', 'label' => 'Feature on the homepage', 'type' => 'bool'],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool'],
                ],
            ],

            // -------------------------------------------------------------
            'faqs' => [
                'table'      => 'faqs',
                'singular'   => 'FAQ',
                'plural'     => 'FAQs',
                'icon'       => 'help',
                'permission' => 'content.manage',
                'group'      => 'Content',
                'orderable'  => true,
                'searchable' => ['question', 'answer', 'category'],
                'columns'    => [
                    ['key' => 'question', 'label' => 'Question', 'primary' => true, 'truncate' => 80],
                    ['key' => 'category', 'label' => 'Category', 'type' => 'badge'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'question', 'label' => 'Question', 'type' => 'text', 'required' => true, 'max' => 500],
                    ['key' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'required' => true, 'max' => 5000],
                    ['key' => 'category', 'label' => 'Category', 'type' => 'text', 'max' => 80, 'default' => 'General', 'hint' => 'Questions are grouped by this on the FAQ page.'],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool', 'default' => 1],
                ],
            ],

            // -------------------------------------------------------------
            'process_steps' => [
                'table'      => 'process_steps',
                'singular'   => 'Process step',
                'plural'     => 'Process steps',
                'icon'       => 'list',
                'permission' => 'content.manage',
                'group'      => 'Content',
                'orderable'  => true,
                'searchable' => ['title', 'description'],
                'columns'    => [
                    ['key' => 'step_number', 'label' => '#', 'type' => 'mono'],
                    ['key' => 'title', 'label' => 'Step', 'primary' => true, 'sub' => 'duration'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'step_number', 'label' => 'Number', 'type' => 'text', 'max' => 6, 'default' => '01'],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 190],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'max' => 2000],
                    ['key' => 'duration', 'label' => 'Typical duration', 'type' => 'text', 'max' => 60],
                    ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon'],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool', 'default' => 1],
                ],
            ],

            // -------------------------------------------------------------
            'stats' => [
                'table'      => 'stats',
                'singular'   => 'Statistic',
                'plural'     => 'Statistics',
                'icon'       => 'chart',
                'permission' => 'content.manage',
                'group'      => 'Content',
                'orderable'  => true,
                'searchable' => ['label', 'value'],
                'notice'     => 'Only publish numbers you can stand behind. This table starts empty on purpose — nothing here is invented.',
                'columns'    => [
                    ['key' => 'label', 'label' => 'Label', 'primary' => true, 'sub' => 'description'],
                    ['key' => 'value', 'label' => 'Value', 'type' => 'mono'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'value', 'label' => 'Value', 'type' => 'text', 'required' => true, 'max' => 30, 'hint' => 'Numeric values animate on scroll.'],
                    ['key' => 'prefix', 'label' => 'Prefix', 'type' => 'text', 'max' => 10],
                    ['key' => 'suffix', 'label' => 'Suffix', 'type' => 'text', 'max' => 10, 'hint' => 'For example % or +'],
                    ['key' => 'label', 'label' => 'Label', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'text', 'max' => 255],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool', 'default' => 1],
                ],
            ],

            // -------------------------------------------------------------
            'portfolio_categories' => [
                'table'      => 'portfolio_categories',
                'singular'   => 'Portfolio category',
                'plural'     => 'Portfolio categories',
                'icon'       => 'grid',
                'permission' => 'portfolio.manage',
                'group'      => 'Content',
                'orderable'  => true,
                'searchable' => ['name'],
                'columns'    => [
                    ['key' => 'name', 'label' => 'Category', 'primary' => true, 'sub' => 'description'],
                    ['key' => 'slug', 'label' => 'Slug', 'type' => 'mono'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['key' => 'slug', 'label' => 'URL slug', 'type' => 'slug', 'from' => 'name'],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'max' => 500],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool', 'default' => 1],
                ],
            ],

            // -------------------------------------------------------------
            'portfolio_technologies' => [
                'table'      => 'portfolio_technologies',
                'singular'   => 'Technology',
                'plural'     => 'Technologies',
                'icon'       => 'tag',
                'permission' => 'portfolio.manage',
                'group'      => 'Content',
                'orderable'  => true,
                'searchable' => ['name'],
                'timestamps' => false,
                'columns'    => [
                    ['key' => 'name', 'label' => 'Technology', 'primary' => true],
                    ['key' => 'slug', 'label' => 'Slug', 'type' => 'mono'],
                ],
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['key' => 'slug', 'label' => 'URL slug', 'type' => 'slug', 'from' => 'name'],
                ],
            ],

            // -------------------------------------------------------------
            'blog_categories' => [
                'table'      => 'blog_categories',
                'singular'   => 'Blog category',
                'plural'     => 'Blog categories',
                'icon'       => 'grid',
                'permission' => 'blog.manage',
                'group'      => 'Content',
                'orderable'  => true,
                'searchable' => ['name'],
                'public_url' => '/blog?category={slug}',
                'columns'    => [
                    ['key' => 'name', 'label' => 'Category', 'primary' => true, 'sub' => 'description'],
                    ['key' => 'slug', 'label' => 'Slug', 'type' => 'mono'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['key' => 'slug', 'label' => 'URL slug', 'type' => 'slug', 'from' => 'name'],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'max' => 500],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool', 'default' => 1],
                ],
            ],

            // -------------------------------------------------------------
            'blog_tags' => [
                'table'      => 'blog_tags',
                'singular'   => 'Tag',
                'plural'     => 'Blog tags',
                'icon'       => 'tag',
                'permission' => 'blog.manage',
                'group'      => 'Content',
                'orderable'  => false,
                'timestamps' => false,
                'searchable' => ['name'],
                'order_by'   => 'name ASC',
                'columns'    => [
                    ['key' => 'name', 'label' => 'Tag', 'primary' => true],
                    ['key' => 'slug', 'label' => 'Slug', 'type' => 'mono'],
                ],
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['key' => 'slug', 'label' => 'URL slug', 'type' => 'slug', 'from' => 'name'],
                ],
            ],

            // -------------------------------------------------------------
            'package_addons' => [
                'table'      => 'package_addons',
                'singular'   => 'Add-on',
                'plural'     => 'Add-ons',
                'icon'       => 'plus',
                'permission' => 'packages.manage',
                'group'      => 'Packages',
                'orderable'  => true,
                'searchable' => ['name', 'description'],
                'columns'    => [
                    ['key' => 'name', 'label' => 'Add-on', 'primary' => true, 'sub' => 'description'],
                    ['key' => 'price', 'label' => 'Price', 'type' => 'money'],
                    ['key' => 'billing_period', 'label' => 'Billing', 'type' => 'badge'],
                    ['key' => 'is_published', 'label' => 'Status', 'type' => 'status'],
                ],
                'fields' => [
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 190],
                    ['key' => 'slug', 'label' => 'URL slug', 'type' => 'slug', 'from' => 'name'],
                    ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'max' => 500],
                    ['key' => 'price', 'label' => 'Price', 'type' => 'decimal', 'required' => true],
                    ['key' => 'currency', 'label' => 'Currency', 'type' => 'text', 'max' => 6, 'default' => 'USD'],
                    ['key' => 'billing_period', 'label' => 'Billing period', 'type' => 'select', 'options' => ['one-time' => 'One-time', 'monthly' => 'Monthly', 'yearly' => 'Yearly']],
                    ['key' => 'icon', 'label' => 'Icon', 'type' => 'icon'],
                    ['key' => 'is_published', 'label' => 'Published', 'type' => 'bool', 'default' => 1],
                ],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        $all = self::all();
        if (!isset($all[$key])) {
            return null;
        }
        return $all[$key] + ['key' => $key];
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }
}
