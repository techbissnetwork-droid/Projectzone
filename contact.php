<?php
require __DIR__ . '/app/bootstrap.php';
require_installed();
require __DIR__ . '/app/partials/sections.php';

$errors = [];
$sent   = false;
$values = ['name' => '', 'email' => '', 'company' => '', 'phone' => '', 'service' => '', 'budget' => '', 'message' => ''];

$serviceOptions = lines(txt('contact.form.services'));
$budgetOptions  = lines(txt('contact.form.budgets'));

if (is_post()) {
    csrf_check();

    foreach ($values as $k => $_) {
        $values[$k] = post($k);
    }

    // Bots fill every field they find; this one is hidden from people.
    $isBot = post('website') !== '';

    // A genuine visitor takes longer than three seconds to write a message.
    $started = (int) ($_SESSION['form_started'] ?? 0);
    if ($started > 0 && (time() - $started) < 3) {
        $isBot = true;
    }

    if (throttle_count('enquiry', 3600) >= 5) {
        $errors[] = 'Too many messages sent from this connection. Please try again later, or email us directly.';
    }

    if ($values['name'] === '') {
        $errors[] = 'Please tell us your name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That email address does not look right.';
    }
    if (mb_strlen($values['message']) < 10) {
        $errors[] = 'Please describe what you need in a little more detail.';
    }
    // Only accept values we actually offered.
    if ($values['service'] !== '' && !in_array($values['service'], $serviceOptions, true)) {
        $values['service'] = '';
    }
    if ($values['budget'] !== '' && !in_array($values['budget'], $budgetOptions, true)) {
        $values['budget'] = '';
    }

    if (!$errors) {
        if ($isBot) {
            // Silently accept and discard, so the bot does not learn to retry.
            $sent = true;
        } else {
            $row = [
                'name'       => mb_substr($values['name'], 0, 190),
                'email'      => mb_substr($values['email'], 0, 190),
                'company'    => mb_substr($values['company'], 0, 190),
                'phone'      => mb_substr($values['phone'], 0, 60),
                'service'    => mb_substr($values['service'], 0, 190),
                'budget'     => mb_substr($values['budget'], 0, 120),
                'message'    => mb_substr($values['message'], 0, 8000),
                'status'     => 'new',
                'ip'         => client_ip(),
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'created_at' => now(),
            ];
            $id = db_insert('enquiries', $row);
            throttle_hit('enquiry');

            // The enquiry is saved before mail is attempted, so a mail failure
            // never loses it — it is still in the admin area either way.
            if (send_enquiry_mail($row)) {
                db_update('enquiries', $id, ['mailed' => 1]);
            }
            $sent = true;
            $values = array_map(fn() => '', $values);
        }
    }
}

$_SESSION['form_started'] = time();

$title = txt('contact.meta.title', 'Contact — TECHBISS');
$desc  = txt('contact.meta.desc');
require __DIR__ . '/app/partials/head.php';
require __DIR__ . '/app/partials/header.php';

$crumb = 'Contact';
$eyebrowKey = 'contact.hero.eyebrow';
$headingKey = 'contact.hero.heading';
$leadKey    = 'contact.hero.lead';
require __DIR__ . '/app/partials/pagehero.php';
?>

<section class="sec">
  <div class="wrap">
    <div class="contact-grid">

      <div class="reveal">
        <?php if ($sent): ?>
          <div class="notice notice--ok">
            <h3><?= e(txt('contact.form.success')) ?></h3>
            <p>If it is urgent, call <a href="tel:<?= e(txt('site.phone_link')) ?>"><?= e(txt('site.phone')) ?></a>.</p>
          </div>
        <?php else: ?>
          <?php if ($errors): ?>
            <div class="notice notice--err">
              <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>

          <form class="enquiry" method="post" action="<?= e(base_url('contact.php')) ?>#main" novalidate>
            <?= csrf_field() ?>
            <div class="field-row">
              <div class="field">
                <label for="name">Your name *</label>
                <input type="text" id="name" name="name" autocomplete="name" value="<?= e($values['name']) ?>" required>
              </div>
              <div class="field">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" autocomplete="email" value="<?= e($values['email']) ?>" required>
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="company">Business name</label>
                <input type="text" id="company" name="company" autocomplete="organization" value="<?= e($values['company']) ?>">
              </div>
              <div class="field">
                <label for="phone">Phone / WhatsApp</label>
                <input type="tel" id="phone" name="phone" autocomplete="tel" value="<?= e($values['phone']) ?>">
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="service">What do you need?</label>
                <select id="service" name="service">
                  <?php foreach ($serviceOptions as $opt): ?>
                    <option<?= $opt === $values['service'] ? ' selected' : '' ?>><?= e($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="budget">Rough budget</label>
                <select id="budget" name="budget">
                  <?php foreach ($budgetOptions as $opt): ?>
                    <option<?= $opt === $values['budget'] ? ' selected' : '' ?>><?= e($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="field">
              <label for="message">What does the business do, and what's missing? *</label>
              <textarea id="message" name="message" required placeholder="e.g. We run a two-branch pharmacy. We have a Facebook page and nothing else. People call to ask if we have stock and what time we close."><?= e($values['message']) ?></textarea>
            </div>

            <div class="hp" aria-hidden="true">
              <label for="website">Leave this field empty</label>
              <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <p class="form-status" id="formStatus" role="status" aria-live="polite"></p>
            <button class="btn btn--primary magnetic" type="submit">Send enquiry →</button>
            <p class="form-note">Your message goes straight to <?= e(txt('site.email')) ?>. We never pass it on.</p>
          </form>
        <?php endif; ?>
      </div>

      <aside class="contact-side reveal">
        <div class="ccard">
          <h4>Email</h4>
          <p><a href="mailto:<?= e(txt('site.email')) ?>"><?= e(txt('site.email')) ?></a><br>Answered within one business day.</p>
        </div>
        <div class="ccard">
          <h4>Phone &amp; WhatsApp</h4>
          <p><a href="tel:<?= e(txt('site.phone_link')) ?>"><?= e(txt('site.phone')) ?></a><br><?= e(txt('site.hours')) ?></p>
        </div>
        <div class="ccard">
          <h4>Support (existing clients)</h4>
          <p><a href="mailto:<?= e(txt('site.support_email')) ?>"><?= e(txt('site.support_email')) ?></a><br>Something down or expiring? Put URGENT in the subject.</p>
        </div>
        <?php $next = lines(txt('contact.next')); if ($next): ?>
        <div class="ccard">
          <h4>What happens next</h4>
          <p><?php foreach ($next as $n => $step): ?><?= ($n + 1) ?> — <?= e($step) ?><br><?php endforeach; ?></p>
        </div>
        <?php endif; ?>
        <div class="ccard">
          <h4>Bring these if you have them</h4>
          <p><?= e(txt('contact.bring')) ?></p>
        </div>
      </aside>

    </div>
  </div>
</section>

<?php
section_statement('contact.statement');
section_faq('contact', 'contact.faq.heading', 'contact.faq.sub');
require __DIR__ . '/app/partials/footer.php';
