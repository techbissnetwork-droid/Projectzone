<?php
/**
 * Definitions for every editable list in the admin area. One generic CRUD
 * screen (admin/resource.php) renders all of them, so adding a new editable
 * list means adding an entry here, not another admin page.
 *
 * Field types: text, textarea, number, select, checkbox.
 */

function resources(): array
{
    $sizes = ['a' => 'Large (2 wide, 2 tall)', 'b' => 'Wide (2 wide)', 'c' => 'Small (1 x 1)'];

    return [
        'services' => [
            'label'    => 'Services',
            'singular' => 'service',
            'note'     => 'Shown as the bento grid on the home page and as the detail rows on the services page.',
            'list'     => ['code', 'title', 'size', 'is_active'],
            'fields'   => [
                'sort'        => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'code'        => ['label' => 'Number badge', 'type' => 'text', 'hint' => 'e.g. 01'],
                'anchor'      => ['label' => 'Link anchor', 'type' => 'text', 'hint' => 'Letters and dashes only, e.g. websites. Used in the page address.'],
                'title'       => ['label' => 'Title', 'type' => 'text', 'required' => true],
                'summary'     => ['label' => 'Short description', 'type' => 'textarea', 'hint' => 'Shown on the home page card.'],
                'size'        => ['label' => 'Card size on home page', 'type' => 'select', 'options' => $sizes, 'default' => 'c'],
                'kicker'      => ['label' => 'Detail kicker', 'type' => 'text', 'hint' => 'Small line above the heading on the services page.'],
                'heading'     => ['label' => 'Detail heading', 'type' => 'text'],
                'body'        => ['label' => 'Detail paragraph', 'type' => 'textarea'],
                'bullets'     => ['label' => 'Bullet points', 'type' => 'textarea', 'hint' => 'One per line.'],
                'panel_title' => ['label' => 'Status panel title', 'type' => 'text'],
                'panel'       => ['label' => 'Status panel rows', 'type' => 'textarea', 'hint' => 'One per line, written as: label | value'],
                'is_active'   => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'industries' => [
            'label'    => 'Industries',
            'singular' => 'industry',
            'note'     => 'The colour cards and the detail grid on the industries page.',
            'list'     => ['code', 'name', 'gradient', 'is_active'],
            'fields'   => [
                'sort'      => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'code'      => ['label' => 'Number badge', 'type' => 'text'],
                'anchor'    => ['label' => 'Link anchor', 'type' => 'text'],
                'name'      => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'blurb'     => ['label' => 'Card blurb', 'type' => 'text', 'hint' => 'One short line on the colour card.'],
                'gradient'  => ['label' => 'Card colour', 'type' => 'select', 'options' => [
                    1 => '1 — lime', 2 => '2 — cyan', 3 => '3 — pink', 4 => '4 — violet',
                    5 => '5 — amber', 6 => '6 — teal', 7 => '7 — green', 8 => '8 — rose',
                ], 'default' => 1],
                'kicker'    => ['label' => 'Detail kicker', 'type' => 'text'],
                'heading'   => ['label' => 'Detail heading', 'type' => 'text'],
                'summary'   => ['label' => 'Detail paragraph', 'type' => 'textarea'],
                'bullets'   => ['label' => 'Bullet points', 'type' => 'textarea', 'hint' => 'One per line.'],
                'is_active' => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'tiers' => [
            'label'    => 'Pricing packages',
            'singular' => 'package',
            'note'     => 'Both the one-off build packages and the monthly care plans.',
            'list'     => ['grp', 'name', 'price', 'is_featured', 'is_active'],
            'fields'   => [
                'sort'        => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'grp'         => ['label' => 'Group', 'type' => 'select', 'options' => ['build' => 'Build package (one-off)', 'care' => 'Care plan (monthly)'], 'default' => 'build'],
                'name'        => ['label' => 'Package name', 'type' => 'text', 'required' => true],
                'price'       => ['label' => 'Price', 'type' => 'text', 'hint' => 'e.g. $690 or From $4,500'],
                'period'      => ['label' => 'Price note', 'type' => 'text', 'hint' => 'e.g. ONE-OFF · 2 WEEKS'],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'features'    => ['label' => 'What is included', 'type' => 'textarea', 'hint' => 'One per line.'],
                'tag'         => ['label' => 'Ribbon label', 'type' => 'text', 'hint' => 'e.g. Most chosen. Leave empty for none.'],
                'cta_label'   => ['label' => 'Button text', 'type' => 'text'],
                'is_featured' => ['label' => 'Highlight this package', 'type' => 'checkbox', 'default' => 0],
                'is_active'   => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'addons' => [
            'label'    => 'Add-ons',
            'singular' => 'add-on',
            'note'     => 'The grid of extras on the pricing page.',
            'list'     => ['code', 'title', 'size', 'is_active'],
            'fields'   => [
                'sort'        => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'code'        => ['label' => 'Number badge', 'type' => 'text'],
                'title'       => ['label' => 'Title', 'type' => 'text', 'required' => true],
                'description' => ['label' => 'Description', 'type' => 'textarea'],
                'size'        => ['label' => 'Card size', 'type' => 'select', 'options' => $sizes, 'default' => 'c'],
                'is_active'   => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'compare_rows' => [
            'label'    => 'Comparison table',
            'singular' => 'table row',
            'note'     => 'Column headings are set under Page text → Pricing.',
            'list'     => ['label', 'col1', 'col2', 'col3', 'is_active'],
            'fields'   => [
                'sort'      => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'label'     => ['label' => 'Row label', 'type' => 'text', 'required' => true],
                'col1'      => ['label' => 'Column 1', 'type' => 'text'],
                'col2'      => ['label' => 'Column 2', 'type' => 'text'],
                'col3'      => ['label' => 'Column 3', 'type' => 'text'],
                'is_active' => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'faqs' => [
            'label'    => 'FAQs',
            'singular' => 'question',
            'note'     => 'Assign each question to the page it should appear on.',
            'list'     => ['page', 'question', 'is_active'],
            'fields'   => [
                'sort'      => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'page'      => ['label' => 'Show on page', 'type' => 'select', 'options' => [
                    'services' => 'Services', 'pricing' => 'Pricing', 'contact' => 'Contact',
                ], 'default' => 'services'],
                'question'  => ['label' => 'Question', 'type' => 'text', 'required' => true],
                'answer'    => ['label' => 'Answer', 'type' => 'textarea'],
                'is_active' => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'testimonials' => [
            'label'    => 'Testimonials',
            'singular' => 'testimonial',
            'list'     => ['name', 'role', 'is_active'],
            'fields'   => [
                'sort'      => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'quote'     => ['label' => 'Quote', 'type' => 'textarea', 'required' => true, 'hint' => 'No need to add quotation marks.'],
                'name'      => ['label' => 'Name', 'type' => 'text', 'required' => true],
                'role'      => ['label' => 'Role and company', 'type' => 'text'],
                'avatar'    => ['label' => 'Initials', 'type' => 'text', 'hint' => 'Up to 4 characters. Left empty, they are taken from the name.'],
                'is_active' => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'stats' => [
            'label'    => 'Statistics',
            'singular' => 'statistic',
            'note'     => 'The counting numbers on the home and about pages.',
            'list'     => ['value', 'suffix', 'label', 'is_active'],
            'fields'   => [
                'sort'      => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'value'     => ['label' => 'Number', 'type' => 'text', 'required' => true, 'hint' => 'Digits only, e.g. 180 or 99.9'],
                'suffix'    => ['label' => 'Suffix', 'type' => 'text', 'hint' => 'e.g. + or % or h'],
                'label'     => ['label' => 'Caption', 'type' => 'textarea'],
                'is_active' => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'steps' => [
            'label'    => 'Process steps',
            'singular' => 'step',
            'note'     => 'The numbered "how a project goes live" rail on the home page.',
            'list'     => ['code', 'title', 'is_active'],
            'fields'   => [
                'sort'      => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'code'      => ['label' => 'Number', 'type' => 'text'],
                'title'     => ['label' => 'Title', 'type' => 'text', 'required' => true],
                'body'      => ['label' => 'Description', 'type' => 'textarea'],
                'is_active' => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'rules' => [
            'label'    => 'Working rules',
            'singular' => 'rule',
            'note'     => 'The "how we work" grid on the about page.',
            'list'     => ['code', 'title', 'size', 'is_active'],
            'fields'   => [
                'sort'      => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'code'      => ['label' => 'Number badge', 'type' => 'text'],
                'title'     => ['label' => 'Title', 'type' => 'text', 'required' => true],
                'body'      => ['label' => 'Description', 'type' => 'textarea'],
                'size'      => ['label' => 'Card size', 'type' => 'select', 'options' => $sizes, 'default' => 'c'],
                'is_active' => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],

        'team' => [
            'label'    => 'Team',
            'singular' => 'team member',
            'list'     => ['name', 'role', 'is_active'],
            'fields'   => [
                'sort'      => ['label' => 'Order', 'type' => 'number', 'default' => 0],
                'initial'   => ['label' => 'Avatar letter', 'type' => 'text'],
                'name'      => ['label' => 'Name or role title', 'type' => 'text', 'required' => true],
                'role'      => ['label' => 'Small caption', 'type' => 'text'],
                'body'      => ['label' => 'Description', 'type' => 'textarea'],
                'is_active' => ['label' => 'Visible on the site', 'type' => 'checkbox', 'default' => 1],
            ],
        ],
    ];
}

function resource(string $key): array
{
    $all = resources();
    if (!isset($all[$key])) {
        http_response_code(404);
        exit('Unknown section.');
    }
    return $all[$key] + ['table' => $key, 'key' => $key];
}

/** Pull a resource's fields out of $_POST, coerced to the declared type. */
function resource_input(array $res): array
{
    $data = [];
    foreach ($res['fields'] as $name => $f) {
        $data[$name] = match ($f['type']) {
            'checkbox' => isset($_POST[$name]) ? 1 : 0,
            'number'   => (int) post($name, (string) ($f['default'] ?? 0)),
            default    => post($name, ''),
        };
    }
    return $data;
}

/** Returns a list of human-readable problems, empty when the input is valid. */
function resource_validate(array $res, array $data): array
{
    $errors = [];
    foreach ($res['fields'] as $name => $f) {
        if (!empty($f['required']) && trim((string) $data[$name]) === '') {
            $errors[] = $f['label'] . ' is required.';
        }
        if ($f['type'] === 'select' && $data[$name] !== '' && !array_key_exists($data[$name], $f['options'])) {
            $errors[] = $f['label'] . ' is not a valid choice.';
        }
    }
    return $errors;
}
