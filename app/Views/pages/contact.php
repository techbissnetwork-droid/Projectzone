<?php
/** @var App\Core\View $view @var array $site @var string $topic @var array $prefill @var array $flash */
$view->extends('layouts.app');
$view->start('content');
$topics = $site['contact_topics'];
$budgets = $site['budget_bands'];
$prefillMessage = '';
if ($prefill['service'] !== '') { $prefillMessage = 'We are interested in your ' . str_replace('-', ' ', $prefill['service']) . " practice.\n\n"; }
elseif ($prefill['industry'] !== '') { $prefillMessage = 'We work in ' . str_replace('-', ' ', $prefill['industry']) . ".\n\n"; }
elseif ($prefill['role'] !== '') { $prefillMessage = 'I would like to apply for the ' . $prefill['role'] . " role.\n\n"; }
elseif ($prefill['product'] !== '') { $prefillMessage = 'I have a question about ' . $prefill['product'] . ".\n\n"; }
?>
<section class="hero">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Home' => '/', 'Contact' => '/contact']]); ?>
      <div style="max-width:52ch">
        <span class="eyebrow" data-reveal>Contact</span>
        <h1 class="h1 hero__title" data-reveal="60">Tell us what needs to change.</h1>
        <p class="lede hero__lede" data-reveal="120">
          Every qualified enquiry gets a reply from an architect within one
          business day — not a sequence from a marketing platform.
        </p>
      </div>
    </div>
  </div>
</section>

<span data-actionbar-after aria-hidden="true"></span>

<section class="section section--flush-top" id="contact-form" style="scroll-margin-top:calc(var(--header-h) + 1.5rem)">
  <div class="container container--wide">
    <div class="split split--wide-left" style="align-items:start">
      <div class="card" data-reveal style="padding:clamp(1.25rem,1rem + 1.4vw,2.25rem)">
        <h2 class="h3">Start a conversation</h2>
        <p class="small muted mt-3">Fields marked with an asterisk are required.</p>
        <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

        <form method="post" action="<?= e(url('/contact')) ?>" class="mt-6" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="t" value="<?= time() ?>">
          <div class="hp" aria-hidden="true">
            <label for="company_website">Leave this field empty</label>
            <input type="text" id="company_website" name="company_website" tabindex="-1" autocomplete="off">
          </div>

          <fieldset style="border:0;padding:0;margin:0 0 var(--s-5)">
            <legend class="field__label" style="padding:0;margin-bottom:.6rem">What is this about? <span class="req">*</span></legend>
            <div class="choice-grid">
              <?php foreach ($topics as $key => $label): ?>
                <label class="choice">
                  <input type="radio" name="topic" value="<?= e($key) ?>" <?= (old('topic', $topic) === $key) ? 'checked' : '' ?>>
                  <span><?= e($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <?php if ($error = error_for('topic')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
          </fieldset>

          <div class="field-row">
            <div class="field">
              <label class="field__label" for="name">Full name <span class="req">*</span></label>
              <input class="input" type="text" id="name" name="name" required autocomplete="name"
                     value="<?= e(old('name')) ?>" <?= error_for('name') ? 'aria-invalid="true"' : '' ?>>
              <?php if ($error = error_for('name')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
            </div>
            <div class="field">
              <label class="field__label" for="email">Work email <span class="req">*</span></label>
              <input class="input" type="email" id="email" name="email" required autocomplete="email"
                     value="<?= e(old('email')) ?>" <?= error_for('email') ? 'aria-invalid="true"' : '' ?>>
              <?php if ($error = error_for('email')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label class="field__label" for="company">Company</label>
              <input class="input" type="text" id="company" name="company" autocomplete="organization" value="<?= e(old('company')) ?>">
            </div>
            <div class="field">
              <label class="field__label" for="phone">Phone <span class="field__hint">optional</span></label>
              <input class="input" type="tel" id="phone" name="phone" autocomplete="tel" value="<?= e(old('phone')) ?>">
              <?php if ($error = error_for('phone')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label class="field__label" for="budget">Indicative budget</label>
              <select class="select" id="budget" name="budget">
                <option value="">Prefer not to say</option>
                <?php foreach ($budgets as $key => $label): ?>
                  <option value="<?= e($key) ?>" <?= old('budget') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label class="field__label" for="timeline">Timeline</label>
              <select class="select" id="timeline" name="timeline">
                <option value="">Not decided</option>
                <?php foreach (['Immediate', 'This quarter', 'Next quarter', 'Within 12 months', 'Exploring'] as $option): ?>
                  <option value="<?= e($option) ?>" <?= old('timeline') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="field">
            <label class="field__label" for="message">
              Project details <span class="req">*</span>
              <span class="field__hint">What is going wrong, and what would good look like?</span>
            </label>
            <textarea class="textarea" id="message" name="message" required
                      <?= error_for('message') ? 'aria-invalid="true"' : '' ?>><?= e(old('message', $prefillMessage)) ?></textarea>
            <?php if ($error = error_for('message')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
          </div>

          <div class="field">
            <label class="check">
              <input type="checkbox" name="consent" value="1" required <?= old('consent') ? 'checked' : '' ?>>
              <span>
                I agree that TECHBISS may store and process this enquiry to respond to it,
                as described in the <a href="<?= e(url('/legal/privacy')) ?>" style="color:var(--accent-2)">privacy notice</a>. <span class="req">*</span>
              </span>
            </label>
            <?php if ($error = error_for('consent')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
          </div>

          <button class="btn btn--primary btn--lg btn--block mt-5" type="submit">
            Send enquiry<?= icon('arrow-right') ?>
          </button>
          <p class="tiny dim center mt-4">We reply within one business day. No sequences, no cold calls.</p>
        </form>
      </div>

      <aside class="stack" style="--flow:var(--s-4)" data-reveal="80">
        <div class="card">
          <h2 class="h5">Direct lines</h2>
          <div class="stack mt-4" style="--flow:.85rem">
            <?php foreach ([
              ['mail', 'New business', $site['brand']['sales_email'], 'mailto:' . $site['brand']['sales_email']],
              ['ticket', 'Existing clients', $site['brand']['support_email'], 'mailto:' . $site['brand']['support_email']],
              ['phone', 'Call us', $site['brand']['phone'], 'tel:' . $site['brand']['phone_href']],
            ] as $line): ?>
              <a href="<?= e($line[3]) ?>" style="display:flex;gap:.75rem;align-items:flex-start">
                <?= icon($line[0], ['size' => 18]) ?>
                <span>
                  <span class="tiny dim" style="display:block"><?= e($line[1]) ?></span>
                  <strong style="font-size:var(--t-0)"><?= e($line[2]) ?></strong>
                </span>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <h2 class="h5">Offices</h2>
          <div class="stack mt-4" style="--flow:.9rem">
            <?php foreach ($site['offices'] as $office): ?>
              <div style="padding-bottom:.9rem;border-bottom:1px solid var(--line)">
                <div class="between">
                  <strong style="font-size:var(--t-0)"><?= e($office['city']) ?></strong>
                  <span class="tiny dim"><?= e($office['timezone']) ?></span>
                </div>
                <p class="tiny dim mt-3" style="line-height:1.6"><?= e($office['address']) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card" style="border-color:var(--accent-line);background:linear-gradient(160deg,var(--accent-soft),transparent 70%),var(--surface)">
          <h2 class="h5">Buying from the Marketplace?</h2>
          <p class="small muted mt-3">
            Licensing questions, migration help and installation support are handled
            by the product team, usually the same day.
          </p>
          <div class="cluster mt-4">
            <a class="btn btn--sm btn--ghost" href="<?= e(url('/marketplace/licensing')) ?>">Licensing</a>
            <a class="btn btn--sm btn--ghost" href="<?= e(url('/marketplace/installer')) ?>">Installer</a>
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>
<?php $view->stop(); ?>
