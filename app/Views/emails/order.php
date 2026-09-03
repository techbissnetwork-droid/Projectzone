<?php /** @var string $reference @var array $lines @var float $total @var string $name */ ?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:600px;color:#0a1220">
  <h2 style="margin:0 0 4px;font-size:18px">Order <?= e($reference) ?> confirmed</h2>
  <p style="margin:0 0 20px;color:#4c5769;font-size:14px">Thank you, <?= e($name) ?>. Your licences are ready.</p>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <?php foreach ($lines as $line): ?>
      <tr>
        <td style="padding:10px 0;border-bottom:1px solid #eaeef5">
          <strong><?= e($line['product']['name']) ?></strong><br>
          <span style="color:#78839a;font-size:12px"><?= e(ucfirst((string) $line['tier'])) ?> licence</span>
        </td>
        <td style="padding:10px 0;border-bottom:1px solid #eaeef5;text-align:right"><?= e(money($line['price'])) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr>
      <td style="padding:12px 0;font-weight:600">Total</td>
      <td style="padding:12px 0;text-align:right;font-weight:600"><?= e(money($total)) ?></td>
    </tr>
  </table>
  <p style="margin:24px 0 0;font-size:14px;line-height:1.6">
    Sign in to your client portal to retrieve licence keys, register domains and
    track deployments. Every product includes the Advanced Installer.
  </p>
</div>
