<?php
/** @var App\Core\View $view @var array $site */
$view->extends('layouts.app');
$view->start('content');
?>
<section class="hero">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Company' => '/about']]); ?>
      <div class="split split--wide-left" style="align-items:center">
        <div>
          <span class="eyebrow" data-reveal>About TECHBISS</span>
          <h1 class="h1 hero__title" data-reveal="60">
            Founded on one conviction: transformation fails when nobody owns the outcome.
          </h1>
          <p class="lede hero__lede" data-reveal="120">
            Three engineers started TECHBISS in <?= e((string) $site['brand']['founded']) ?> after watching
            well-funded programmes fail for organisational reasons rather than technical
            ones. Thirteen years later we run four regions on one delivery standard.
          </p>
        </div>
        <div data-reveal="140">
          <div class="stats" style="grid-template-columns:repeat(2,minmax(0,1fr))">
            <?php foreach ($site['stats'] as $stat): ?>
              <div class="stat">
                <span class="stat__value"><span data-count="<?= e($stat['value']) ?>"><?= e($stat['value']) ?></span><em><?= e($stat['suffix']) ?></em></span>
                <span class="stat__label"><?= e($stat['label']) ?></span>
                <span class="stat__detail"><?= e($stat['detail']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section" id="principles" style="scroll-margin-top:calc(var(--header-h) + 2rem)">
  <div class="container container--wide">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'Principles',
      'title' => 'Six commitments we are willing to be measured against.',
      'body' => 'These are not values on a wall. Each one has a corresponding practice in how we scope, deliver and report.',
    ]); ?>
    <div class="cols-3">
      <?php foreach ($site['principles'] as $i => $principle): ?>
        <article class="card card--lift spotlight" data-reveal="<?= $i * 50 ?>">
          <span class="mono dim">0<?= $i + 1 ?></span>
          <h3 class="h4 mt-3"><?= e($principle['title']) ?></h3>
          <p class="small muted mt-3"><?= e($principle['body']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container container--wide">
    <div class="split split--wide-right">
      <div data-reveal>
        <span class="eyebrow">Our history</span>
        <h2 class="h2 mt-3">Thirteen years, four regions, one standard.</h2>
        <p class="lede mt-4">
          We grew deliberately slowly. Each region opened only once we could staff
          it with people who had already delivered to our standard somewhere else.
        </p>
      </div>
      <div class="timeline" data-reveal="80">
        <?php foreach ($site['timeline'] as $entry): ?>
          <div class="timeline__item">
            <div class="timeline__rail">
              <span class="timeline__dot"></span>
              <span class="timeline__line"></span>
            </div>
            <div>
              <span class="timeline__year"><?= e($entry['year']) ?></span>
              <h3><?= e($entry['title']) ?></h3>
              <p><?= e($entry['body']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section" id="leadership" style="scroll-margin-top:calc(var(--header-h) + 2rem)">
  <div class="container container--wide">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'Leadership',
      'title' => 'The people accountable for your engagement.',
      'body' => 'Our executives carry delivery responsibility, not just commercial responsibility. Each one owns accounts personally.',
    ]); ?>
    <div class="cols-3">
      <?php foreach ($site['leadership'] as $i => $person): ?>
        <article class="card card--lift a-<?= e($person['accent']) ?>" data-reveal="<?= $i * 50 ?>">
          <div class="cluster" style="gap:.85rem">
            <span class="avatar avatar--lg"><?= e(initials($person['name'])) ?></span>
            <div>
              <strong style="font-size:var(--t-1);display:block"><?= e($person['name']) ?></strong>
              <span class="small dim"><?= e($person['role']) ?></span>
            </div>
          </div>
          <p class="small muted mt-4"><?= e($person['bio']) ?></p>
          <div class="feature__foot">
            <span><?= icon('pin', ['size' => 13]) ?> <?= e($person['location']) ?></span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" style="background:var(--bg-elev);border-block:1px solid var(--line)">
  <div class="container container--wide">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'Offices',
      'title' => 'Four regions, follow-the-sun delivery.',
      'body' => 'Every region runs the same quality gates, the same review process and the same reporting. Where your pod sits is a logistics question, not a quality one.',
    ]); ?>
    <div class="cols-4">
      <?php foreach ($site['offices'] as $i => $office): ?>
        <article class="card" data-reveal="<?= $i * 50 ?>">
          <div class="between">
            <h3 class="h4"><?= e($office['city']) ?></h3>
            <span class="badge badge--neutral"><?= e($office['timezone']) ?></span>
          </div>
          <p class="small" style="color:var(--accent-2);margin-top:.35rem"><?= e($office['role']) ?></p>
          <p class="small dim mt-4" style="line-height:1.6"><?= e($office['address']) ?></p>
          <a class="small mt-4" style="display:inline-block;color:var(--ink-2)" href="tel:<?= e(preg_replace('/[^\d+]/', '', $office['phone'])) ?>">
            <?= icon('phone', ['size' => 13]) ?> <?= e($office['phone']) ?>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section" id="careers" style="scroll-margin-top:calc(var(--header-h) + 2rem)">
  <div class="container container--wide">
    <?php $view->partial('partials.section-head', [
      'eyebrow' => 'Careers',
      'title' => 'We hire people who want to own the result.',
      'body' => 'Senior-weighted teams, real ownership, and no expectation that you will be moved off an account you care about.',
      'action' => ['label' => 'Email our talent team', 'path' => '/contact?topic=careers'],
    ]); ?>
    <div class="panel" data-reveal>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Role</th><th>Team</th><th>Location</th><th>Type</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($site['careers'] as $role): ?>
              <tr>
                <td data-label="Role"><strong><?= e($role['role']) ?></strong></td>
                <td data-label="Team"><?= e($role['team']) ?></td>
                <td data-label="Location"><?= e($role['location']) ?></td>
                <td data-label="Type"><span class="badge badge--neutral"><?= e($role['type']) ?></span></td>
                <td data-label="" class="num">
                  <a class="btn btn--sm btn--ghost" href="<?= e(url('/contact?topic=careers&role=' . rawurlencode($role['role']))) ?>">Apply</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Work with us, or for us.',
  'body' => 'Whether you have a platform that needs rebuilding or a career that needs a harder problem, the conversation starts the same way.',
  'primary' => ['label' => 'Start a project', 'path' => '/contact'],
  'secondary' => ['label' => 'See open roles', 'path' => '/about#careers'],
]); ?>
<?php $view->stop(); ?>

<?php $view->start('after_body'); ?>
<div class="actionbar" data-actionbar>
  <a class="btn btn--ghost" href="<?= e(url('/about#careers')) ?>">Careers</a>
  <a class="btn btn--primary" href="<?= e(url('/contact')) ?>">Contact us</a>
</div>
<?php $view->stop(); ?>
