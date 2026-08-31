<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Auth;
use Techbiss\Core\Cache;
use Techbiss\Core\Request;
use Techbiss\Core\Str;
use Techbiss\Core\Validator;
use Techbiss\Repo\BlogRepo;
use Techbiss\Repo\TaxonomyRepo;

final class BlogController extends BaseAdminController
{
    private BlogRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new BlogRepo();
    }

    public function index(Request $request): void
    {
        $this->authorize('blog.manage');
        $this->view->render('blog/index', [
            'title'      => 'Blog posts',
            'rows'       => $this->repo->adminList(
                mb_substr($request->queryString('q'), 0, 80),
                $request->queryString('status'),
                $request->queryInt('category')
            ),
            'categories' => (new TaxonomyRepo('blog_categories'))->all(),
            'search'     => $request->queryString('q'),
            'status'     => $request->queryString('status'),
            'categoryId' => $request->queryInt('category'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('blog.manage');
        $this->renderForm([
            'status'      => 'draft',
            'author_name' => Auth::name(),
            'author_id'   => Auth::id(),
        ], true);
    }

    public function edit(Request $request, array $params): void
    {
        $this->authorize('blog.manage');
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            flash('error', 'Post not found.');
            redirect('/admin/blog');
        }
        $this->renderForm($row, false);
    }

    private function renderForm(array $row, bool $isNew): void
    {
        $id = (int) ($row['id'] ?? 0);
        $this->view->render('blog/form', [
            'title'        => $isNew ? 'New post' : 'Edit post',
            'row'          => $row,
            'isNew'        => $isNew,
            'categories'   => (new TaxonomyRepo('blog_categories'))->all(),
            'tags'         => (new TaxonomyRepo('blog_tags'))->all(),
            'selectedTags' => $id > 0 ? $this->repo->tagIds($id) : [],
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('blog.manage');
        $this->verify($request);
        $this->save($request, null);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('blog.manage');
        $this->verify($request);
        $this->save($request, (int) $params['id']);
    }

    private function save(Request $request, ?int $id): never
    {
        $v = Validator::make($request->all())
            ->required('title', 'Title', 2, 190)
            ->slug('slug', 'title')
            ->text('excerpt', 500, false, 'Excerpt')
            ->html('content', false, 'Content')
            ->int('category_id')
            ->optional('author_name', 120)
            ->in('status', ['draft', 'scheduled', 'published'], 'Status')
            ->date('published_at')
            ->optional('seo_title', 190)
            ->text('seo_description', 320, false, 'Meta description')
            ->boolean('is_featured');

        $status      = (string) $v->get('status', 'draft');
        $publishedAt = $v->get('published_at');

        if ($status === 'scheduled') {
            if ($publishedAt === null) {
                $v->addError('published_at', 'Choose the date and time this post should go live.');
            } elseif (strtotime($publishedAt) <= time()) {
                $v->addError('published_at', 'A scheduled post needs a future publish date.');
            }
        }
        if ($status === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(),
                '/admin/blog' . ($id === null ? '/create' : '/' . $id . '/edit'));
        }

        $content = (string) $v->get('content', '');
        $excerpt = (string) $v->get('excerpt', '');
        if ($excerpt === '' && $content !== '') {
            $excerpt = Str::excerpt($content, 200);
        }
        $mediaOk = static fn (string $p): string
            => ($p !== '' && preg_match('#^(https?://|uploads/)#', $p)) ? mb_substr($p, 0, 500) : '';

        $data = [
            'title'           => $v->get('title'),
            'slug'            => $this->repo->uniqueSlug((string) $v->get('slug'), $id),
            'excerpt'         => $excerpt,
            'content'         => $content,
            'category_id'     => $v->get('category_id') > 0 ? $v->get('category_id') : null,
            'author_id'       => Auth::id() ?: null,
            'author_name'     => $v->get('author_name', '') ?: Auth::name(),
            'featured_image'  => $mediaOk($request->str('featured_image')),
            'reading_minutes' => Str::readingTime($content),
            'status'          => $status,
            'published_at'    => $publishedAt,
            'seo_title'       => $v->get('seo_title', ''),
            'seo_description' => $v->get('seo_description', ''),
            'og_image'        => $mediaOk($request->str('og_image')),
            'is_featured'     => (int) $v->get('is_featured', 0),
        ];

        if ($id === null) {
            $id = $this->repo->create($data);
            ActivityLog::record('create', 'blog_posts', $id, 'Created post: ' . $data['title']);
            $message = 'Post created.';
        } else {
            $this->repo->updateRow($id, $data);
            ActivityLog::record('update', 'blog_posts', $id, 'Updated post: ' . $data['title']);
            $message = 'Post updated.';
        }

        // Tag input accepts both existing ids and free text.
        $tagRepo = new TaxonomyRepo('blog_tags');
        $tagIds  = array_map('intval', $request->arr('tags'));
        foreach ($request->arr('new_tags') as $name) {
            foreach (array_filter(array_map('trim', explode(',', $name))) as $piece) {
                $newId = $tagRepo->findOrCreate($piece);
                if ($newId > 0) {
                    $tagIds[] = $newId;
                }
            }
        }
        $this->repo->syncTags($id, $tagIds);
        Cache::flush();

        $this->ok($message, '/admin/blog/' . $id . '/edit');
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('blog.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        $this->repo->deleteRow($id);
        ActivityLog::record('delete', 'blog_posts', $id, 'Deleted post: ' . ($row['title'] ?? $id));
        $this->ok('Post deleted.', '/admin/blog');
    }

    public function toggle(Request $request, array $params): never
    {
        $this->authorize('blog.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        if ($row === null) {
            $this->back('/admin/blog');
        }
        $isLive = (string) $row['status'] === 'published';
        $this->repo->updateRow($id, [
            'status'       => $isLive ? 'draft' : 'published',
            'published_at' => $isLive ? $row['published_at'] : ($row['published_at'] ?: date('Y-m-d H:i:s')),
        ]);
        ActivityLog::record('toggle', 'blog_posts', $id, 'Status set to ' . ($isLive ? 'draft' : 'published'));
        Cache::flush();
        $this->back('/admin/blog');
    }
}
