<?php
/**
 * Dashboard. Every figure below is a live count from the database — nothing
 * on this screen is illustrative or placeholder data.
 * @var array $counts @var array $series
 */
use Techbiss\Core\Auth;

$maxSeries = 1;
foreach ($series as $point) {
    $maxSeries = max($maxSeries, (int) $point['messages'] + (int) $point['quotes']);
}
$hasLeadData = false;
foreach ($series as $point) {
    if ((int) $point['messages'] + (int) $point['quotes'] > 0) { $hasLeadData = true; break; }
}
?>
<div class="page-header">
    <div>
        <h1>Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', (string) $user['name'])[0]) ?></h1>
        <p>Everything below is read live from the database. Empty figures mean there is genuinely nothing there yet.</p>
    </div>
    <div class="page-header__actions">
        <?php if (Auth::can('portfolio.manage')): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/portfolio/create')) ?>"><?= icon('plus') ?>New project</a>
        <?php endif; ?>
        <?php if (Auth::can('blog.manage')): ?>
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/blog/create')) ?>"><?= icon('edit') ?>Write a post</a>
        <?php endif; ?>
    </div>
</div>

<!-- Lead tiles -->
<div class="tiles">
    <?php if (Auth::can('leads.view')): ?>
    <a class="tile" href="<?= e(url('/admin/messages')) ?>">
        <span class="tile__label"><?= icon('inbox') ?>Contact messages</span>
        <span class="tile__value"><?= (int) $counts['messages'] ?></span>
        <span class="tile__meta"><?= $counts['new_messages'] > 0 ? '<strong>' . (int) $counts['new_messages'] . ' unread</strong>' : 'All read' ?></span>
    </a>
    <a class="tile" href="<?= e(url('/admin/quotes')) ?>">
        <span class="tile__label"><?= icon('send') ?>Quote requests</span>
        <span class="tile__value"><?= (int) $counts['quotes'] ?></span>
        <span class="tile__meta"><?= $counts['new_quotes'] > 0 ? '<strong>' . (int) $counts['new_quotes'] . ' new</strong>' : 'None waiting' ?></span>
    </a>
    <?php endif; ?>

    <?php if (Auth::can('customers.manage')): ?>
    <a class="tile" href="<?= e(url('/admin/customers')) ?>">
        <span class="tile__label"><?= icon('users') ?>Customers</span>
        <span class="tile__value"><?= (int) $counts['customers'] ?></span>
        <span class="tile__meta"><?= (int) $counts['subscribers'] ?> newsletter subscribers</span>
    </a>
    <?php endif; ?>

</div>

<div class="grid-2-1">
    <div>
        <?php if (Auth::can('leads.view')): ?>
        <!-- Lead volume chart -->
        <div class="panel">
            <div class="panel__head">
                <div>
                    <span class="panel__title">Lead volume</span>
                    <div class="panel__sub">Last 12 weeks, from your own records</div>
                </div>
            </div>
            <div class="panel__body">
                <?php if (!$hasLeadData): ?>
                    <div class="empty-state" style="border:0;background:none;padding:2rem 1rem">
                        <span class="empty-state__icon"><?= icon('chart') ?></span>
                        <h3 style="font-size:1rem">No leads recorded yet</h3>
                        <p>This chart fills in as enquiries arrive through the website. It will not show anything until they do.</p>
                    </div>
                <?php else:
                    $w = 640; $h = 170; $pad = 22;
                    $barW = ($w - $pad * 2) / max(1, count($series));
                ?>
                <svg class="chart" viewBox="0 0 <?= $w ?> <?= $h + 22 ?>" role="img"
                     aria-label="Weekly contact messages and quote requests for the last 12 weeks">
                    <g class="chart__grid">
                        <?php for ($i = 0; $i <= 4; $i++): $y = $pad + ($h - $pad * 2) * ($i / 4); ?>
                        <line x1="<?= $pad ?>" y1="<?= $y ?>" x2="<?= $w - $pad ?>" y2="<?= $y ?>"></line>
                        <?php endfor; ?>
                    </g>
                    <?php foreach ($series as $i => $point):
                        $x = $pad + $i * $barW + $barW * 0.22;
                        $bw = $barW * 0.56;
                        $mh = (($h - $pad * 2) * ((int) $point['messages'] / $maxSeries));
                        $qh = (($h - $pad * 2) * ((int) $point['quotes'] / $maxSeries));
                        $baseY = $h - $pad;
                    ?>
                    <rect class="chart__bar" x="<?= round($x, 1) ?>" y="<?= round($baseY - $mh, 1) ?>"
                          width="<?= round($bw, 1) ?>" height="<?= round(max(0, $mh), 1) ?>"
                          fill="var(--accent)" rx="2">
                        <title><?= e($point['label']) ?>: <?= (int) $point['messages'] ?> messages</title>
                    </rect>
                    <rect class="chart__bar" x="<?= round($x, 1) ?>" y="<?= round($baseY - $mh - $qh, 1) ?>"
                          width="<?= round($bw, 1) ?>" height="<?= round(max(0, $qh), 1) ?>"
                          fill="var(--cyan)" rx="2">
                        <title><?= e($point['label']) ?>: <?= (int) $point['quotes'] ?> quote requests</title>
                    </rect>
                    <text class="chart__label" x="<?= round($x + $bw / 2, 1) ?>" y="<?= $h - 4 ?>" text-anchor="middle">
                        <?= e($point['label']) ?>
                    </text>
                    <?php endforeach; ?>
                </svg>
                <div class="chart-legend">
                    <span class="chart-legend__key"><span class="chart-legend__swatch" style="background:var(--accent)"></span>Contact messages</span>
                    <span class="chart-legend__key"><span class="chart-legend__swatch" style="background:var(--cyan)"></span>Quote requests</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent leads -->
        <div class="panel">
            <div class="panel__head">
                <span class="panel__title">Latest enquiries</span>
                <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/quotes')) ?>">View all<?= icon('arrow-right') ?></a>
            </div>
            <?php
            $recent = [];
            foreach ($recentQuotes as $q) {
                $recent[] = ['type' => 'quote', 'id' => (int) $q['id'], 'name' => $q['name'],
                             'detail' => ($q['business_name'] ?: $q['email']), 'ref' => $q['reference'],
                             'status' => $q['status'], 'at' => $q['created_at']];
            }
            foreach ($recentMessages as $m) {
                $recent[] = ['type' => 'message', 'id' => (int) $m['id'], 'name' => $m['name'],
                             'detail' => ($m['company'] ?: $m['email']), 'ref' => '',
                             'status' => $m['status'], 'at' => $m['created_at']];
            }
            usort($recent, static fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
            $recent = array_slice($recent, 0, 8);
            ?>
            <?php if (!$recent): ?>
                <div class="panel__body">
                    <div class="empty-state" style="border:0;background:none;padding:1.5rem 1rem">
                        <span class="empty-state__icon"><?= icon('inbox') ?></span>
                        <h3 style="font-size:1rem">No enquiries yet</h3>
                        <p>Requests from the contact page and the request page land here.</p>
                    </div>
                </div>
            <?php else: ?>
            <div class="table-wrap" style="border:0;border-radius:0;background:none">
                <table class="data-table" style="min-width:520px">
                    <thead>
                        <tr><th>From</th><th>Type</th><th>Status</th><th class="num">Received</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $item): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/admin/' . ($item['type'] === 'quote' ? 'quotes' : 'messages') . '/' . $item['id'])) ?>">
                                    <span class="cell-title"><?= e($item['name']) ?></span>
                                    <span class="cell-sub"><?= e(str_limit($item['detail'], 46)) ?></span>
                                </a>
                            </td>
                            <td>
                                <span class="badge"><?= $item['type'] === 'quote' ? 'Quote' : 'Message' ?></span>
                                <?php if ($item['ref'] !== ''): ?><br><span class="mono-sm"><?= e($item['ref']) ?></span><?php endif; ?>
                            </td>
                            <td>
                                <span class="status-dot status-dot--<?= $item['status'] === 'new' ? 'warn' : 'draft' ?>">
                                    <?= e(ucfirst((string) $item['status'])) ?>
                                </span>
                            </td>
                            <td class="num"><span class="hint"><?= e(time_ago($item['at'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>

    <!-- Secondary column: counts, demand and activity beside the main figures. -->
    <aside class="stack stack-5" aria-label="At a glance">
        <!-- Content at a glance -->
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Content</span></div>
            <div class="panel__body">
                <div class="stack stack-3">
                    <?php foreach ([
                        ['Services',     $counts['services'],     '/admin/services',     'layers',   'content.manage'],
                        ['Portfolio',    $counts['projects'],     '/admin/portfolio',    'image',    'portfolio.manage'],
                        ['Industries',   $counts['industries'],   '/admin/industries',   'building', 'content.manage'],
                                    ['Blog posts',   $counts['posts'],        '/admin/blog',         'edit',     'blog.manage'],
                        ['Testimonials', $counts['testimonials'], '/admin/testimonials', 'quote',    'content.manage'],
                        ['Media files',  $counts['media'],        '/admin/media',        'image',    'media.manage'],
                    ] as $item):
                        if (!Auth::can($item[4])) { continue; } ?>
                    <a class="row row--between" href="<?= e(url($item[2])) ?>" style="font-size:var(--fs-sm)">
                        <span class="row row--tight" style="color:var(--text-muted)">
                            <?= icon($item[3]) ?><?= e($item[0]) ?>
                        </span>
                        <strong class="tabular" style="color:var(--text)"><?= (int) $item[1] ?></strong>
                    </a>
                    <?php endforeach; ?>
                    <?php if (Auth::can('blog.manage') && (int) $counts['drafts'] > 0): ?>
                    <div class="notice" style="padding:.6rem .75rem;font-size:var(--fs-xs)">
                        <?= icon('edit') ?><span><?= (int) $counts['drafts'] ?> unpublished blog post<?= (int) $counts['drafts'] === 1 ? '' : 's' ?>.</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <?php if ($activity): ?>
        <div class="panel">
            <div class="panel__head">
                <span class="panel__title">Recent activity</span>
                <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/logs')) ?>">All<?= icon('arrow-right') ?></a>
            </div>
            <div class="panel__body">
                <div class="timeline">
                    <?php foreach ($activity as $log): ?>
                    <div class="timeline__item">
                        <span class="timeline__icon"><?= icon(match ($log['action']) {
                            'create' => 'plus', 'update' => 'edit', 'delete' => 'trash',
                            'login' => 'logout', 'upload' => 'upload', 'export' => 'download',
                            default => 'clock',
                        }) ?></span>
                        <div>
                            <div class="timeline__text"><?= e($log['description'] ?: ucfirst((string) $log['action'])) ?></div>
                            <div class="timeline__meta"><?= e($log['admin_name'] ?: 'System') ?> · <?= e(time_ago($log['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </aside>
</div>
