<?php
/** @var array $projects @var \Techbiss\Core\Paginator $paginator @var array $categories
 *  @var array $industries @var string $activeCat @var string $activeInd @var string $search */
$query = array_filter(['category' => $activeCat, 'industry' => $activeInd, 'q' => $search]);
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Selected work',
    'heading' => 'Projects built the same way yours will be.',
    'lead'    => 'Websites, applications, commerce platforms and brand systems. Each one started with a business problem, not a design brief.',
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <?php if ($categories || $industries): ?>
        <form class="filter-bar" method="get" action="<?= e(url('/portfolio')) ?>" data-reveal>
            <div class="filter-bar__scroll">
                <a class="pill<?= $activeCat === '' ? ' is-active' : '' ?>"
                   href="<?= e(url('/portfolio' . query_with(['category' => null], $query))) ?>">All work</a>
                <?php foreach ($categories as $cat): ?>
                <a class="pill<?= $activeCat === $cat['slug'] ? ' is-active' : '' ?>"
                   href="<?= e(url('/portfolio' . query_with(['category' => $cat['slug']], $query))) ?>"><?= e($cat['name']) ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ($industries): ?>
            <label class="sr-only" for="filter-industry">Filter by industry</label>
            <select class="select" id="filter-industry" name="industry" data-autosubmit style="max-width:200px;min-height:38px">
                <option value="">All industries</option>
                <?php foreach ($industries as $ind): ?>
                <?php $slug = \Techbiss\Core\Str::slug((string) $ind['name']); ?>
                <option value="<?= e($slug) ?>" <?= $activeInd === $slug ? 'selected' : '' ?>><?= e($ind['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <?php if ($activeCat !== ''): ?><input type="hidden" name="category" value="<?= e($activeCat) ?>"><?php endif; ?>
            <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>

            <span class="filter-bar__count">
                <?= $paginator->total ?> project<?= $paginator->total === 1 ? '' : 's' ?>
            </span>
        </form>
        <?php endif; ?>

        <?php if (!$projects): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('image') ?></span>
                <h3><?= ($activeCat !== '' || $activeInd !== '' || $search !== '') ? 'No projects match those filters' : 'Case studies are on the way' ?></h3>
                <p>
                    <?php if ($activeCat !== '' || $activeInd !== '' || $search !== ''): ?>
                        Try a different category, or view every project.
                    <?php else: ?>
                        We are preparing our case studies. In the meantime, tell us about your project and we will walk you through comparable work directly.
                    <?php endif; ?>
                </p>
                <div class="row row--center mt-4">
                    <?php if ($activeCat !== '' || $activeInd !== '' || $search !== ''): ?>
                    <a class="btn btn--ghost" href="<?= e(url('/portfolio')) ?>">View all work</a>
                    <?php endif; ?>
                    <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Talk to us</a>
                </div>
            </div>
        <?php else: ?>
            <div class="work-grid" data-reveal-stagger>
                <?php foreach ($projects as $i => $project): ?>
                    <?= $view->partial('partials/work-card', ['project' => $project, 'i' => $i]) ?>
                <?php endforeach; ?>
            </div>
            <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/portfolio', 'query' => $query]) ?>
        <?php endif; ?>
    </div>
</section>

<?= $view->partial('partials/cta-band', [
    'eyebrow' => 'Your project',
    'heading' => 'Let us build the next one.',
    'lead'    => 'Tell us what the business does and where it is stuck. We will come back with an approach, a schedule and a price.',
]) ?>
