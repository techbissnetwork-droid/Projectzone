<?php
/** @var array $row @var bool $isNew @var array $categories @var array $tags @var array $selectedTags */
$id     = (int) ($row['id'] ?? 0);
$action = $isNew ? url('/admin/blog') : url('/admin/blog/' . $id);
$val    = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$selTags = array_map('intval', (array) old('tags', $selectedTags));
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p>Reading time is calculated from the content. The excerpt falls back to the opening of the article if left blank.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/blog')) ?>"><?= icon('arrow-left') ?>Back</a>
        <?php if (!$isNew && ($row['status'] ?? '') === 'published'): ?>
        <a class="btn btn--quiet btn--sm" target="_blank" rel="noopener" href="<?= e(url('/blog/' . $row['slug'])) ?>"><?= icon('external') ?>View</a>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" data-dirty-guard>
    <?= csrf_field() ?>
    <div class="form-cols">
        <div>
            <div class="panel">
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="b-title">Title <span class="req">*</span></label>
                            <input class="input<?= error_for('title') ? ' is-invalid' : '' ?>" id="b-title" type="text"
                                   name="title" value="<?= e($val('title')) ?>" required maxlength="190" data-counter>
                            <?php if (error_for('title')): ?><span class="field-error"><?= e(error_for('title')) ?></span><?php endif; ?>
                        </div>

                        <div class="field">
                            <label class="label" for="b-slug">URL slug</label>
                            <div class="input-group">
                                <input class="input" id="b-slug" type="text" name="slug" value="<?= e($val('slug')) ?>"
                                       maxlength="190" data-slug-from="title" spellcheck="false">
                                <button class="btn btn--quiet btn--sm" type="button" data-slug-regenerate><?= icon('refresh') ?></button>
                            </div>
                            <span class="hint">/blog/<?= e($val('slug') ?: 'article-slug') ?></span>
                        </div>

                        <div class="field">
                            <label class="label" for="b-excerpt">Excerpt</label>
                            <textarea class="textarea" id="b-excerpt" name="excerpt" maxlength="500" data-counter><?= e($val('excerpt')) ?></textarea>
                            <span class="hint">Shown on the blog index and used as the meta description fallback.</span>
                        </div>

                        <div class="field">
                            <label class="label" for="b-content">Content</label>
                            <textarea class="textarea" id="b-content" name="content" maxlength="120000"
                                      style="min-height:420px;font-family:var(--font-mono);font-size:.82rem"><?= e($val('content')) ?></textarea>
                            <span class="hint">
                                Accepts HTML: &lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;blockquote&gt;, &lt;a&gt;,
                                &lt;img&gt;, &lt;table&gt;, &lt;code&gt; and &lt;pre&gt;. Everything else is stripped on save.
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel__head"><span class="panel__title">Search &amp; social</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="b-seo-title">SEO title</label>
                            <input class="input" id="b-seo-title" type="text" name="seo_title" value="<?= e($val('seo_title')) ?>" maxlength="190" data-counter>
                        </div>
                        <div class="field">
                            <label class="label" for="b-seo-desc">Meta description</label>
                            <textarea class="textarea" id="b-seo-desc" name="seo_description" maxlength="320" data-counter><?= e($val('seo_description')) ?></textarea>
                        </div>
                        <div class="field">
                            <span class="label">Social share image</span>
                            <div class="media-field" data-media-field>
                                <div class="media-field__preview">
                                    <?php $og = media_url($val('og_image')); ?>
                                    <?php if ($og !== ''): ?><img src="<?= e($og) ?>" alt=""><?php else: ?><?= icon('image') ?><?php endif; ?>
                                </div>
                                <div class="media-field__body">
                                    <span class="media-field__path"><?= $val('og_image') !== '' ? e($val('og_image')) : 'No image selected' ?></span>
                                    <input type="hidden" name="og_image" value="<?= e($val('og_image')) ?>">
                                    <div class="row row--tight">
                                        <button class="btn btn--ghost btn--sm" type="button" data-media-choose>Choose</button>
                                        <button class="btn btn--quiet btn--sm" type="button" data-media-clear>Clear</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <aside class="form-aside">
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Publishing</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="b-status">Status</label>
                            <select class="select" id="b-status" name="status">
                                <?php foreach (['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published'] as $v => $l): ?>
                                <option value="<?= e($v) ?>" <?= $val('status', 'draft') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="b-date">Publish date &amp; time</label>
                            <input class="input<?= error_for('published_at') ? ' is-invalid' : '' ?>" id="b-date" type="datetime-local"
                                   name="published_at" value="<?= e(str_replace(' ', 'T', substr((string) $val('published_at'), 0, 16))) ?>">
                            <span class="hint">Required when scheduling. Left blank on publish, it uses the current time.</span>
                            <?php if (error_for('published_at')): ?><span class="field-error"><?= e(error_for('published_at')) ?></span><?php endif; ?>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="is_featured" value="1" <?= (int) $val('is_featured', 0) === 1 ? 'checked' : '' ?>>
                            <span class="switch__track" aria-hidden="true"></span><span>Feature on the homepage</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel__head"><span class="panel__title">Details</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="b-cat">Category</label>
                            <select class="select" id="b-cat" name="category_id">
                                <option value="">Uncategorised</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>" <?= (int) $val('category_id') === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="b-author">Author name</label>
                            <input class="input" id="b-author" type="text" name="author_name" value="<?= e($val('author_name')) ?>" maxlength="120">
                        </div>
                        <div class="field">
                            <span class="label">Featured image</span>
                            <div class="media-field" data-media-field>
                                <div class="media-field__preview">
                                    <?php $fi = media_url($val('featured_image')); ?>
                                    <?php if ($fi !== ''): ?><img src="<?= e($fi) ?>" alt=""><?php else: ?><?= icon('image') ?><?php endif; ?>
                                </div>
                                <div class="media-field__body">
                                    <span class="media-field__path"><?= $val('featured_image') !== '' ? e($val('featured_image')) : 'No image selected' ?></span>
                                    <input type="hidden" name="featured_image" value="<?= e($val('featured_image')) ?>">
                                    <div class="row row--tight">
                                        <button class="btn btn--ghost btn--sm" type="button" data-media-choose>Choose</button>
                                        <button class="btn btn--quiet btn--sm" type="button" data-media-clear>Clear</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel__head">
                    <span class="panel__title">Tags</span>
                    <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/blog_tags')) ?>">Manage</a>
                </div>
                <div class="panel__body">
                    <?php if ($tags): ?>
                    <div class="stack stack-2" style="max-height:220px;overflow-y:auto">
                        <?php foreach ($tags as $tag): ?>
                        <label class="check">
                            <input type="checkbox" name="tags[]" value="<?= (int) $tag['id'] ?>"
                                   <?= in_array((int) $tag['id'], $selTags, true) ? 'checked' : '' ?>>
                            <span class="check__box" aria-hidden="true"></span>
                            <span><?= e($tag['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="field mt-4">
                        <label class="label" for="b-newtags">New tags</label>
                        <input class="input" id="b-newtags" type="text" name="new_tags[]" placeholder="Comma separated" maxlength="255">
                        <span class="hint">Created automatically if they do not exist yet.</span>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <div class="sticky-actions">
        <button class="btn btn--primary" type="submit"><?= icon('check') ?><?= $isNew ? 'Create post' : 'Save changes' ?></button>
        <a class="btn btn--quiet" href="<?= e(url('/admin/blog')) ?>">Cancel</a>
    </div>
</form>
