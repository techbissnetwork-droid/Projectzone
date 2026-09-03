<?php
/** @var App\Core\View $view @var array $tickets @var array $replies @var array $statusCounts @var string $activeStatus */
$view->extends('layouts.portal');
$view->start('content');
?>
<?php $view->partial('partials.filter-tabs', ['counts' => $statusCounts, 'active' => $activeStatus, 'base' => '/staff/tickets', 'allLabel' => 'All tickets']); ?>

<div class="stack mt-5" style="--flow:var(--s-4)">
  <?php if ($tickets === []): ?>
    <div class="empty">
      <?= icon('ticket') ?>
      <h3>Nothing in the queue</h3>
      <p>No tickets match that filter. Clients raise tickets from the client portal.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($tickets as $ticket): ?>
    <div class="panel">
      <div class="panel__head">
        <div style="min-width:0">
          <h3><?= e($ticket['subject']) ?></h3>
          <span class="tiny dim mono">
            <?= e($ticket['reference']) ?> · <?= e($ticket['company'] ?: $ticket['client_name']) ?> ·
            <?= e($ticket['category']) ?> · raised <?= e(time_ago($ticket['created_at'])) ?>
          </span>
        </div>
        <div class="cluster" style="gap:.4rem">
          <span class="badge badge--<?= $ticket['priority'] === 'high' ? 'bad' : 'neutral' ?>"><?= e($ticket['priority']) ?></span>
          <?php $view->partial('partials.status-pill', ['value' => (string) $ticket['status']]); ?>
        </div>
      </div>
      <div class="panel__body">
        <div class="card" style="padding:var(--s-4);background:var(--surface-2)">
          <div class="cluster" style="gap:.6rem">
            <span class="avatar avatar--sm"><?= e(initials((string) $ticket['client_name'])) ?></span>
            <div>
              <strong class="small"><?= e($ticket['client_name']) ?></strong>
              <span class="tiny dim" style="display:block"><?= e(human_date($ticket['created_at'], 'M j, Y')) ?></span>
            </div>
          </div>
          <p class="small mt-4" style="line-height:1.65"><?= e($ticket['body']) ?></p>
        </div>

        <?php foreach ($replies[(int) $ticket['id']] ?? [] as $reply): ?>
          <div class="card mt-3" style="padding:var(--s-4);margin-left:clamp(0px,4vw,2rem);border-color:var(--accent-line)">
            <div class="cluster" style="gap:.6rem">
              <span class="avatar avatar--sm"><?= e(initials((string) $reply['author_name'])) ?></span>
              <div>
                <strong class="small"><?= e($reply['author_name']) ?> <span class="badge badge--neutral">TECHBISS</span></strong>
                <span class="tiny dim" style="display:block"><?= e(time_ago($reply['created_at'])) ?></span>
              </div>
            </div>
            <p class="small mt-4" style="line-height:1.65"><?= e($reply['body']) ?></p>
          </div>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('/staff/tickets/reply')) ?>" class="mt-5">
          <?= csrf_field() ?>
          <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
          <div class="field">
            <label class="field__label" for="reply-<?= (int) $ticket['id'] ?>">Reply</label>
            <textarea class="textarea" id="reply-<?= (int) $ticket['id'] ?>" name="body" required
                      style="min-height:96px" placeholder="Answer the question, and say what happens next."></textarea>
          </div>
          <div class="cluster">
            <label class="sr-only" for="rstatus-<?= (int) $ticket['id'] ?>">Set status</label>
            <select class="select" id="rstatus-<?= (int) $ticket['id'] ?>" name="status" style="max-width:180px">
              <option value="answered" selected>Mark answered</option>
              <option value="resolved">Mark resolved</option>
              <option value="open">Keep open</option>
            </select>
            <button class="btn btn--primary" type="submit">Post reply</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php $view->stop(); ?>
