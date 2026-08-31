<?php
/** @var array $projects @var \Techbiss\Core\Paginator $paginator @var array $categories
 *  @var array $industries @var string $activeCat @var string $activeInd
 *  @var string $activeSort @var string $search */
$query    = array_filter(['category' => $activeCat, 'industry' => $activeInd, 'q' => $search, 'sort' => $activeSort !== 'featured' ? $activeSort : null]);
$filtered = $activeCat !== '' || $activeInd !== '' || $search !== '';
$sorts    = ['featured' => 'Featured first', 'newest' => 'Newest', 'name' => 'A – Z'];
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Ready to launch',
    'heading' => 'Ready projects.',
    'lead'    => 'Finished builds. See one working, then we set it up on your domain. Ask about any of them for a price.',
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <h2 class="sr-only">Available projects</h2>
        <?php if ($categories || $industries): ?>
        <form class="filter-bar" method="get" action="<?= e(url('/premade-projects')) ?>" data-reveal>
            <div class="filter-bar__scroll">
                <a class="pill<?= $activeCat === '' ? ' is-active' : '' ?>"
                   href="<?= e(url('/premade-projects' . query_with(['category' => null], $query))) ?>">All</a>
                <?php foreach ($categories as $cat): ?>
                <a class="pill<?= $activeCat === $cat['slug'] ? ' is-active' : '' ?>"
                   href="<?= e(url('/premade-projects' . query_with(['category' => $cat['slug']], $query))) ?>"><?= e($cat['name']) ?></a>
                <?php endforeach; ?>
            </div>

            <label class="sr-only" for="filter-sort">Sort</label>
            <select class="select" id="filter-sort" name="sort" data-autosubmit style="max-width:170px;min-height:38px">
                <?php foreach ($sorts as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $activeSort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($activeCat !== ''): ?><input type="hidden" name="category" value="<?= e($activeCat) ?>"><?php endif; ?>
            <?php if ($activeInd !== ''): ?><input type="hidden" name="industry" value="<?= e($activeInd) ?>"><?php endif; ?>
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>

            <span class="filter-bar__count">
                <?= $paginator->total ?> project<?= $paginator->total === 1 ? '' : 's' ?>
            </span>
        </form>
        <?php endif; ?>

        <?php if (!$projects): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('rocket') ?></span>
                <h3><?= $filtered ? 'Nothing matches those filters' : 'Nothing listed yet' ?></h3>
                <p>
                    <?php if ($filtered): ?>
                        Try another category, or see everything.
                    <?php else: ?>
                        We are getting the first builds ready. Tell us what you need and we will show you what fits.
                    <?php endif; ?>
                </p>
                <div class="row row--center mt-4">
                    <?php if ($filtered): ?>
                    <a class="btn btn--ghost" href="<?= e(url('/premade-projects')) ?>">See all</a>
                    <?php endif; ?>
                    <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Talk to us</a>
                </div>
            </div>
        <?php else: ?>
            <div class="work-grid" data-reveal-stagger>
                <?php foreach ($projects as $i => $project): ?>
                    <?= $view->partial('partials/project-card', ['project' => $project, 'i' => $i]) ?>
                <?php endforeach; ?>
            </div>
            <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/premade-projects', 'query' => $query]) ?>
        <?php endif; ?>
    </div>
</section>

<?= $view->partial('partials/cta-band', [
    'eyebrow' => 'Not on the list?',
    'heading' => 'We build from scratch too.',
    'lead'    => 'Tell us what your business does. We will say what fits and what it costs.',
]) ?>
