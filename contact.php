<?php
/**
 * Enquiry form.
 *
 * The enquiry is written to the database before mail is attempted, so a
 * failing mail server never loses one. The dashboard reports anything that
 * could not be sent.
 */
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$errors = [];
$sent   = false;
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

if (is_post()) {
    csrf_check();

    $data = [
        'name'       => post('name'),
        'email'      => strtolower(post('email')),
        'phone'      => post('phone'),
        'company'    => post('company'),
        'service'    => post('service'),
        'budget'     => post('budget'),
        'message'    => post('message'),
        'status'     => 'new',
        'mail_sent'  => 0,
        'created_at' => now(),
    ];

    if ($data['name'] === '')                 { $errors[] = 'Enter your name.'; }
    if (!valid_email($data['email']))         { $errors[] = 'Enter a valid email address.'; }
    if (mb_strlen($data['message']) < 10)     { $errors[] = 'Tell us a little more — at least a sentence.'; }
    if (mb_strlen($data['message']) > 6000)   { $errors[] = 'That message is too long.'; }
    if (post('company_website') !== '')       { $errors[] = 'Rejected.'; }   // honeypot

    if (!$errors) {
        $id   = db_insert('enquiries', $data);
        $ok   = mail_enquiry($data);
        if ($ok) {
            db_update('enquiries', $id, ['mail_sent' => 1]);
        }
        log_activity('Enquiry received', 'enquiry', $id, $data['name']);
        $sent = true;

        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            exit;
        }
    } elseif ($wantsJson) {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
        exit;
    }
}

$pageTitle = setting('contact.meta.title');
$pageDesc  = setting('contact.meta.desc');
$activeNav = 'contact';
$services  = active_services();

include APP_DIR . '/partials/head.php';

page_head(
    setting('contact.hero.eyebrow'),
    setting('contact.hero.line1'),
    setting('contact.hero.line2'),
    setting('contact.hero.lead'),
    [['index.php', 'Home'], [null, 'Contact']]
);
?>

<section id="form"><div class="wrap">
<?php if ($sent): ?>
  <div class="notebox ok rv">
    <h3>Message received.</h3>
    <p><?= esc(setting('contact.thanks')) ?></p>
    <div class="acts" style="margin-top:20px">
      <a class="pill" href="portfolio.php">See our work</a>
      <a class="pill ghost" href="index.php">Back to the site</a>
    </div>
  </div>
<?php else: ?>
  <div class="formgrid rv">
    <form method="post" class="enquiry" action="contact.php#form">
      <?= csrf_field() ?>
      <h2 class="subhead big"><?= esc(setting('contact.form.heading')) ?></h2>
<?php if ($errors): ?>
      <div class="formstatus show bad">
        <?php foreach ($errors as $e) { echo esc($e) . '<br>'; } ?>
      </div>
<?php endif; ?>
      <div class="two">
        <div class="field"><label for="name">Your name <span class="req">*</span></label>
          <input id="name" name="name" required value="<?= esc(post('name')) ?>">
          <span class="err">We need something to call you.</span></div>
        <div class="field"><label for="email">Email <span class="req">*</span></label>
          <input id="email" name="email" type="email" required value="<?= esc(post('email')) ?>">
          <span class="err">Enter an address we can reply to.</span></div>
      </div>
      <div class="two">
        <div class="field"><label for="phone">Phone</label>
          <input id="phone" name="phone" value="<?= esc(post('phone')) ?>"></div>
        <div class="field"><label for="company">Business name</label>
          <input id="company" name="company" value="<?= esc(post('company')) ?>"></div>
      </div>
      <div class="two">
        <div class="field"><label for="service">What do you need</label>
          <select id="service" name="service">
            <option value="">Not sure yet</option>
<?php foreach ($services as $s): ?>
            <option value="<?= esc($s['title']) ?>"<?= post('service') === $s['title'] ? ' selected' : '' ?>>
              <?= esc($s['title']) ?></option>
<?php endforeach; ?>
            <option value="The whole stack"<?= post('service') === 'The whole stack' ? ' selected' : '' ?>>The whole stack</option>
          </select></div>
        <div class="field"><label for="budget">Rough budget</label>
          <select id="budget" name="budget">
<?php foreach (['', 'Under $1,500', '$1,500 – $4,000', '$4,000 – $10,000', 'Over $10,000', 'No idea yet'] as $b): ?>
            <option value="<?= esc($b) ?>"<?= post('budget') === $b && $b !== '' ? ' selected' : '' ?>>
              <?= $b === '' ? 'Prefer not to say' : esc($b) ?></option>
<?php endforeach; ?>
          </select></div>
      </div>
      <div class="field"><label for="message">What is going on <span class="req">*</span></label>
        <textarea id="message" name="message" required minlength="10"
          placeholder="What the business does, what you have online today, and what is frustrating right now."><?= esc(post('message')) ?></textarea>
        <span class="err">A sentence or two is plenty.</span></div>

      <input class="hp" type="text" name="company_website" tabindex="-1" autocomplete="off" aria-hidden="true">
      <div id="formstatus" class="formstatus"></div>
      <button class="pill lg" type="submit">Send it over</button>
      <p class="formnote"><?= esc(setting('contact.form.note')) ?></p>
    </form>

    <aside class="contactside">
      <div class="cbit">
        <h4>Email</h4>
        <a href="mailto:<?= esc(setting('site.email')) ?>"><?= esc(setting('site.email')) ?></a>
        <p>For anything new. We answer within one business day.</p>
      </div>
      <div class="cbit">
        <h4>Support</h4>
        <a href="mailto:<?= esc(setting('site.support_email')) ?>"><?= esc(setting('site.support_email')) ?></a>
        <p>Already a client? Raise it in the
           <a href="client/login.php" style="display:inline;font-size:inherit;font-family:inherit;font-weight:400;color:var(--acc)">client portal</a>
           so it is tracked against your project.</p>
      </div>
<?php if (setting('site.phone')): ?>
      <div class="cbit">
        <h4>Phone</h4>
        <strong><?= esc(setting('site.phone')) ?></strong>
        <p><?= esc(setting('site.hours')) ?></p>
      </div>
<?php endif; ?>
      <div class="cbit">
        <h4>What happens next</h4>
        <p>You get a straight answer about what is worth doing first &mdash; and what is not.
           No script, no deck, no pressure to buy the whole stack on day one.</p>
      </div>
    </aside>
  </div>
<?php endif; ?>
</div></section>

<?php
faq_block('services', 'While you are here.',
    'The questions we get asked before anyone signs anything.');
include APP_DIR . '/partials/footer.php';
