<?php
/** @var string $value */
$map = [
    'paid' => 'ok', 'active' => 'ok', 'live' => 'ok', 'won' => 'ok', 'published' => 'ok',
    'complete' => 'ok', 'resolved' => 'ok', 'done' => 'ok', 'green' => 'ok', 'subscribed' => 'ok',
    'pending' => 'warn', 'running' => 'warn', 'draft' => 'warn', 'answered' => 'warn',
    'amber' => 'warn', 'proposal' => 'warn', 'due' => 'warn', 'qualified' => 'warn',
    'failed' => 'bad', 'lost' => 'bad', 'suspended' => 'bad', 'red' => 'bad',
    'retired' => 'bad', 'overdue' => 'bad', 'refunded' => 'bad',
    'new' => 'violet', 'open' => 'violet',
];
$tone = $map[strtolower($value)] ?? 'neutral';
?>
<span class="badge badge--<?= e($tone) ?>"><?= e(ucfirst(str_replace('_', ' ', $value))) ?></span>
