<?php
/**
 * @var array $customer @var array $projects @var array $requests
 */
$projectStatusBadge = [
    'building'   => 'info',
    'delivered'  => 'success',
    'maintained' => 'accent',
    'ended'      => '',
];
$requestStatusBadge = [
    'new'         => 'warning',
    'in_progress' => 'info',
    'resolved'    => 'success',
];
$requestTypes = [
    'upgrade'     => 'Upgrade',
    'update'      => 'Update',
    'maintenance' => 'Maintenance',
    'support'     => 'Support',
    'other'       => 'Something else',
];
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Client area',
    'heading' => 'Welcome back, ' . $customer['name'] . '.',
    'lead'    => 'Your projects, and anywhere you want us to pick something back up.',
]) ?>

<section class="section section--flush-top">
    <div class="container container--narrow">
        <div class="row row--between mb-6">
            <p class="hint mb-0"><?= icon('mail') ?><?= e($customer['email']) ?></p>
            <form method="post" action="<?= e(url('/portal/logout')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--sm" type="submit"><?= icon('logout') ?>Sign out</button>
            </form>
        </div>

        <h2 class="eyebrow mb-3">Your projects</h2>
        <?php if (!$projects): ?>
        <div class="card card--pad-lg mb-8">
            <p class="hint mb-0">Nothing on file yet. Once a project is set up for you, it will show here.</p>
        </div>
        <?php else: ?>
        <div class="stack stack-3 mb-8">
            <?php foreach ($projects as $project): ?>
            <div class="card card--pad-lg">
                <div class="row row--between" style="align-items:flex-start">
                    <div>
                        <h3 class="mb-1"><?= e($project['name']) ?></h3>
                        <?php if (!empty($project['summary'])): ?>
                        <p class="hint mb-0"><?= e($project['summary']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="badge<?= ($projectStatusBadge[$project['status']] ?? '') !== '' ? ' badge--' . $projectStatusBadge[$project['status']] : '' ?>">
                        <?= e(ucfirst(str_replace('_', ' ', (string) $project['status']))) ?>
                    </span>
                </div>
                <div class="row mt-4" style="gap:1.5rem;flex-wrap:wrap">
                    <?php if (!empty($project['live_url'])): ?>
                    <a class="hint" href="<?= e($project['live_url']) ?>" target="_blank" rel="noopener noreferrer">
                        <?= icon('external') ?><?= e($project['live_url']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($project['domain_renews_on'])): ?>
                    <span class="hint"><?= icon('calendar') ?>Domain renews <?= e(format_date($project['domain_renews_on'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($project['hosting_renews_on'])): ?>
                    <span class="hint"><?= icon('calendar') ?>Hosting renews <?= e(format_date($project['hosting_renews_on'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($project['maintenance_due'])): ?>
                    <span class="hint"><?= icon('calendar') ?>Maintenance due <?= e(format_date($project['maintenance_due'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h2 class="eyebrow mb-3">Ask for upgrade, update, maintenance or support</h2>
        <form class="card card--pad-lg mb-8" method="post" action="<?= e(url('/portal/requests')) ?>" data-reveal>
            <?= csrf_field() ?>

            <div class="field--row">
                <div class="field">
                    <label class="label" for="preq-type">What do you need?</label>
                    <select class="select" id="preq-type" name="request_type">
                        <?php foreach ($requestTypes as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= old('request_type') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (count($projects) > 1): ?>
                <div class="field">
                    <label class="label" for="preq-project">Which project?</label>
                    <select class="select" id="preq-project" name="client_project_id">
                        <option value="">Not sure / general</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?= (int) $project['id'] ?>" <?= old('client_project_id') === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php elseif ($projects): ?>
                <input type="hidden" name="client_project_id" value="<?= (int) $projects[0]['id'] ?>">
                <?php endif; ?>
            </div>

            <div class="field mt-2">
                <label class="label" for="preq-message">Tell us what you need <span class="req" aria-hidden="true">*</span></label>
                <textarea class="textarea<?= error_for('message') ? ' is-invalid' : '' ?>" id="preq-message" name="message"
                          rows="4" required <?= $view->partial('partials/field-invalid', ['key' => 'message']) ?>><?= e(old('message')) ?></textarea>
                <?= $view->partial('partials/field-error', ['key' => 'message']) ?>
            </div>

            <div class="form-actions mt-4">
                <button class="btn btn--primary btn--lg" type="submit"><?= icon('send') ?>Send request</button>
            </div>
        </form>

        <?php if ($requests): ?>
        <h2 class="eyebrow mb-3">Your requests</h2>
        <div class="stack stack-2">
            <?php foreach ($requests as $row): ?>
            <div class="card">
                <div class="row row--between">
                    <div>
                        <span class="cell-title"><?= e($row['reference']) ?></span>
                        <p class="hint mb-0 mt-1"><?= e($requestTypes[$row['request_type']] ?? ucfirst((string) $row['request_type'])) ?><?= !empty($row['project_name']) ? ' · ' . e($row['project_name']) : '' ?> · <?= e(time_ago($row['created_at'])) ?></p>
                    </div>
                    <span class="badge<?= ($requestStatusBadge[$row['status']] ?? '') !== '' ? ' badge--' . $requestStatusBadge[$row['status']] : '' ?>">
                        <?= e(ucfirst(str_replace('_', ' ', (string) $row['status']))) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
