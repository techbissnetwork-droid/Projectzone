<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'delete' && post()) {
    Csrf::check();
    $row = Database::one('SELECT * FROM payment_methods WHERE id = :i', ['i' => $id]);
    if ($row) {
        $used = (int)Database::value('SELECT COUNT(*) FROM orders WHERE payment_method_id = :i', ['i' => $id], 0);
        if ($used > 0) {
            Database::update('payment_methods', ['is_active' => 0], $id);
            Flash::ok('“' . $row['name'] . '” has been used on ' . $used . ' order(s), so it was switched off instead of deleted.');
        } else {
            Database::delete('payment_methods', $id);
            log_activity('payment.delete', 'payment_method', $id, $row['name']);
            Flash::ok('“' . $row['name'] . '” was deleted.');
        }
    }
    redirect('admin/payments.php');
}

if ($action === 'toggle' && post()) {
    Csrf::check();
    $row = Database::one('SELECT id, is_active FROM payment_methods WHERE id = :i', ['i' => $id]);
    if ($row) {
        Database::update('payment_methods', ['is_active' => $row['is_active'] ? 0 : 1], $id);
    }
    redirect('admin/payments.php');
}

if ($action === 'new' || $action === 'edit') {
    $m = $action === 'edit'
        ? Database::one('SELECT * FROM payment_methods WHERE id = :i', ['i' => $id])
        : ['provider' => 'manual', 'is_active' => 1, 'is_test' => 1, 'sort_order' => 0];
    if ($action === 'edit' && !$m) {
        http_response_code(404);
        exit('Payment method not found.');
    }
    $errors = [];
    $cfg = $action === 'edit' ? Payments::config($m) : [];

    if (post()) {
        Csrf::check();
        $d = static fn(string $k): ?string => ($v = trim((string)($_POST[$k] ?? ''))) !== '' ? $v : null;
        $name     = trim((string)($_POST['name'] ?? ''));
        $provider = (string)($_POST['provider'] ?? 'manual');
        if ($name === '')                          $errors[] = 'Enter a name buyers will recognise.';
        if (!isset(Payments::PROVIDERS[$provider])) $errors[] = 'Choose a valid provider.';

        /* Keep a saved secret when the field is left blank on an edit. */
        $newCfg = [];
        foreach (array_keys(Payments::PROVIDERS[$provider]['fields'] ?? []) as $key) {
            $posted = trim((string)($_POST['cfg_' . $key] ?? ''));
            $newCfg[$key] = $posted !== '' ? $posted : ($cfg[$key] ?? '');
            if ($newCfg[$key] === '') {
                $errors[] = Payments::PROVIDERS[$provider]['fields'][$key] . ' is required for ' . Payments::PROVIDERS[$provider]['name'] . '.';
            }
        }

        if (!$errors) {
            $code = slugify($d('code') ?? $name);
            $clash = Database::one('SELECT id FROM payment_methods WHERE code = :c' . ($action === 'edit' ? ' AND id <> :i' : ''),
                $action === 'edit' ? ['c' => $code, 'i' => $id] : ['c' => $code]);
            if ($clash) {
                $code .= '-' . substr(bin2hex(random_bytes(2)), 0, 3);
            }
            $data = [
                'code'           => $code,
                'name'           => $name,
                'provider'       => $provider,
                'summary'        => $d('summary'),
                'instructions'   => $d('instructions'),
                'account_name'   => $d('account_name'),
                'account_number' => $d('account_number'),
                'config'         => $newCfg ? json_encode($newCfg, JSON_UNESCAPED_SLASHES) : null,
                'is_active'      => !empty($_POST['is_active']) ? 1 : 0,
                'is_test'        => !empty($_POST['is_test']) ? 1 : 0,
                'sort_order'     => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($action === 'edit') {
                Database::update('payment_methods', $data, $id);
                Flash::ok('Payment method saved.');
            } else {
                $data['created_at'] = now();
                Database::insert('payment_methods', $data);
                Flash::ok('Payment method added.');
            }
            log_activity('payment.save', 'payment_method', $id ?: null, $name);
            redirect('admin/payments.php');
        }
        $m = array_merge($m, $_POST);
    }

    $PAGE_TITLE = $action === 'edit' ? 'Edit payment method' : 'New payment method';
    $AREA = 'admin';
    require __DIR__ . '/../partials/app_header.php';
    ?>
    <?php if ($errors): ?>
      <div class="alert err"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" class="form" style="max-width:820px">
      <?= Csrf::field() ?>
      <div class="fieldset">
        <p class="legend">What the buyer sees</p>
        <div class="row two">
          <label class="field"><span>Name</span>
            <input name="name" data-slug-source required maxlength="120" placeholder="Bank transfer"
                   value="<?= e($m['name'] ?? '') ?>"></label>
          <label class="field"><span>Code <small>auto</small></span>
            <input name="code" data-slug-target maxlength="40" value="<?= e($m['code'] ?? '') ?>"></label>
        </div>
        <label class="field"><span>One-line summary <small>shown next to the option at checkout</small></span>
          <input name="summary" maxlength="300" value="<?= e($m['summary'] ?? '') ?>"></label>
      </div>

      <div class="fieldset">
        <p class="legend">How it works</p>
        <label class="field"><span>Provider</span>
          <select name="provider" id="provider">
            <?php foreach (Payments::PROVIDERS as $k => $p): ?>
              <option value="<?= e($k) ?>"<?= ($m['provider'] ?? '') === $k ? ' selected' : '' ?>>
                <?= e($p['name']) ?> — <?= e($p['hint']) ?></option>
            <?php endforeach; ?>
          </select></label>

        <div data-when-provider="manual">
          <label class="field"><span>Instructions <small>shown after the order is placed</small></span>
            <textarea name="instructions" rows="4"><?= e($m['instructions'] ?? '') ?></textarea></label>
          <div class="row two">
            <label class="field"><span>Account name</span>
              <input name="account_name" value="<?= e($m['account_name'] ?? '') ?>"></label>
            <label class="field"><span>Account / wallet number</span>
              <input name="account_number" value="<?= e($m['account_number'] ?? '') ?>"></label>
          </div>
        </div>

        <?php foreach (Payments::PROVIDERS as $pk => $p):
          if (!$p['fields']) { continue; } ?>
          <div data-when-provider="<?= e($pk) ?>">
            <?php foreach ($p['fields'] as $fk => $fl):
              $saved = ($m['provider'] ?? '') === $pk ? ($cfg[$fk] ?? '') : ''; ?>
              <label class="field"><span><?= e($fl) ?>
                <?php if ($saved !== ''): ?><small>saved — leave blank to keep it</small><?php endif; ?></span>
                <input name="cfg_<?= e($fk) ?>" type="password" autocomplete="off"
                       placeholder="<?= $saved !== '' ? '••••••••••••' : '' ?>"></label>
            <?php endforeach; ?>
            <label class="field check"><input type="checkbox" name="is_test" value="1"<?= !empty($m['is_test']) ? ' checked' : '' ?>>
              <span>Sandbox / test mode</span></label>
            <p class="hint">Keys are stored in the database. Test the whole flow with a real transaction in sandbox mode before switching this off.</p>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="fieldset">
        <p class="legend">Availability</p>
        <div class="row two">
          <label class="field"><span>Sort order</span>
            <input name="sort_order" type="number" value="<?= e((string)($m['sort_order'] ?? 0)) ?>"></label>
          <label class="field check" style="align-self:end">
            <input type="checkbox" name="is_active" value="1"<?= !empty($m['is_active']) ? ' checked' : '' ?>>
            <span>Offer this at checkout</span></label>
        </div>
      </div>

      <div class="formfoot">
        <button class="btn" type="submit"><?= $action === 'edit' ? 'Save method' : 'Add method' ?></button>
        <a class="btn ghost" href="payments.php">Cancel</a>
      </div>
    </form>
    <script>
    (function () {
      var sel = document.getElementById('provider');
      var blocks = [].slice.call(document.querySelectorAll('[data-when-provider]'));
      function sync() {
        blocks.forEach(function (b) { b.hidden = b.getAttribute('data-when-provider') !== sel.value; });
      }
      sel.addEventListener('change', sync); sync();
    })();
    </script>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

$methods = Database::all('SELECT * FROM payment_methods ORDER BY sort_order, id');
$PAGE_TITLE = 'Payment methods';
$AREA = 'admin';
$PAGE_ACTIONS = '<a class="btn sm" href="payments.php?action=new">Add method</a>';
require __DIR__ . '/../partials/app_header.php';
?>
<?php if (!$methods): ?>
  <div class="alert warn"><p><b>No payment methods yet.</b> Until you add one, buyers can still place an order —
    they simply will not be told how to pay. Add a bank transfer to start.</p></div>
<?php endif; ?>

<section class="card">
  <?php if (!$methods): ?>
    <div class="empty"><b>Nothing set up</b><p>Offer a bank transfer, a wallet, or a card gateway.</p>
      <a class="btn sm" href="payments.php?action=new">Add method</a></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Method</th><th>Type</th><th>Ready</th><th>Status</th><th class="right">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($methods as $m):
        $ok = Payments::isConfigured($m);
        $gw = Payments::isGateway((string)$m['provider']); ?>
        <tr>
          <td><span class="t-main"><?= e($m['name']) ?></span>
              <span class="t-sub"><?= e(excerpt($m['summary'] ?: $m['instructions'], 78) ?: '—') ?></span></td>
          <td><span class="badge <?= $gw ? 'info' : 'muted' ?>"><?= e(Payments::PROVIDERS[$m['provider']]['name'] ?? $m['provider']) ?></span>
              <?php if ($gw && (int)$m['is_test'] === 1): ?><span class="badge warn">Test</span><?php endif; ?></td>
          <td><?php if (!$gw): ?><span class="badge ok">Confirmed by hand</span>
              <?php elseif ($ok): ?><span class="badge ok">Keys set</span>
              <?php else: ?><span class="badge danger">Keys missing</span><?php endif; ?></td>
          <td><form method="post" action="payments.php?action=toggle&id=<?= (int)$m['id'] ?>"><?= Csrf::field() ?>
            <button class="badge <?= $m['is_active'] ? 'ok' : 'muted' ?>" type="submit" style="cursor:pointer">
              <?= $m['is_active'] ? 'Offered' : 'Hidden' ?></button></form></td>
          <td><div class="acts">
            <a class="btn ghost sm" href="payments.php?action=edit&id=<?= (int)$m['id'] ?>">Edit</a>
            <form method="post" action="payments.php?action=delete&id=<?= (int)$m['id'] ?>"
                  data-confirm="Delete “<?= e($m['name']) ?>”? Methods already used on an order are switched off instead.">
              <?= Csrf::field() ?><button class="btn danger sm" type="submit">Delete</button></form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="card__head"><h2>How payment works here</h2></div>
  <div class="card__body">
    <ul class="ticks">
      <li><b>Manual methods</b> show your instructions and a reference. You mark the order paid in Orders once the money lands.</li>
      <li><b>Gateways</b> send the buyer to eSewa, Khalti or Stripe. On return we re-check the result against that provider's own API — a browser redirect alone never marks an order paid.</li>
      <li>Every attempt is recorded, so an abandoned or failed payment is still visible on the order.</li>
    </ul>
  </div>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
