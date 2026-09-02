<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$services = Database::all('SELECT title FROM services WHERE is_active = 1 ORDER BY sort_order');
$errors = [];
$sent   = false;

if (post()) {
    Csrf::check();
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim(mb_strtolower((string)($_POST['email'] ?? '')));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $svc   = trim((string)($_POST['service'] ?? ''));
    $msg   = trim((string)($_POST['message'] ?? ''));

    /* Honeypot — real people leave it empty. */
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        $sent = true;
    } else {
        if ($name === '')          $errors[] = 'Enter your name.';
        if (!is_email($email))     $errors[] = 'Enter a valid email address.';
        if (mb_strlen($msg) < 10)  $errors[] = 'Tell us a little more about what you need.';
        if (mb_strlen($msg) > 5000) $errors[] = 'Please keep the message under 5000 characters.';

        if (!$errors) {
            $recent = (int)Database::value(
                'SELECT COUNT(*) FROM enquiries WHERE email = :e AND created_at > :t',
                ['e' => $email, 't' => date('Y-m-d H:i:s', time() - 900)], 0);
            if ($recent >= 3) {
                $errors[] = 'You have already sent a few messages. We will reply to those first.';
            } else {
                Database::insert('enquiries', [
                    'name'       => $name,
                    'email'      => $email,
                    'phone'      => $phone ?: null,
                    'service'    => $svc ?: null,
                    'message'    => $msg,
                    'status'     => 'new',
                    'created_at' => now(),
                ]);
                log_activity('enquiry.create', 'enquiry', null, $email);
                $sent = true;
            }
        }
    }
}

$PAGE_TITLE = 'Contact';
$META_DESC  = 'Tell us what you are building. ' . Settings::get('site_name') . ' replies within one business day.';
$CANONICAL  = url('contact.php');
require __DIR__ . '/partials/public_header.php';
?>
<section class="pagehead" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal">Start here</p>
    <h1 class="pagehead__title reveal">Tell us what you’re building.<br><span class="grad">We’ll figure out the technology.</span></h1>
    <p class="pagehead__lede reveal">A first call is free and there is no obligation. We reply within one business day.</p>
  </div>
</section>

<section class="contact">
  <div class="shell contact__grid">
    <div class="contact__form reveal">
      <?php if ($sent): ?>
        <div class="notice notice--ok">
          <p><b>Message sent.</b></p>
          <p>Thanks — we have it, and we will come back to you within one business day.</p>
          <p><a class="link" href="<?= e(url('services.php')) ?>">Meanwhile, see what we build <span aria-hidden="true">→</span></a></p>
        </div>
      <?php else: ?>
        <?php if ($errors): ?>
          <div class="notice notice--err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
        <?php endif; ?>
        <form method="post" class="wform">
          <?= Csrf::field() ?>
          <div class="wform__row">
            <label><span>Your name</span><input name="name" required value="<?= e(old('name')) ?>"></label>
            <label><span>Email</span><input name="email" type="email" required value="<?= e(old('email')) ?>"></label>
          </div>
          <div class="wform__row">
            <label><span>Phone <small>optional</small></span><input name="phone" value="<?= e(old('phone')) ?>"></label>
            <label><span>What do you need?</span>
              <select name="service">
                <option value="">Not sure yet</option>
                <?php foreach ($services as $s): ?>
                  <option value="<?= e($s['title']) ?>"<?= old('service') === $s['title'] ? ' selected' : '' ?>><?= e($s['title']) ?></option>
                <?php endforeach; ?>
                <option value="Something else"<?= old('service') === 'Something else' ? ' selected' : '' ?>>Something else</option>
              </select></label>
          </div>
          <label><span>About your project</span>
            <textarea name="message" rows="7" required
              placeholder="What does your business do, what is not working today, and what would success look like?"><?= e(old('message')) ?></textarea></label>
          <p class="hp" aria-hidden="true"><label>Leave this empty<input name="website" tabindex="-1" autocomplete="off"></label></p>
          <button class="btn btn--primary btn--lg magnetic" type="submit">Send message <span class="btn__arrow">→</span></button>
        </form>
      <?php endif; ?>
    </div>

    <aside class="contact__aside reveal">
      <div class="infobox">
        <h2>Reach us directly</h2>
        <dl>
          <div><dt>Email</dt><dd><a class="link" href="mailto:<?= e(Settings::get('contact_email')) ?>"><?= e(Settings::get('contact_email')) ?></a></dd></div>
          <?php if (Settings::get('contact_phone')): ?>
            <div><dt>Phone</dt><dd><a class="link" href="tel:<?= e(Settings::get('contact_phone')) ?>"><?= e(Settings::get('contact_phone')) ?></a></dd></div>
          <?php endif; ?>
          <?php if (Settings::get('contact_address')): ?>
            <div><dt>Address</dt><dd><?= enl(Settings::get('contact_address')) ?></dd></div>
          <?php endif; ?>
          <?php if (Settings::get('contact_hours')): ?>
            <div><dt>Hours</dt><dd><?= e(Settings::get('contact_hours')) ?></dd></div>
          <?php endif; ?>
        </dl>
      </div>
      <div class="infobox">
        <h2>Already a client?</h2>
        <p>Raise a support request, ask for maintenance or track your renewals in the portal.</p>
        <a class="btn btn--ghost btn--block magnetic" href="<?= e(url('login.php')) ?>">Open the client portal <span class="btn__arrow">→</span></a>
      </div>
      <ul class="facts">
        <li><span class="mono">reply time</span><b>Within one business day</b></li>
        <li><span class="mono">first call</span><b>Free, no obligation</b></li>
        <li><span class="mono">engagement</span><b>Project or retainer</b></li>
      </ul>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
