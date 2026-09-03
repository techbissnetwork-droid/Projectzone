<?php
/** @var App\Core\View $view */
$view->extends('layouts.app');
$view->start('content');
$matrix = [
    'Production sites' => ['1', 'Up to 5', 'Unlimited internal'],
    'Client / agency work' => ['No', 'Yes', 'Yes'],
    'Full source code' => ['Yes', 'Yes', 'Yes'],
    'Advanced Installer' => ['Yes', 'Yes', 'Yes'],
    'Figma design system' => ['Yes', 'Yes', 'Yes'],
    'Updates included' => ['12 months', '12 months', '24 months'],
    'Support channel' => ['Email', 'Priority email', 'Named engineer'],
    'Response target' => ['3 business days', '1 business day', '4 business hours'],
    'Source escrow' => ['No', 'No', 'Yes'],
    'Redistribution / resale' => ['No', 'No', 'No'],
];
?>
<section class="hero">
  <div class="aura"></div>
  <div class="container container--wide">
    <div class="hero__inner">
      <?php $view->partial('partials.crumbs', ['crumbs' => ['Marketplace' => '/marketplace', 'Licensing' => '/marketplace/licensing']]); ?>
      <div style="max-width:56ch">
        <span class="eyebrow" data-reveal>Licensing</span>
        <h1 class="h1 hero__title" data-reveal="60">Three tiers, written in plain language.</h1>
        <p class="lede hero__lede" data-reveal="120">
          A licence is per product, not per seat, and never expires. What the
          tier changes is how many sites you may deploy, whether you may build
          for clients, and how quickly we answer.
        </p>
      </div>
    </div>
  </div>
</section>

<section class="section section--flush-top">
  <div class="container container--wide">
    <div class="panel" data-reveal>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>What you get</th>
              <?php foreach (App\Models\Product::TIERS as $tier): ?>
                <th><?= e($tier['label']) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($matrix as $feature => $values): ?>
              <tr>
                <td data-label="Feature"><strong><?= e($feature) ?></strong></td>
                <?php foreach ($values as $i => $value): ?>
                  <td data-label="<?= e(array_values(App\Models\Product::TIERS)[$i]['label']) ?>">
                    <?php if ($value === 'Yes'): ?>
                      <span style="color:var(--ok)"><?= icon('check', ['size' => 15]) ?></span>
                    <?php elseif ($value === 'No'): ?>
                      <span class="dim"><?= icon('x', ['size' => 15]) ?></span>
                    <?php else: ?>
                      <?= e($value) ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="cols-2 mt-7">
      <div class="card" data-reveal>
        <h2 class="h4">What every licence permits</h2>
        <ul class="feature__list mt-4" style="padding-top:0">
          <?php foreach ([
            'Modify the source however you like, including removing our branding',
            'Deploy to staging and development environments at no extra cost',
            'Use the design system on other projects within your organisation',
            'Keep using the version you bought forever, even after support ends',
          ] as $line): ?>
            <li><?= icon('check') ?><span><?= e($line) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="card" data-reveal="60">
        <h2 class="h4">What no licence permits</h2>
        <ul class="feature__list mt-4" style="padding-top:0">
          <?php foreach ([
            'Reselling, sub-licensing or redistributing the source code',
            'Publishing the product as a template or theme elsewhere',
            'Using it to build a competing marketplace product',
            'Sharing your licence key outside your organisation',
          ] as $line): ?>
            <li><span style="color:var(--bad)"><?= icon('x', ['size' => 15]) ?></span><span><?= e($line) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <div class="accordion mt-7" data-reveal>
      <?php foreach ([
        ['Can I upgrade a licence later?', 'Yes. Upgrading costs the difference between the two tiers at current prices, and your original purchase date and support window carry across.'],
        ['What counts as a production site?', 'One deployment serving real users on one primary domain. Staging, development and preview environments for that same site do not count separately.'],
        ['What happens when support expires?', 'Nothing stops working. You keep the version you have and may continue using it indefinitely; you simply stop receiving new releases and support responses until you renew.'],
        ['Do you offer refunds?', 'Within 14 days, where the product has not been deployed and the licence key has not been activated. Because we ship source code, we cannot refund after activation.'],
        ['Can I get the source in escrow?', 'Enterprise licences include source escrow with a third-party agent, released on defined trigger events. The agreement is signed separately.'],
      ] as $i => $faq): ?>
        <details<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= e($faq[0]) ?></summary>
          <div class="accordion__body"><?= e($faq[1]) ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php $view->partial('partials.cta-band', [
  'title' => 'Still not sure which tier?',
  'body' => 'Tell us how many sites and whether it is client work. We will tell you which licence covers it — and if none of them do, we will write one that does.',
  'primary' => ['label' => 'Ask about licensing', 'path' => '/contact?topic=marketplace'],
  'secondary' => ['label' => 'Browse products', 'path' => '/marketplace'],
]); ?>
<?php $view->stop(); ?>
