<?php
/** @var App\Core\View $view @var array $tickets @var array $replies */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="split split--wide-left" style="gap:var(--s-4);align-items:start">
  <div class="stack" style="--flow:var(--s-4)">
    <?php if ($tickets === []): ?>
      <div class="empty">
        <?= icon('ticket') ?>
        <h3>No tickets open</h3>
        <p>Raise one on the right. Support replies within one business day; high priority within four business hours.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($tickets as $ticket): ?>
      <div class="panel">
        <div class="panel__head">
          <div style="min-width:0">
            <h3><?= e($ticket['subject']) ?></h3>
            <span class="tiny dim mono"><?= e($ticket['reference']) ?> · <?= e($ticket['category']) ?> · <?= e(time_ago($ticket['created_at'])) ?></span>
          </div>
          <div class="cluster" style="gap:.4rem">
            <span class="badge badge--<?= $ticket['priority'] === 'high' ? 'bad' : 'neutral' ?>"><?= e($ticket['priority']) ?></span>
            <?php $view->partial('partials.status-pill', ['value' => (string) $ticket['status']]); ?>
          </div>
        </div>
        <div class="panel__body">
          <p class="small" style="line-height:1.65"><?= e($ticket['body']) ?></p>
          <?php foreach ($replies[(int) $ticket['id']] ?? [] as $reply): ?>
            <div class="card mt-4" style="padding:var(--s-4);border-color:var(--accent-line);background:linear-gradient(160deg,var(--accent-soft),transparent 70%),var(--surface)">
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
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="panel__head"><h3>Raise a ticket</h3></div>
    <div class="panel__body">
      <form method="post" action="<?= e(url('/client/support')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label class="field__label" for="subject">Subject <span class="req">*</span></label>
          <input class="input" type="text" id="subject" name="subject" required value="<?= e(old('subject')) ?>"
                 placeholder="Installer stops at the database step">
          <?php if ($error = error_for('subject')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        </div>
        <div class="field-row">
          <div class="field">
            <label class="field__label" for="category">Category</label>
            <select class="select" id="category" name="category" required>
              <?php foreach (['general' => 'General', 'installation' => 'Installation', 'licensing' => 'Licensing',
                              'migration' => 'Migration', 'performance' => 'Performance', 'billing' => 'Billing'] as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= old('category') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="field__label" for="priority">Priority</label>
            <select class="select" id="priority" name="priority" required>
              <option value="low" <?= old('priority') === 'low' ? 'selected' : '' ?>>Low</option>
              <option value="normal" <?= old('priority', 'normal') === 'normal' ? 'selected' : '' ?>>Normal</option>
              <option value="high" <?= old('priority') === 'high' ? 'selected' : '' ?>>High — blocking</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label class="field__label" for="body">
            What is happening? <span class="req">*</span>
            <span class="field__hint">Include the URL and any error text</span>
          </label>
          <textarea class="textarea" id="body" name="body" required><?= e(old('body')) ?></textarea>
          <?php if ($error = error_for('body')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Raise ticket</button>
        <p class="tiny dim center mt-4">Normal priority: one business day. High priority: four business hours.</p>
      </form>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
