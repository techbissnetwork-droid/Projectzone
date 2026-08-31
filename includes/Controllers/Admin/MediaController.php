<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\App;
use Techbiss\Core\Auth;
use Techbiss\Core\Paginator;
use Techbiss\Core\Request;
use Techbiss\Repo\MediaRepo;

final class MediaController extends BaseAdminController
{
    private MediaRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new MediaRepo();
    }

    public function index(Request $request): void
    {
        $this->authorize('media.manage');
        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 50);
        $result  = $this->repo->paginate(
            $page,
            $perPage,
            mb_substr($request->queryString('q'), 0, 80),
            $request->queryString('folder'),
            $request->queryString('type')
        );

        $this->view->render('media/index', [
            'title'      => 'Media library',
            'rows'       => $result['items'],
            'paginator'  => new Paginator($page, $perPage, $result['total']),
            'folders'    => $this->repo->folders(),
            'search'     => $request->queryString('q'),
            'folder'     => $request->queryString('folder'),
            'type'       => $request->queryString('type'),
            'totalBytes' => $this->repo->totalBytes(),
            'totalFiles' => $this->repo->count(),
            'maxBytes'   => (int) App::config('uploads.max_bytes', 0),
            'allowed'    => array_values((array) App::config('uploads.allowed_mime', [])),
        ]);
    }

    /** Upload handler — used by the library page and by every media picker. */
    public function upload(Request $request): never
    {
        $this->authorize('media.manage');
        $this->verify($request);

        $files = $request->fileList('files');
        if ($files === []) {
            $single = $request->file('file');
            if ($single !== null) {
                $files = [$single];
            }
        }
        if ($files === []) {
            if ($request->wantsJson()) {
                json_response(['ok' => false, 'message' => 'No file was selected.'], 422);
            }
            flash('error', 'No file was selected.');
            $this->back('/admin/media');
        }

        $folder   = $request->str('folder', 'general');
        $uploader = App::uploader();
        $saved    = [];
        $errors   = [];

        foreach (array_slice($files, 0, 20) as $file) {
            $result = $uploader->store($file, $folder);
            if (!$result['ok']) {
                $errors[] = ($file['name'] ?? 'File') . ': ' . $result['error'];
                continue;
            }
            $data               = $result['data'];
            $data['alt_text']   = mb_substr($request->str('alt_text'), 0, 255);
            $data['uploaded_by'] = Auth::id() ?: null;
            $id = $this->repo->create($data);
            $saved[] = ['id' => $id, 'path' => $data['path'], 'url' => url($data['path']),
                        'thumb' => $data['thumb_path'] !== '' ? url($data['thumb_path']) : url($data['path']),
                        'name' => $data['original_name']];
            ActivityLog::record('upload', 'media', $id, 'Uploaded ' . $data['original_name']);
        }

        if ($request->wantsJson()) {
            json_response([
                'ok'      => $saved !== [],
                'files'   => $saved,
                'message' => $saved === []
                    ? implode(' ', $errors)
                    : count($saved) . ' file' . (count($saved) === 1 ? '' : 's') . ' uploaded.'
                        . ($errors ? ' ' . implode(' ', $errors) : ''),
            ], $saved === [] ? 422 : 200);
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
        }
        if ($saved) {
            flash('success', count($saved) . ' file' . (count($saved) === 1 ? '' : 's') . ' uploaded.');
        }
        $this->back('/admin/media');
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('media.manage');
        $this->verify($request);
        $id = (int) $params['id'];

        $folder = $request->str('folder', 'general');
        $this->repo->update($id, [
            'alt_text' => mb_substr($request->str('alt_text'), 0, 255),
            'folder'   => preg_match('/^[a-z0-9_-]{1,40}$/', $folder) ? $folder : 'general',
        ]);
        ActivityLog::record('update', 'media', $id, 'Updated media metadata');

        if ($request->wantsJson()) {
            json_response(['ok' => true, 'message' => 'Media updated.']);
        }
        $this->ok('Media updated.', '/admin/media');
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('media.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        $ok  = $this->repo->delete($id);
        if ($ok) {
            ActivityLog::record('delete', 'media', $id, 'Deleted ' . ($row['original_name'] ?? $id));
        }

        if ($request->wantsJson()) {
            json_response(['ok' => $ok, 'message' => $ok ? 'File deleted.' : 'File not found.']);
        }
        $this->ok($ok ? 'File deleted.' : 'File not found.', '/admin/media');
    }

    /** JSON feed backing the media picker modal. */
    public function browse(Request $request): never
    {
        $this->authorize('media.manage');
        $result = $this->repo->paginate(
            max(1, $request->queryInt('page', 1)),
            36,
            mb_substr($request->queryString('q'), 0, 80),
            $request->queryString('folder'),
            'image'
        );
        $items = array_map(static fn (array $m): array => [
            'id'    => (int) $m['id'],
            'path'  => $m['path'],
            'url'   => url((string) $m['path']),
            'thumb' => url((string) ($m['thumb_path'] !== '' ? $m['thumb_path'] : $m['path'])),
            'name'  => $m['original_name'],
            'alt'   => $m['alt_text'],
        ], $result['items']);

        json_response(['ok' => true, 'items' => $items, 'total' => $result['total'], 'folders' => $this->repo->folders()]);
    }
}
