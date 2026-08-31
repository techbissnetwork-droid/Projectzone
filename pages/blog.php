<?php
/** @var array $posts @var \Techbiss\Core\Paginator $paginator @var array $categories
 *  @var string $activeCat @var string $activeTag @var string $search */
$query = array_filter(['category' => $activeCat, 'tag' => $activeTag, 'q' => $search]);
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Blog',
    'heading' => 'Practical writing on taking a business digital.',
    'lead'    => 'Domains, hosting, websites, email, search and automation — explained for business owners, not developers.',
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <form class="filter-bar" method="get" action="<?= e(url('/blog')) ?>" data-reveal>
            <div class="filter-bar__scroll">
                <a class="pill<?= $activeCat === '' ? ' is-active' : '' ?>" href="<?= e(url('/blog')) ?>">All articles</a>
                <?php foreach ($categories as $cat): ?>
                <a class="pill<?= $activeCat === $cat['slug'] ? ' is-active' : '' ?>"
                   href="<?= e(url('/blog' . query_with(['category' => $cat['slug'], 'tag' => null], $query))) ?>">
                    <?= e($cat['name']) ?>
                    <?php if (!empty($cat['post_count'])): ?><span class="text-muted"><?= (int) $cat['post_count'] ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="input-group" style="max-width:240px">
                <?= icon('search', 'icon icon--lead') ?>
                <label class="sr-only" for="blog-search">Search articles</label>
                <input class="input" id="blog-search" type="search" name="q" value="<?= e($search) ?>"
                       placeholder="Search articles" style="min-height:38px">
            </div>

            <span class="filter-bar__count"><?= $paginator->total ?> article<?= $paginator->total === 1 ? '' : 's' ?></span>
        </form>

        <?php if (!$posts): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('file') ?></span>
                <h3><?= $query ? 'No articles match your search' : 'The first articles are being written' ?></h3>
                <p>
                    <?php if ($query): ?>Try a different search or browse every article.
                    <?php else: ?>We are working on guides covering domains, hosting, websites, email and search. Subscribe below and we will let you know.<?php endif; ?>
                </p>
                <?php if ($query): ?>
                <a class="btn btn--ghost mt-4" href="<?= e(url('/blog')) ?>">View all articles</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-3" data-reveal-stagger>
                <?php foreach ($posts as $i => $post): $img = media_url($post['featured_image']); ?>
                <article class="card card--interactive post-card" style="--i:<?= $i ?>" data-reveal>
                    <?php if ($img !== ''): ?>
                    <div class="post-card__media">
                        <img src="<?= e($img) ?>" alt="<?= e($post['title']) ?>" loading="lazy" decoding="async" width="600" height="338">
                    </div>
                    <?php endif; ?>
                    <div class="post-card__body">
                        <div class="post-card__meta">
                            <?php if (!empty($post['category_name'])): ?>
                            <span class="badge badge--accent"><?= e($post['category_name']) ?></span>
                            <?php endif; ?>
                            <span><?= e(format_date($post['published_at'])) ?></span>
                            <span class="dot" style="width:3px;height:3px;border-radius:50%;background:currentColor;opacity:.5"></span>
                            <span><?= (int) $post['reading_minutes'] ?> min read</span>
                        </div>
                        <h2 class="post-card__title"><a href="<?= e(url('/blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h2>
                        <p class="post-card__excerpt"><?= e(str_limit($post['excerpt'], 140)) ?></p>
                        <?php if (!empty($post['author_name'])): ?>
                        <div class="post-card__foot">
                            <span class="avatar avatar--sm"><?= e(initials((string) $post['author_name'])) ?></span>
                            <span class="hint"><?= e($post['author_name']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/blog', 'query' => $query]) ?>
        <?php endif; ?>
    </div>
</section>

<?= $view->partial('partials/cta-band', [
    'eyebrow' => 'Beyond reading',
    'heading' => 'Ready to act on any of this?',
    'lead'    => 'We can do this work for your business. Start with a short conversation.',
]) ?>
