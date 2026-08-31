<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Admin\Resources;
use Techbiss\Core\ActivityLog;
use Techbiss\Core\Cache;
use Techbiss\Core\Database;
use Techbiss\Core\Paginator;
use Techbiss\Core\Request;
use Techbiss\Core\Str;
use Techbiss\Core\Validator;
use Techbiss\Repo\IndustryRepo;
use Techbiss\Repo\ServiceRepo;

/**
 * One controller serving every resource declared in Techbiss\Admin\Resources.
 * Field types in the registry drive both validation and form rendering, so
 * adding a content type is a matter of describing it, not writing CRUD again.
 */
final class ResourceController extends BaseAdminController
{
    private array $resource;
    private string $key;

    public function __construct(string $key)
    {
        parent::__construct();
        $resource = Resources::get($key);
        if ($resource === null) {
            http_response_code(404);
            $this->view->render('404', ['title' => 'Not found']);
            exit;
        }
        $this->resource = $resource;
        $this->key      = $key;
        $this->view->shareMany(['resource' => $resource, 'resourceKey' => $key]);
    }

    private function db(): Database
    {
        return Database::instance();
    }

    private function table(): string
    {
        return $this->resource['table'];
    }

    private function orderBy(): string
    {
        if (!empty($this->resource['order_by'])) {
            return $this->resource['order_by'];
        }
        return !empty($this->resource['orderable']) ? 'sort_order ASC, id ASC' : 'id DESC';
    }

    // =================================================================
    public function index(Request $request): void
    {
        $this->authorize($this->resource['permission']);

        $search  = mb_substr($request->queryString('q'), 0, 80);
        $status  = $request->queryString('status');
        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 25);

        $where  = ['1'];
        $params = [];
        if ($search !== '' && !empty($this->resource['searchable'])) {
            $parts = [];
            foreach ($this->resource['searchable'] as $col) {
                if (preg_match('/^[a-z_]+$/', $col)) {
                    $parts[]  = "`$col` LIKE ?";
                    $params[] = '%' . $search . '%';
                }
            }
            if ($parts) {
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
        }
        if ($status === 'published' && $this->hasColumn('is_published')) {
            $where[] = 'is_published = 1';
        } elseif ($status === 'draft' && $this->hasColumn('is_published')) {
            $where[] = 'is_published = 0';
        }

        $whereSql = implode(' AND ', $where);
        $total    = $this->db()->int('SELECT COUNT(*) FROM `' . $this->table() . "` WHERE $whereSql", $params);
        $pager    = new Paginator($page, $perPage, $total);
        $rows     = $this->db()->all(
            'SELECT * FROM `' . $this->table() . "` WHERE $whereSql ORDER BY " . $this->orderBy()
            . ' LIMIT ' . $perPage . ' OFFSET ' . $pager->offset(),
            $params
        );

        $this->view->render('resource/index', [
            'title'     => $this->resource['plural'],
            'rows'      => $rows,
            'paginator' => $pager,
            'search'    => $search,
            'status'    => $status,
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize($this->resource['permission']);
        $this->view->render('resource/form', [
            'title'  => 'New ' . strtolower($this->resource['singular']),
            'row'    => $this->defaults(),
            'isNew'  => true,
            'extras' => $this->formExtras(null),
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $this->authorize($this->resource['permission']);
        $id  = (int) $params['id'];
        $row = $this->db()->first('SELECT * FROM `' . $this->table() . '` WHERE id = ?', [$id]);
        if ($row === null) {
            flash('error', $this->resource['singular'] . ' not found.');
            redirect('/admin/' . $this->key);
        }
        $this->view->render('resource/form', [
            'title'  => 'Edit ' . strtolower($this->resource['singular']),
            'row'    => $row,
            'isNew'  => false,
            'extras' => $this->formExtras($id),
        ]);
    }

    public function store(Request $request): void
    {
        $this->authorize($this->resource['permission']);
        $this->verify($request);
        $this->save($request, null);
    }

    public function update(Request $request, array $params): void
    {
        $this->authorize($this->resource['permission']);
        $this->verify($request);
        $this->save($request, (int) $params['id']);
    }

    private function save(Request $request, ?int $id): never
    {
        [$data, $errors, $side] = $this->validateFields($request, $id);

        if ($errors !== []) {
            $this->fail(
                $request,
                $errors,
                'Please correct the highlighted fields.',
                '/admin/' . $this->key . ($id === null ? '/create' : '/' . $id . '/edit')
            );
        }

        $useTimestamps = ($this->resource['timestamps'] ?? true) !== false;
        $now = date('Y-m-d H:i:s');

        if ($id === null) {
            if ($this->hasColumn('sort_order') && !isset($data['sort_order'])) {
                $data['sort_order'] = (int) $this->db()->value(
                    'SELECT COALESCE(MAX(sort_order),0)+1 FROM `' . $this->table() . '`', [], 1
                );
            }
            if ($useTimestamps) {
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
            }
            $id = $this->db()->insert($this->table(), $data);
            ActivityLog::record('create', $this->key, $id, 'Created ' . strtolower($this->resource['singular']) . ': ' . $this->labelFor($data));
            $message = $this->resource['singular'] . ' created.';
        } else {
            if ($useTimestamps) {
                $data['updated_at'] = $now;
            }
            $this->db()->update($this->table(), $data, 'id', $id);
            ActivityLog::record('update', $this->key, $id, 'Updated ' . strtolower($this->resource['singular']) . ': ' . $this->labelFor($data));
            $message = $this->resource['singular'] . ' updated.';
        }

        $this->saveSideEffects($id, $side, $request);
        Cache::flush();
        $this->ok($message, '/admin/' . $this->key);
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize($this->resource['permission']);
        $this->verify($request);
        $id = (int) $params['id'];

        $row = $this->db()->first('SELECT * FROM `' . $this->table() . '` WHERE id = ?', [$id]);
        if ($row === null) {
            $this->ok($this->resource['singular'] . ' already removed.', '/admin/' . $this->key);
        }
        // Protect the pages the router depends on.
        if ($this->table() === 'pages' && (int) ($row['is_system'] ?? 0) === 1) {
            flash('error', 'This page is part of the site structure and cannot be deleted. Unpublish it instead.');
            redirect('/admin/' . $this->key);
        }

        $this->db()->delete($this->table(), 'id', $id);
        ActivityLog::record('delete', $this->key, $id, 'Deleted ' . strtolower($this->resource['singular']) . ': ' . $this->labelFor($row));
        Cache::flush();
        $this->ok($this->resource['singular'] . ' deleted.', '/admin/' . $this->key);
    }

    public function toggle(Request $request, array $params): never
    {
        $this->authorize($this->resource['permission']);
        $this->verify($request);

        $column = (string) $request->str('column', 'is_published');
        if (!in_array($column, ['is_published', 'is_featured', 'is_active'], true) || !$this->hasColumn($column)) {
            $this->ok('Nothing changed.', '/admin/' . $this->key);
        }
        $id      = (int) $params['id'];
        $current = (int) $this->db()->value('SELECT `' . $column . '` FROM `' . $this->table() . '` WHERE id = ?', [$id], 0);
        $new     = $current === 1 ? 0 : 1;
        $this->db()->run('UPDATE `' . $this->table() . '` SET `' . $column . '` = ? WHERE id = ?', [$new, $id]);
        ActivityLog::record('toggle', $this->key, $id, $column . ' set to ' . $new);
        Cache::flush();

        if ($request->wantsJson()) {
            json_response(['ok' => true, 'value' => $new]);
        }
        $this->back('/admin/' . $this->key);
    }

    public function reorder(Request $request): never
    {
        $this->authorize($this->resource['permission']);
        $this->verify($request);

        if (!$this->hasColumn('sort_order')) {
            json_response(['ok' => false, 'message' => 'This list cannot be reordered.'], 422);
        }
        $ids = array_map('intval', $request->arr('order'));
        $this->db()->transaction(function (Database $db) use ($ids): void {
            foreach (array_values($ids) as $pos => $id) {
                if ($id > 0) {
                    $db->run('UPDATE `' . $this->table() . '` SET sort_order = ? WHERE id = ?', [$pos + 1, $id]);
                }
            }
        });
        ActivityLog::record('reorder', $this->key, null, 'Reordered ' . strtolower($this->resource['plural']));
        Cache::flush();
        json_response(['ok' => true, 'message' => $this->resource['plural'] . ' reordered.']);
    }

    // =================================================================
    // Field handling
    // =================================================================

    /** @return array{0:array<string,mixed>,1:array<string,string>,2:array<string,mixed>} */
    private function validateFields(Request $request, ?int $id): array
    {
        $v    = Validator::make($request->all());
        $data = [];
        $side = [];

        foreach ($this->resource['fields'] as $field) {
            $key      = $field['key'];
            $type     = $field['type'];
            $required = !empty($field['required']);
            $max      = (int) ($field['max'] ?? 255);
            $label    = $field['label'];

            switch ($type) {
                case 'text':
                    $required ? $v->required($key, $label, 1, $max) : $v->optional($key, $max);
                    $data[$key] = (string) $v->get($key, '');
                    break;

                case 'slug':
                    $v->slug($key, $field['from'] ?? 'name');
                    $slug = (string) $v->get($key, '');
                    $data[$key] = $this->uniqueSlug($slug, $id);
                    break;

                case 'textarea':
                case 'lines':
                    $v->text($key, $max > 255 ? $max : 5000, $required, $label);
                    $data[$key] = (string) $v->get($key, '');
                    break;

                case 'richtext':
                    $v->html($key, $required, $label);
                    $data[$key] = (string) $v->get($key, '');
                    break;

                case 'decimal':
                    $v->decimal($key, $label, 0, 99999999.99, !$required);
                    $data[$key] = $v->get($key);
                    break;

                case 'number':
                    $v->int($key, $field['min'] ?? null, $field['max_value'] ?? null, $label);
                    $data[$key] = $v->get($key) ?? ($field['default'] ?? 0);
                    break;

                case 'bool':
                    $v->boolean($key);
                    $data[$key] = (int) $v->get($key, 0);
                    break;

                case 'select':
                    $options = array_map('strval', array_keys($field['options'] ?? []));
                    $v->in($key, $options, $label, $required);
                    $value = $v->get($key, '');
                    $data[$key] = ($value === '' && isset($field['default'])) ? $field['default'] : $value;
                    if (is_numeric($data[$key])) {
                        $data[$key] = (int) $data[$key];
                    }
                    break;

                case 'icon':
                    $value = $request->str($key);
                    $data[$key] = \Techbiss\Core\Icons::has($value) ? $value : ($field['default'] ?? 'spark');
                    break;

                case 'accent':
                    $allowed = ['cyan', 'violet', 'emerald', 'amber', 'rose', 'blue'];
                    $value = $request->str($key);
                    $data[$key] = in_array($value, $allowed, true) ? $value : 'cyan';
                    break;

                case 'media':
                    $value = trim($request->str($key));
                    // Accept an uploads-relative path or an absolute URL only.
                    if ($value !== '' && !preg_match('#^(https?://|uploads/)#', $value)) {
                        $value = '';
                    }
                    $data[$key] = mb_substr($value, 0, 500);
                    break;

                case 'lookup':
                    $lookupId = $request->int($key);
                    $data[$key] = $lookupId > 0 ? $lookupId : null;
                    break;

                case 'services':
                    $side['services'] = array_map('intval', $request->arr($key));
                    break;

                default:
                    $v->optional($key, $max);
                    $data[$key] = (string) $v->get($key, '');
            }
        }

        if (!empty($this->resource['repeater'])) {
            $side['repeater'] = $request->rows('repeater');
        }

        return [$data, $v->errors(), $side];
    }

    private function saveSideEffects(int $id, array $side, Request $request): void
    {
        if (isset($side['services'])) {
            if ($this->table() === 'industries') {
                (new IndustryRepo())->syncServices($id, $side['services']);
            }
        }

        if (isset($side['repeater']) && !empty($this->resource['repeater'])) {
            $cfg = $this->resource['repeater'];
            if ($cfg['table'] === 'service_features') {
                (new ServiceRepo())->replaceFeatures($id, $side['repeater']);
            }
        }
    }

    /** Values shown when the create form is first opened. */
    private function defaults(): array
    {
        $row = [];
        foreach ($this->resource['fields'] as $field) {
            $row[$field['key']] = $field['default'] ?? ($field['type'] === 'bool' ? 0 : '');
        }
        return $row;
    }

    /** Data the form needs beyond the record itself. */
    private function formExtras(?int $id): array
    {
        $extras = [];
        foreach ($this->resource['fields'] as $field) {
            if ($field['type'] === 'lookup') {
                $lookup = $field['lookup'];
                $t = preg_match('/^[a-z_]+$/', $lookup['table']) ? $lookup['table'] : '';
                $l = preg_match('/^[a-z_]+$/', $lookup['label']) ? $lookup['label'] : '';
                $extras['lookup_' . $field['key']] = ($t && $l)
                    ? $this->db()->all("SELECT id, `$l` AS label FROM `$t` ORDER BY `$l`")
                    : [];
            }
            if ($field['type'] === 'services') {
                $extras['all_services']      = (new ServiceRepo())->options();
                $extras['selected_services'] = $id === null ? [] : (new IndustryRepo())->serviceIds($id);
            }
        }
        if (!empty($this->resource['repeater']) && $id !== null) {
            $cfg = $this->resource['repeater'];
            $t   = preg_match('/^[a-z_]+$/', $cfg['table']) ? $cfg['table'] : '';
            $fk  = preg_match('/^[a-z_]+$/', $cfg['foreign']) ? $cfg['foreign'] : '';
            $extras['repeater_rows'] = ($t && $fk)
                ? $this->db()->all("SELECT * FROM `$t` WHERE `$fk` = ? ORDER BY sort_order ASC, id ASC", [$id])
                : [];
        } else {
            $extras['repeater_rows'] = [];
        }
        return $extras;
    }

    private function hasColumn(string $column): bool
    {
        static $cache = [];
        $t = $this->table();
        if (!isset($cache[$t])) {
            $cache[$t] = array_map('strval', $this->db()->column(
                'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?',
                [$t]
            ));
        }
        return in_array($column, $cache[$t], true);
    }

    private function uniqueSlug(string $slug, ?int $ignoreId): string
    {
        $slug = $slug === '' ? 'item' : $slug;
        $base = $slug;
        $i    = 1;
        while (true) {
            $sql    = 'SELECT COUNT(*) FROM `' . $this->table() . '` WHERE slug = ?';
            $params = [$slug];
            if ($ignoreId !== null) {
                $sql     .= ' AND id <> ?';
                $params[] = $ignoreId;
            }
            if ($this->db()->int($sql, $params) === 0) {
                return $slug;
            }
            $slug = $base . '-' . (++$i);
        }
    }

    private function labelFor(array $row): string
    {
        foreach (['name', 'title', 'question', 'label', 'client_name'] as $key) {
            if (!empty($row[$key])) {
                return Str::excerpt((string) $row[$key], 80);
            }
        }
        return '#' . ($row['id'] ?? '');
    }
}
