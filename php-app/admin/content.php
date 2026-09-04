<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_installed('../install/');

$staff = require_staff();
require_staff_access($staff, 'content.php');
$pdo = db();

$defaults = require __DIR__ . '/../includes/default_content.php';

// A curated set of icon keys that exist in the public site's icon set
// (assets/app.js ICONS) — kept separate from the admin panel's own
// smaller icon set in includes/icons.php.
$ICON_CHOICES = [
    'monitor', 'code', 'globe', 'rocket', 'chart', 'cart', 'heart', 'gear',
    'spark', 'flag', 'box', 'shield', 'users', 'chat', 'star', 'compass',
    'target', 'bolt', 'layers', 'refresh', 'mail', 'phone', 'calendar',
    'lock', 'book', 'search', 'cloud',
];

function icon_select(string $name, string $selected, array $choices): string
{
    $out = '<select name="' . e($name) . '">';
    foreach ($choices as $choice) {
        $sel = $choice === $selected ? ' selected' : '';
        $out .= '<option value="' . e($choice) . '"' . $sel . '>' . e($choice) . '</option>';
    }
    $out .= '</select>';
    return $out;
}

function lines_to_array(string $text): array
{
    $lines = array_map('trim', explode("\n", str_replace("\r\n", "\n", $text)));
    return array_values(array_filter($lines, fn($l) => $l !== ''));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check((string)($_POST['csrf'] ?? ''))) {
        flash('Your session expired — please try again.', 'error');
        header('Location: content.php');
        exit;
    }

    $services = [];
    for ($i = 0; $i < 6; $i++) {
        $name = trim((string)($_POST["svc_name_$i"] ?? ''));
        if ($name === '') {
            continue;
        }
        $services[] = [
            'icon' => trim((string)($_POST["svc_icon_$i"] ?? '')) ?: 'star',
            'name' => $name,
            'blurb' => trim((string)($_POST["svc_blurb_$i"] ?? '')),
            'bullets' => lines_to_array((string)($_POST["svc_bullets_$i"] ?? '')),
        ];
    }

    $solutions = [];
    for ($i = 0; $i < 5; $i++) {
        $name = trim((string)($_POST["sol_name_$i"] ?? ''));
        if ($name === '') {
            continue;
        }
        $solutions[] = [
            'icon' => trim((string)($_POST["sol_icon_$i"] ?? '')) ?: 'star',
            'name' => $name,
            'out' => lines_to_array((string)($_POST["sol_out_$i"] ?? '')),
        ];
    }

    $caseStudies = [];
    for ($i = 0; $i < 6; $i++) {
        $client = trim((string)($_POST["case_client_$i"] ?? ''));
        if ($client === '') {
            continue;
        }
        $caseStudies[] = [
            'sector' => trim((string)($_POST["case_sector_$i"] ?? '')),
            'icon' => trim((string)($_POST["case_icon_$i"] ?? '')) ?: 'star',
            'client' => $client,
            'stat' => trim((string)($_POST["case_stat_$i"] ?? '')),
            'statLabel' => trim((string)($_POST["case_statlabel_$i"] ?? '')),
            'quote' => trim((string)($_POST["case_quote_$i"] ?? '')),
            'body' => trim((string)($_POST["case_body_$i"] ?? '')),
        ];
    }

    $pricing = [];
    for ($i = 0; $i < 3; $i++) {
        $name = trim((string)($_POST["plan_name_$i"] ?? ''));
        if ($name === '') {
            continue;
        }
        $m = trim((string)($_POST["plan_m_$i"] ?? ''));
        $y = trim((string)($_POST["plan_y_$i"] ?? ''));
        $pricing[] = [
            'n' => $name,
            'm' => $m === '' ? null : (int)$m,
            'y' => $y === '' ? null : (int)$y,
            'd' => trim((string)($_POST["plan_desc_$i"] ?? '')),
            'f' => lines_to_array((string)($_POST["plan_features_$i"] ?? '')),
            'cta' => trim((string)($_POST["plan_cta_$i"] ?? '')),
            'rec' => isset($_POST["plan_rec_$i"]),
        ];
    }

    $pricingFaq = [];
    for ($i = 0; $i < 5; $i++) {
        $q = trim((string)($_POST["faq_q_$i"] ?? ''));
        if ($q === '') {
            continue;
        }
        $pricingFaq[] = [$q, trim((string)($_POST["faq_a_$i"] ?? ''))];
    }

    $team = [];
    for ($i = 0; $i < 4; $i++) {
        $name = trim((string)($_POST["team_name_$i"] ?? ''));
        if ($name === '') {
            continue;
        }
        $team[] = [
            'i' => strtoupper(trim((string)($_POST["team_initials_$i"] ?? ''))) ?: strtoupper(substr($name, 0, 2)),
            'n' => $name,
            'r' => trim((string)($_POST["team_role_$i"] ?? '')),
        ];
    }

    $values = [];
    for ($i = 0; $i < 4; $i++) {
        $title = trim((string)($_POST["val_title_$i"] ?? ''));
        if ($title === '') {
            continue;
        }
        $values[] = [
            'icon' => trim((string)($_POST["val_icon_$i"] ?? '')) ?: 'star',
            't' => $title,
            'd' => trim((string)($_POST["val_desc_$i"] ?? '')),
        ];
    }

    $stmt = $pdo->prepare('INSERT INTO settings (id, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute(['services_json', json_encode($services ?: $defaults['services'])]);
    $stmt->execute(['solutions_json', json_encode($solutions ?: $defaults['solutions'])]);
    $stmt->execute(['case_studies_json', json_encode($caseStudies ?: $defaults['case_studies'])]);
    $stmt->execute(['pricing_json', json_encode($pricing ?: $defaults['pricing'])]);
    $stmt->execute(['pricing_faq_json', json_encode($pricingFaq ?: $defaults['pricing_faq'])]);
    $stmt->execute(['team_json', json_encode($team ?: $defaults['team'])]);
    $stmt->execute(['values_json', json_encode($values ?: $defaults['values'])]);

    flash('Content updated — live on the public site now.');
    header('Location: content.php');
    exit;
}

// Bypass the request-lifetime cache so the form shows what was just saved.
$current = $pdo->query('SELECT id, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$services = content_section('services_json', $defaults['services']);
$solutions = content_section('solutions_json', $defaults['solutions']);
$caseStudies = content_section('case_studies_json', $defaults['case_studies']);
$pricing = content_section('pricing_json', $defaults['pricing']);
$pricingFaq = content_section('pricing_faq_json', $defaults['pricing_faq']);
$team = content_section('team_json', $defaults['team']);
$values = content_section('values_json', $defaults['values']);
$token = csrf_token();
?>
<!doctype html>
<html lang="en"<?= palette_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Content — TECHBISS Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?: '1' ?>">
<style>.content-item{ border:1px solid var(--border-soft); border-radius:14px; padding:16px; margin-bottom:14px; }
.content-item summary{ cursor:pointer; font-family:var(--font-display); font-weight:600; margin-bottom:12px; }</style>
</head>
<body>
<?= admin_header($staff, 'content.php') ?>
<main class="admin-page">
  <?= admin_flash_html() ?>
  <div class="admin-toolbar">
    <div><h1 style="margin-bottom:4px;">Site content</h1><p class="lede" style="margin-bottom:0;">Edit the Services, Industries, Case Studies, Pricing and About page content shown on the public site.</p></div>
  </div>

  <form method="post">
    <input type="hidden" name="csrf" value="<?= e($token) ?>">

    <div class="settings-tabs">
      <input type="radio" name="ctab" id="tab-c-services" class="tab-radio" checked>
      <input type="radio" name="ctab" id="tab-c-industries" class="tab-radio">
      <input type="radio" name="ctab" id="tab-c-cases" class="tab-radio">
      <input type="radio" name="ctab" id="tab-c-pricing" class="tab-radio">
      <input type="radio" name="ctab" id="tab-c-team" class="tab-radio">

      <div class="tab-labels">
        <label for="tab-c-services"><?= ico('grid') ?> Services</label>
        <label for="tab-c-industries"><?= ico('users') ?> Industries</label>
        <label for="tab-c-cases"><?= ico('star') ?> Case Studies</label>
        <label for="tab-c-pricing"><?= ico('chart') ?> Pricing &amp; FAQ</label>
        <label for="tab-c-team"><?= ico('edit') ?> About</label>
      </div>

      <div class="tab-panel" id="panel-c-services">
        <p class="lede" style="margin-bottom:14px;">Shown on the homepage and the Services page. Leave a name blank to remove that card.</p>
        <?php for ($i = 0; $i < 6; $i++): $it = $services[$i] ?? ['icon' => '', 'name' => '', 'blurb' => '', 'bullets' => []]; ?>
        <details class="content-item" <?= $i < 2 ? 'open' : '' ?>>
          <summary>Service <?= $i + 1 ?><?= $it['name'] !== '' ? ' — ' . e($it['name']) : '' ?></summary>
          <div class="grid grid-2" style="gap:16px;">
            <div class="field"><label>Icon</label><?= icon_select("svc_icon_$i", $it['icon'], $ICON_CHOICES) ?></div>
            <div class="field"><label>Name</label><input name="svc_name_<?= $i ?>" value="<?= e($it['name']) ?>"></div>
          </div>
          <div class="field"><label>Short description</label><input name="svc_blurb_<?= $i ?>" value="<?= e($it['blurb']) ?>"></div>
          <div class="field"><label>Bullet points (one per line)</label><textarea name="svc_bullets_<?= $i ?>"><?= e(implode("\n", $it['bullets'])) ?></textarea></div>
        </details>
        <?php endfor; ?>
      </div>

      <div class="tab-panel" id="panel-c-industries">
        <p class="lede" style="margin-bottom:14px;">Shown on the Solutions ("Who we help") page. Leave a name blank to remove that card.</p>
        <?php for ($i = 0; $i < 5; $i++): $it = $solutions[$i] ?? ['icon' => '', 'name' => '', 'out' => []]; ?>
        <details class="content-item" <?= $i < 2 ? 'open' : '' ?>>
          <summary>Industry <?= $i + 1 ?><?= $it['name'] !== '' ? ' — ' . e($it['name']) : '' ?></summary>
          <div class="grid grid-2" style="gap:16px;">
            <div class="field"><label>Icon</label><?= icon_select("sol_icon_$i", $it['icon'], $ICON_CHOICES) ?></div>
            <div class="field"><label>Name</label><input name="sol_name_<?= $i ?>" value="<?= e($it['name']) ?>"></div>
          </div>
          <div class="field"><label>What they get (one per line)</label><textarea name="sol_out_<?= $i ?>"><?= e(implode("\n", $it['out'])) ?></textarea></div>
        </details>
        <?php endfor; ?>
      </div>

      <div class="tab-panel" id="panel-c-cases">
        <p class="lede" style="margin-bottom:14px;">Shown on the homepage and the Work page. Leave a client name blank to remove that story.</p>
        <?php for ($i = 0; $i < 6; $i++): $it = $caseStudies[$i] ?? ['sector' => '', 'icon' => '', 'client' => '', 'stat' => '', 'statLabel' => '', 'quote' => '', 'body' => '']; ?>
        <details class="content-item">
          <summary>Case study <?= $i + 1 ?><?= $it['client'] !== '' ? ' — ' . e($it['client']) : '' ?></summary>
          <div class="grid grid-2" style="gap:16px;">
            <div class="field"><label>Client name</label><input name="case_client_<?= $i ?>" value="<?= e($it['client']) ?>"></div>
            <div class="field"><label>Sector</label><input name="case_sector_<?= $i ?>" value="<?= e($it['sector']) ?>" placeholder="e.g. Bakery"></div>
          </div>
          <div class="field"><label>Icon</label><?= icon_select("case_icon_$i", $it['icon'], $ICON_CHOICES) ?></div>
          <div class="grid grid-2" style="gap:16px;">
            <div class="field"><label>Stat</label><input name="case_stat_<?= $i ?>" value="<?= e($it['stat']) ?>" placeholder="e.g. +64%"></div>
            <div class="field"><label>Stat label</label><input name="case_statlabel_<?= $i ?>" value="<?= e($it['statLabel']) ?>" placeholder="e.g. online orders in month one"></div>
          </div>
          <div class="field"><label>Quote</label><input name="case_quote_<?= $i ?>" value="<?= e($it['quote']) ?>"></div>
          <div class="field"><label>Story</label><textarea name="case_body_<?= $i ?>"><?= e($it['body']) ?></textarea></div>
        </details>
        <?php endfor; ?>
      </div>

      <div class="tab-panel" id="panel-c-pricing">
        <p class="lede" style="margin-bottom:14px;">The 3 pricing plans and their FAQ, shown on the Pricing page.</p>
        <?php for ($i = 0; $i < 3; $i++): $it = $pricing[$i] ?? ['n' => '', 'm' => '', 'y' => '', 'd' => '', 'f' => [], 'cta' => '', 'rec' => false]; ?>
        <details class="content-item" open>
          <summary>Plan <?= $i + 1 ?><?= $it['n'] !== '' ? ' — ' . e($it['n']) : '' ?></summary>
          <div class="grid grid-2" style="gap:16px;">
            <div class="field"><label>Plan name</label><input name="plan_name_<?= $i ?>" value="<?= e($it['n']) ?>"></div>
            <div class="field"><label>Button text</label><input name="plan_cta_<?= $i ?>" value="<?= e($it['cta']) ?>"></div>
          </div>
          <div class="grid grid-2" style="gap:16px;">
            <div class="field"><label>Monthly price ($) <small style="font-weight:400;color:var(--ink-faint);">— leave blank for "Custom"</small></label><input name="plan_m_<?= $i ?>" value="<?= e($it['m'] === null ? '' : (string)$it['m']) ?>" inputmode="numeric"></div>
            <div class="field"><label>Annual price ($/mo, billed yearly)</label><input name="plan_y_<?= $i ?>" value="<?= e($it['y'] === null ? '' : (string)$it['y']) ?>" inputmode="numeric"></div>
          </div>
          <div class="field"><label>Description</label><input name="plan_desc_<?= $i ?>" value="<?= e($it['d']) ?>"></div>
          <div class="field"><label>Features (one per line)</label><textarea name="plan_features_<?= $i ?>"><?= e(implode("\n", $it['f'])) ?></textarea></div>
          <label class="flex items-center gap-8" style="font-size:.85rem;"><input type="checkbox" name="plan_rec_<?= $i ?>" <?= !empty($it['rec']) ? 'checked' : '' ?>> Mark as "Most popular"</label>
        </details>
        <?php endfor; ?>
        <?php for ($i = 0; $i < 5; $i++): $it = $pricingFaq[$i] ?? ['', '']; ?>
        <details class="content-item">
          <summary>FAQ <?= $i + 1 ?><?= ($it[0] ?? '') !== '' ? ' — ' . e($it[0]) : '' ?></summary>
          <div class="field"><label>Question</label><input name="faq_q_<?= $i ?>" value="<?= e($it[0] ?? '') ?>"></div>
          <div class="field"><label>Answer</label><textarea name="faq_a_<?= $i ?>"><?= e($it[1] ?? '') ?></textarea></div>
        </details>
        <?php endfor; ?>
      </div>

      <div class="tab-panel" id="panel-c-team">
        <p class="lede" style="margin-bottom:14px;">The leadership team and company values shown on the About page.</p>
        <?php for ($i = 0; $i < 4; $i++): $it = $team[$i] ?? ['i' => '', 'n' => '', 'r' => '']; ?>
        <details class="content-item" <?= $i < 2 ? 'open' : '' ?>>
          <summary>Team member <?= $i + 1 ?><?= $it['n'] !== '' ? ' — ' . e($it['n']) : '' ?></summary>
          <div class="grid grid-2" style="gap:16px;">
            <div class="field"><label>Name</label><input name="team_name_<?= $i ?>" value="<?= e($it['n']) ?>"></div>
            <div class="field"><label>Role</label><input name="team_role_<?= $i ?>" value="<?= e($it['r']) ?>"></div>
          </div>
          <div class="field" style="max-width:160px;"><label>Initials</label><input name="team_initials_<?= $i ?>" value="<?= e($it['i']) ?>" maxlength="3"></div>
        </details>
        <?php endfor; ?>
        <?php for ($i = 0; $i < 4; $i++): $it = $values[$i] ?? ['icon' => '', 't' => '', 'd' => '']; ?>
        <details class="content-item">
          <summary>Value <?= $i + 1 ?><?= $it['t'] !== '' ? ' — ' . e($it['t']) : '' ?></summary>
          <div class="grid grid-2" style="gap:16px;">
            <div class="field"><label>Icon</label><?= icon_select("val_icon_$i", $it['icon'], $ICON_CHOICES) ?></div>
            <div class="field"><label>Title</label><input name="val_title_<?= $i ?>" value="<?= e($it['t']) ?>"></div>
          </div>
          <div class="field"><label>Description</label><textarea name="val_desc_<?= $i ?>"><?= e($it['d']) ?></textarea></div>
        </details>
        <?php endfor; ?>
      </div>

    </div>

    <button class="btn btn-primary" type="submit">Save content</button>
  </form>
</main>
<?= admin_bottomnav($staff, 'content.php') ?>
</body>
</html>
