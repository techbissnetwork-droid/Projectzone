<?php
$__page = 'settings';
$title = 'Site Settings';
require_once __DIR__ . '/_bootstrap.php';
require_login();

$keys = [
    'brand_name', 'theme_default', 'bg_theme', 'backdrop_image',
    'hero_eyebrow', 'hero_title_1', 'hero_title_2', 'hero_sub',
    'hero_cta1_label', 'hero_cta1_url', 'hero_cta2_label', 'hero_cta2_url',
    'hero_photo', 'hero_badge_label', 'hero_badge_value',
    'contact_title', 'contact_sub', 'contact_email',
    'sale_email', 'sale_chat_url',
    'footer_text', 'seo_title', 'seo_description', 'seo_canonical',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // One-click: overwrite the text copy (hero/contact/footer/SEO) + stat tiles
    // with the latest shipped premium defaults. Projects are left untouched.
    if (($_POST['action'] ?? '') === 'load_copy') {
        if (function_exists('starter_content')) {
            foreach (starter_content() as $k => $v) { set_setting($k, $v); }
        }
        if (function_exists('starter_stats')) {
            try {
                db()->exec("DELETE FROM stats");
                $st = db()->prepare("INSERT INTO stats (value,label,target,suffix,sort_order) VALUES (?,?,?,?,?)");
                foreach (starter_stats() as $r) { $st->execute($r); }
            } catch (Throwable $e) { /* stats optional */ }
        }
        flash('Latest premium copy loaded — your projects were left untouched.');
        touch_content();
        header('Location: ' . url('/admin/settings.php'));
        exit;
    }

    // Optional hero photo upload
    if (!empty($_FILES['hero_photo_file']['name']) && is_uploaded_file($_FILES['hero_photo_file']['tmp_name'])) {
        $f = $_FILES['hero_photo_file'];
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = mime_content_type($f['tmp_name']) ?: '';
        if (isset($allowed[$mime]) && $f['size'] <= 6 * 1024 * 1024) {
            if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }
            $name = 'hero-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            if (move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
                $_POST['hero_photo'] = '/uploads/' . $name;
            }
        } else {
            flash('Photo not saved: use JPG/PNG/WebP/GIF up to 6 MB.');
        }
    }

    foreach ($keys as $k) {
        if (array_key_exists($k, $_POST)) {
            $v = (string) $_POST[$k];
            if ($k === 'theme_default') { $v = $v === 'light' ? 'light' : 'dark'; }
            if ($k === 'bg_theme') { $v = bg_theme_key($v); }
            set_setting($k, $v);
        }
    }
    // Checkboxes: absent means "off"
    set_setting('backdrop_on', isset($_POST['backdrop_on']) ? '1' : '0');
    set_setting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');
    set_setting('projects_coming_soon', isset($_POST['projects_coming_soon']) ? '1' : '0');
    set_setting('seo_noindex', isset($_POST['seo_noindex']) ? '1' : '0');
    set_setting('sold_show_price', isset($_POST['sold_show_price']) ? '1' : '0');
    $motion = (string) ($_POST['motion_level'] ?? 'cinematic');
    set_setting('motion_level', in_array($motion, ['cinematic', 'subtle', 'off'], true) ? $motion : 'cinematic');
    $mode = (string) ($_POST['clean_urls_mode'] ?? 'auto');
    set_setting('clean_urls_mode', in_array($mode, ['auto', 'on', 'off'], true) ? $mode : 'auto');
    flash('Settings saved.');
    touch_content();
    header('Location: ' . url('/admin/settings.php'));
    exit;
}

require_once __DIR__ . '/includes/head.php';
function s(string $k): string { return e(setting($k)); }
?>
<div class="topbar"><div><h1 class="page-title">Site Settings</h1><div class="page-sub">All the text, links and branding on your public site.</div></div></div>

<div class="panel" style="border-color:rgba(0,255,179,.28)">
  <h2>Refresh site copy</h2>
  <div class="hint" style="margin-bottom:1rem">Load the latest polished premium copy for the hero, contact, footer, SEO and stat tiles. This overwrites those text fields with the newest defaults — <strong>your projects and games are left untouched</strong>. Anything you've customised in the fields below will be replaced, so tweak afterwards if needed.</div>
  <form method="post" action="<?= e(url('/admin/settings.php')) ?>" onsubmit="return confirm('Replace the hero / contact / footer / SEO text and stat tiles with the latest premium copy? Your projects and games stay as they are.')">
    <?= csrf_field() ?><input type="hidden" name="action" value="load_copy">
    <button class="btn btn-primary" type="submit">Load the latest premium copy</button>
  </form>
</div>

<form method="post" enctype="multipart/form-data" action="<?= e(url('/admin/settings.php')) ?>">
  <?= csrf_field() ?>

  <div class="panel">
    <h2>Brand &amp; theme</h2>
    <div class="grid-2">
      <div class="field"><label>Brand / name</label><input type="text" name="brand_name" value="<?= s('brand_name') ?>" /></div>
      <div class="field"><label>Default color theme</label>
        <select name="theme_default">
          <option value="dark" <?= setting('theme_default')==='dark'?'selected':'' ?>>Dark (recommended)</option>
          <option value="light" <?= setting('theme_default')==='light'?'selected':'' ?>>Light</option>
        </select>
      </div>
    </div>
    <div class="grid-2">
      <div class="field"><label>Default animated background</label>
        <select name="bg_theme">
          <?php foreach (bg_themes() as $bkey => $bmeta): ?>
            <option value="<?= e($bkey) ?>" <?= setting('bg_theme','aurora')===$bkey?'selected':'' ?>><?= e($bmeta[0]) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Visitors can switch themes themselves; this is the default. 11 animated options.</div>
      </div>
      <div class="field">
        <label>How much the site moves</label>
        <select name="motion_level">
          <option value="cinematic" <?= setting('motion_level','cinematic')==='cinematic'?'selected':'' ?>>Cinematic — it plays itself</option>
          <option value="subtle"    <?= setting('motion_level','cinematic')==='subtle'   ?'selected':'' ?>>Subtle — things fade in, nothing moves on its own</option>
          <option value="off"       <?= setting('motion_level','cinematic')==='off'      ?'selected':'' ?>>Off — a still page</option>
        </select>
        <div class="hint"><strong>Cinematic</strong> plays the sliders by themselves (they stop while you hover,
        while off screen, and while the tab is in the background), deals the cards of each slide out one after
        another, leans the buttons toward the pointer and drifts the headings as you scroll. Whichever you pick,
        a visitor whose device asks for reduced motion always gets the still version.</div>
      </div>
      <div class="field"><label>Faint photo backdrop</label>
        <div class="row-inline" style="margin-top:.35rem">
          <label class="switch"><input type="checkbox" name="backdrop_on" <?= setting('backdrop_on','1')==='1'?'checked':'' ?> /><span class="track"></span></label>
          <span class="small">Show a faint full-page photo behind the animation</span>
        </div>
      </div>
    </div>
    <div class="field"><label>Backdrop image path</label><input type="text" name="backdrop_image" value="<?= s('backdrop_image') ?>" /><div class="hint">Root-relative (e.g. /bg.jpg or /uploads/…) or a full URL.</div></div>
  </div>

  <div class="panel">
    <h2>Hero section</h2>
    <div class="field"><label>Eyebrow badge</label><input type="text" name="hero_eyebrow" value="<?= s('hero_eyebrow') ?>" /></div>
    <div class="grid-2">
      <div class="field"><label>Title — line 1</label><input type="text" name="hero_title_1" value="<?= s('hero_title_1') ?>" /></div>
      <div class="field"><label>Title — line 2 (gradient)</label><input type="text" name="hero_title_2" value="<?= s('hero_title_2') ?>" /></div>
    </div>
    <div class="field"><label>Subtitle</label><textarea name="hero_sub"><?= s('hero_sub') ?></textarea></div>
    <div class="grid-2">
      <div class="field"><label>Button 1 — label</label><input type="text" name="hero_cta1_label" value="<?= s('hero_cta1_label') ?>" /></div>
      <div class="field"><label>Button 1 — link</label><input type="text" name="hero_cta1_url" value="<?= s('hero_cta1_url') ?>" /></div>
      <div class="field"><label>Button 2 — label</label><input type="text" name="hero_cta2_label" value="<?= s('hero_cta2_label') ?>" /></div>
      <div class="field"><label>Button 2 — link</label><input type="text" name="hero_cta2_url" value="<?= s('hero_cta2_url') ?>" /></div>
    </div>
    <div class="grid-2">
      <div class="field"><label>Status badge — label</label><input type="text" name="hero_badge_label" value="<?= s('hero_badge_label') ?>" /></div>
      <div class="field"><label>Status badge — value</label><input type="text" name="hero_badge_value" value="<?= s('hero_badge_value') ?>" /></div>
    </div>
    <div class="grid-2">
      <div class="field"><label>Hero photo path</label><input type="text" name="hero_photo" value="<?= s('hero_photo') ?>" /><div class="hint">Root-relative (e.g. /bg.jpg or /uploads/…) or a full URL.</div></div>
      <div class="field"><label>…or upload a new photo</label><input type="file" name="hero_photo_file" accept="image/*" /><div class="hint">JPG/PNG/WebP/GIF, up to 6 MB.</div></div>
    </div>
  </div>

  <div class="panel">
    <h2>Projects slider</h2>
    <div class="field row-inline">
      <label class="switch"><input type="checkbox" name="projects_coming_soon" <?= setting('projects_coming_soon','1')==='1'?'checked':'' ?> /><span class="track"></span></label>
      <span class="small">Fill the last slide's empty spots with “Coming soon” cards. The number of slides grows automatically as you add projects (4 per slide).</span>
    </div>
    <div class="field row-inline">
      <label class="switch"><input type="checkbox" name="sold_show_price" <?= setting('sold_show_price','1')==='1'?'checked':'' ?> /><span class="track"></span></label>
      <span class="small">Show what each sold project sold for. Turn this off to keep your prices private — the Sold section still shows, just without the figures.</span>
    </div>
    <div class="hint">Sold projects get their own sliding section below the main one, four to a slide, exactly like your live projects.</div>
  </div>

  <div class="panel">
    <h2>Contact</h2>
    <div class="grid-2">
      <div class="field"><label>Headline</label><textarea name="contact_title"><?= s('contact_title') ?></textarea><div class="hint">Line breaks are preserved.</div></div>
      <div class="field"><label>Sub-text</label><textarea name="contact_sub"><?= s('contact_sub') ?></textarea><div class="hint">Line breaks are preserved.</div></div>
    </div>
    <div class="field" style="max-width:360px"><label>Contact email</label><input type="email" name="contact_email" value="<?= s('contact_email') ?>" /></div>
  </div>

  <div class="panel">
    <h2>“For Sale” buy button</h2>
    <div class="hint" style="margin-bottom:1rem">When a project's status is <strong>For Sale</strong>, its card shows the price and a <strong>Buy / Enquire</strong> button. If you set a chat link it opens that; otherwise it opens a pre-filled email.</div>
    <div class="grid-2">
      <div class="field"><label>Sale email</label><input type="email" name="sale_email" value="<?= s('sale_email') ?>" placeholder="you@example.com" /><div class="hint">Leave empty to reuse the contact email above.</div></div>
      <div class="field"><label>Chat / enquiry link <span class="muted">(optional)</span></label><input type="text" name="sale_chat_url" value="<?= s('sale_chat_url') ?>" placeholder="https://wa.me/… or https://t.me/… or any chat URL" /><div class="hint">WhatsApp, Telegram, a contact form — anything. Takes priority over email.</div></div>
    </div>
  </div>

  <div class="panel">
    <h2>SEO &amp; footer</h2>
    <div class="field"><label>SEO title</label><input type="text" name="seo_title" value="<?= s('seo_title') ?>" /></div>
    <div class="field"><label>SEO description</label><textarea name="seo_description"><?= s('seo_description') ?></textarea></div>
    <div class="grid-2">
      <div class="field"><label>Canonical URL</label><input type="url" name="seo_canonical" value="<?= s('seo_canonical') ?>" placeholder="<?= e(site_url('/')) ?>" /><div class="hint">Leave empty to auto-detect from the visitor's address. Detected now: <?= e(site_url('/')) ?></div></div>
      <div class="field"><label>Footer text</label><input type="text" name="footer_text" value="<?= s('footer_text') ?>" /></div>
    </div>
    <div class="field row-inline" style="margin-top:.4rem">
      <label class="switch"><input type="checkbox" name="seo_noindex" <?= setting('seo_noindex','0')==='1'?'checked':'' ?> /><span class="track"></span></label>
      <span class="small">Discourage search engines — keeps the site out of Google while you are still working on it.</span>
    </div>
    <div class="hint" style="margin-top:.85rem">
      Your <a href="<?= e(sitemap_url()) ?>" target="_blank" rel="noopener">sitemap</a> and
      <a href="<?= e(site_url('/robots.txt')) ?>" target="_blank" rel="noopener">robots.txt</a> are generated for you and
      always match what is on the site — including every project image, so those get indexed too. <strong><a href="<?= e(url('/admin/seo.php')) ?>">Search engines</a></strong> checks all of this
      against your live site and tells you the exact address to submit to Google. While maintenance mode or the switch above is on, both
      tell search engines to stay away.
    </div>
  </div>

  <div class="panel">
    <h2>Web addresses</h2>
    <div class="grid-2">
      <div class="field">
        <label>Hide <code>.php</code> in the address bar</label>
        <select name="clean_urls_mode">
          <option value="auto" <?= setting('clean_urls_mode','auto')==='auto'?'selected':'' ?>>Automatic — work it out (recommended)</option>
          <option value="on"   <?= setting('clean_urls_mode','auto')==='on'  ?'selected':'' ?>>Prefer the shortest form</option>
          <option value="off"  <?= setting('clean_urls_mode','auto')==='off' ?'selected':'' ?>>Never hide it</option>
        </select>
        <div class="hint">On <strong>Automatic</strong> your addresses never show <code>.php</code>, whatever your
        host supports. Where the server rewrites, you get the shortest form — <code>/admin/login</code>. Where it
        does not, the site uses the folder form — <code>/admin/login/</code> — which works on every server without
        any configuration. <strong>Prefer the shortest form</strong> uses <code>/admin/login</code> as soon as your server proves it
        rewrites — it will never switch to a shape your server cannot serve, so it cannot break your links.
        <strong>Never</strong> goes back to plain <code>.php</code>.</div>
      </div>
      <div class="field">
        <label>Is your server doing it?</label>
        <div class="actions" style="margin-top:.35rem">
          <button class="btn btn-ghost btn-sm" type="button" data-cleanurl-test="#cleanUrlNote">Check my server</button>
        </div>
        <div class="detect-note" id="cleanUrlNote">
          <?php $shape = ['bare' => '/admin/login', 'folder' => '/admin/login/', 'php' => '/admin/login.php']; ?>
          Right now your addresses look like <code><?= e($shape[url_style()] ?? '') ?></code>.
        </div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h2>Site status</h2>
    <div class="field row-inline">
      <label class="switch"><input type="checkbox" name="maintenance_mode" <?= setting('maintenance_mode','0')==='1'?'checked':'' ?> /><span class="track"></span></label>
      <span class="small">Maintenance mode — show a “be right back” page to visitors (you stay signed in and can still preview the site).</span>
    </div>
  </div>

  <div class="actions">
    <button class="btn btn-primary" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Save changes</button>
    <a class="btn btn-ghost" href="<?= e(url('/index.php')) ?>" target="_blank">Preview site</a>
  </div>
</form>

<?php require __DIR__ . '/includes/foot.php'; ?>
