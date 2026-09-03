<?php /** @var array $lead @var string $reference */ ?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;max-width:600px;color:#0a1220">
  <h2 style="margin:0 0 4px;font-size:18px">New enquiry — <?= e($reference) ?></h2>
  <p style="margin:0 0 20px;color:#4c5769;font-size:14px">Submitted through the TECHBISS contact form.</p>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <?php foreach ([
      'Name' => $lead['name'] ?? '',
      'Email' => $lead['email'] ?? '',
      'Company' => $lead['company'] ?? '—',
      'Phone' => $lead['phone'] ?? '—',
      'Topic' => $lead['topic'] ?? '',
      'Budget' => $lead['budget'] ?? '—',
      'Timeline' => $lead['timeline'] ?? '—',
    ] as $label => $value): ?>
      <tr>
        <th style="text-align:left;padding:8px 12px 8px 0;color:#78839a;font-weight:500;width:120px;vertical-align:top"><?= e($label) ?></th>
        <td style="padding:8px 0;border-bottom:1px solid #eaeef5"><?= e($value) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <h3 style="margin:24px 0 8px;font-size:15px">Message</h3>
  <p style="margin:0;white-space:pre-wrap;line-height:1.6;font-size:14px"><?= e($lead['message'] ?? '') ?></p>
</div>
