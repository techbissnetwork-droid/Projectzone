<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\App;
use Techbiss\Core\Cache;
use Techbiss\Core\Request;
use Techbiss\Core\Str;
use Techbiss\Repo\SectionRepo;
use Techbiss\Repo\SettingsRepo;

final class SettingsController extends BaseAdminController
{
    private SettingsRepo $repo;

    /** Human labels for the setting groups, in sidebar order. */
    private const GROUPS = [
        'general'    => ['label' => 'Company', 'icon' => 'building', 'permission' => 'settings.manage'],
        'contact'    => ['label' => 'Contact details', 'icon' => 'mail', 'permission' => 'settings.manage'],
        'social'     => ['label' => 'Social links', 'icon' => 'link', 'permission' => 'settings.manage'],
        'seo'        => ['label' => 'Global SEO', 'icon' => 'search', 'permission' => 'seo.manage'],
        'appearance' => ['label' => 'Appearance', 'icon' => 'palette', 'permission' => 'settings.manage'],
        'commerce'   => ['label' => 'Pricing & payments', 'icon' => 'money', 'permission' => 'settings.manage'],
        'system'     => ['label' => 'System', 'icon' => 'settings', 'permission' => 'settings.manage'],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->repo = App::settings();
    }

    public function index(Request $request): void
    {
        $group = $request->queryString('group', 'general');
        if (!isset(self::GROUPS[$group])) {
            $group = 'general';
        }
        $this->authorize(self::GROUPS[$group]['permission']);

        $this->view->render('settings/index', [
            'title'      => 'Settings',
            'group'      => $group,
            'groups'     => self::GROUPS,
            'rows'       => $this->repo->group($group),
        ]);
    }

    public function update(Request $request): never
    {
        $group = $request->str('group', 'general');
        if (!isset(self::GROUPS[$group])) {
            $group = 'general';
        }
        $this->authorize(self::GROUPS[$group]['permission']);
        $this->verify($request);

        $rows    = $this->repo->group($group);
        $updates = [];
        $errors  = [];

        foreach ($rows as $row) {
            $key   = (string) $row['key_name'];
            $type  = (string) $row['type'];
            $value = $request->post($key);

            switch ($type) {
                case 'bool':
                    $value = $request->bool($key) ? '1' : '0';
                    break;

                case 'image':
                    $value = trim((string) $value);
                    if ($value !== '' && !preg_match('#^(https?://|uploads/)#', $value)) {
                        $errors[$key] = 'Choose an image from the media library or paste a full URL.';
                        continue 2;
                    }
                    $value = mb_substr($value, 0, 500);
                    break;

                case 'select':
                case 'text':
                    $value = trim((string) $value);
                    if (mb_strlen($value) > 500) {
                        $errors[$key] = 'This value is too long.';
                        continue 2;
                    }
                    break;

                case 'textarea':
                default:
                    $value = trim((string) $value);
                    if (mb_strlen($value) > 20000) {
                        $errors[$key] = 'This value is too long.';
                        continue 2;
                    }
            }

            // Targeted checks for the settings whose format actually matters.
            if (in_array($key, ['contact_email', 'sales_email', 'support_email', 'notification_email'], true)
                && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$key] = 'Enter a valid email address.';
                continue;
            }
            if (str_starts_with($key, 'social_') && $value !== '') {
                if (!preg_match('#^https?://#i', $value)) {
                    $value = 'https://' . $value;
                }
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[$key] = 'Enter a valid URL.';
                    continue;
                }
            }
            if ($key === 'google_analytics_id' && $value !== '' && !preg_match('/^(G|UA|GTM)-[A-Za-z0-9-]{4,}$/', $value)) {
                $errors[$key] = 'Enter a valid measurement ID such as G-XXXXXXXXXX.';
                continue;
            }
            if ($key === 'items_per_page') {
                $n = (int) $value;
                $value = (string) max(3, min(48, $n === 0 ? 9 : $n));
            }
            if ($key === 'payment_methods') {
                $allowed = ['bank_transfer', 'manual', 'stripe', 'paypal', 'local'];
                $picked  = array_values(array_intersect(array_map('trim', explode(',', $value)), $allowed));
                $value   = implode(',', $picked);
            }
            if ($key === 'theme_mode' && !in_array($value, ['dark', 'light'], true)) {
                $value = 'dark';
            }

            $updates[$key] = $value;
        }

        if ($errors !== []) {
            $this->fail($request, $errors, 'Please correct the highlighted settings.', '/admin/settings?group=' . urlencode($group));
        }

        $this->repo->setMany($updates);
        Cache::flush();
        ActivityLog::record('update', 'settings', null, 'Updated ' . self::GROUPS[$group]['label'] . ' settings');
        $this->ok('Settings saved.', '/admin/settings?group=' . urlencode($group));
    }

    // -----------------------------------------------------------------
    // Homepage content blocks
    // -----------------------------------------------------------------
    public function sections(Request $request): void
    {
        $this->authorize('content.manage');
        $repo = new SectionRepo();
        $this->view->render('settings/sections', [
            'title' => 'Homepage content',
            'rows'  => $repo->allWithCounts('home'),
        ]);
    }

    public function editSection(Request $request, array $params): void
    {
        $this->authorize('content.manage');
        $repo = new SectionRepo();
        $row  = $repo->find((int) $params['id']);
        if ($row === null) {
            flash('error', 'Section not found.');
            redirect('/admin/homepage');
        }
        $this->view->render('settings/section-form', [
            'title' => 'Edit “' . $row['heading'] . '”',
            'row'   => $row,
            'items' => $repo->items((int) $row['id']),
        ]);
    }

    public function updateSection(Request $request, array $params): never
    {
        $this->authorize('content.manage');
        $this->verify($request);
        $id   = (int) $params['id'];
        $repo = new SectionRepo();

        $row = $repo->find($id);
        if ($row === null) {
            flash('error', 'Section not found.');
            redirect('/admin/homepage');
        }

        $ctaUrl = trim($request->str('cta_url'));
        if ($ctaUrl !== '' && !preg_match('#^(https?://|/)#', $ctaUrl)) {
            $ctaUrl = '/' . ltrim($ctaUrl, '/');
        }

        $repo->updateRow($id, [
            'eyebrow'      => mb_substr($request->str('eyebrow'), 0, 120),
            'heading'      => mb_substr($request->str('heading'), 0, 255),
            'subheading'   => mb_substr($request->str('subheading'), 0, 500),
            'body'         => mb_substr($request->str('body'), 0, 5000),
            'cta_label'    => mb_substr($request->str('cta_label'), 0, 80),
            'cta_url'      => mb_substr($ctaUrl, 0, 500),
            'image'        => preg_match('#^(https?://|uploads/)#', $request->str('image')) ? mb_substr($request->str('image'), 0, 500) : '',
            'is_published' => $request->bool('is_published') ? 1 : 0,
        ]);

        $repo->replaceItems($id, $request->rows('items'));
        Cache::flush();
        ActivityLog::record('update', 'page_sections', $id, 'Updated homepage section: ' . $row['section_key']);
        $this->ok('Section updated.', '/admin/homepage/' . $id . '/edit');
    }

    public function toggleSection(Request $request, array $params): never
    {
        $this->authorize('content.manage');
        $this->verify($request);
        $value = (new SectionRepo())->toggle((int) $params['id'], 'is_published');
        Cache::flush();
        ActivityLog::record('toggle', 'page_sections', (int) $params['id'], 'is_published set to ' . $value);
        if ($request->wantsJson()) {
            json_response(['ok' => true, 'value' => $value]);
        }
        $this->back('/admin/homepage');
    }

    public function reorderSections(Request $request): never
    {
        $this->authorize('content.manage');
        $this->verify($request);
        (new SectionRepo())->reorder(array_map('intval', $request->arr('order')));
        Cache::flush();
        json_response(['ok' => true, 'message' => 'Sections reordered.']);
    }
}
