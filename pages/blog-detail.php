<?php
/** @var array $post @var array $related */
$img   = media_url($post['featured_image']);
$tags  = $post['tags'] ?? [];
$share = rawurlencode(absolute_url('/blog/' . $post['slug']));
$title = rawurlencode((string) $post['title']);
?>
<div class="read-progress" aria-hidden="true"></div>

<article>
<section class="article-hero">
    <div class="page-head__bg" aria-hidden="true"><span class="glow"></span><span class="grid-pattern"></span></div>
    <div class="container container--narrow">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= e(url('/')) ?>">Home</a><?= icon('chevron-right') ?>
            <a href="<?= e(url('/blog')) ?>">Blog</a><?= icon('chevron-right') ?>
            <span aria-current="page"><?= e(str_limit($post['title'], 40)) ?></span>
        </nav>

        <?php if (!empty($post['category_name'])): ?>
        <div class="mt-5">
            <a class="badge badge--accent" href="<?= e(url('/blog?category=' . $post['category_slug'])) ?>"><?= e($post['category_name']) ?></a>
        </div>
        <?php endif; ?>

        <h1 class="mt-4"><?= e($post['title']) ?></h1>

        <?php if (!empty($post['excerpt'])): ?>
        <p class="lead mt-4"><?= e($post['excerpt']) ?></p>
        <?php endif; ?>

        <div class="row mt-6" style="gap:.85rem">
            <?php if (!empty($post['author_name'])): ?>
            <span class="avatar avatar--sm"><?= e(initials((string) $post['author_name'])) ?></span>
            <span style="font-size:var(--fs-sm);color:var(--text-soft)"><?= e($post['author_name']) ?></span>
            <?php endif; ?>
            <span class="hint"><?= e(format_date($post['published_at'], 'j F Y')) ?></span>
            <span class="hint">·</span>
            <span class="hint"><?= (int) $post['reading_minutes'] ?> min read</span>
        </div>

        <?php if ($img !== ''): ?>
        <div class="article-hero__image" data-reveal="scale">
            <img src="<?= e($img) ?>" alt="<?= e($post['title']) ?>" width="900" height="506" fetchpriority="high">
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section--flush-top">
    <div class="container container--narrow">
        <div class="article-body prose" style="max-width:none">
            <?= $post['content'] ?>
        </div>

        <?php if ($tags): ?>
        <div class="chip-row mt-8">
            <?php foreach ($tags as $tag): ?>
            <a class="pill" href="<?= e(url('/blog?tag=' . $tag['slug'])) ?>"><?= icon('tag') ?><?= e($tag['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <hr class="hairline mt-8">

        <div class="row row--between mt-5">
            <span class="hint">Found this useful? Share it.</span>
            <div class="article-share">
                <a class="social-link" target="_blank" rel="noopener noreferrer" data-no-transition
                   href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $share ?>" aria-label="Share on LinkedIn"><?= icon('linkedin') ?></a>
                <a class="social-link" target="_blank" rel="noopener noreferrer" data-no-transition
                   href="https://twitter.com/intent/tweet?url=<?= $share ?>&text=<?= $title ?>" aria-label="Share on X"><?= icon('x') ?></a>
                <a class="social-link" href="mailto:?subject=<?= $title ?>&body=<?= $share ?>" aria-label="Share by email"><?= icon('mail') ?></a>
                <button class="social-link" type="button" data-copy="<?= e(absolute_url('/blog/' . $post['slug'])) ?>" aria-label="Copy link"><?= icon('link') ?></button>
            </div>
        </div>
    </div>
</section>
</article>

<?php if ($related): ?>
<section class="section">
    <div class="container">
        <h2 class="mb-6" style="font-size:var(--fs-h3)" data-reveal>Keep reading</h2>
        <div class="grid grid-3" data-reveal-stagger>
            <?php foreach ($related as $i => $rel): $rimg = media_url($rel['featured_image']); ?>
            <article class="card card--interactive card--spotlight post-card" style="--i:<?= $i ?>" data-reveal>
                <?php if ($rimg !== ''): ?>
                <div class="post-card__media"><img src="<?= e($rimg) ?>" alt="<?= e($rel['title']) ?>" loading="lazy" width="600" height="338"></div>
                <?php endif; ?>
                <div class="post-card__body">
                    <div class="post-card__meta"><span><?= e(format_date($rel['published_at'])) ?></span></div>
                    <h3 class="post-card__title"><a href="<?= e(url('/blog/' . $rel['slug'])) ?>"><?= e($rel['title']) ?></a></h3>
                    <p class="post-card__excerpt"><?= e(str_limit($rel['excerpt'], 100)) ?></p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band') ?>
