<?php
/**
 * Valid AMP layout.
 *
 * AMP forbids author JavaScript and external stylesheets, and caps custom CSS
 * at 75 KB. This layout carries a hand-written subset of the design language
 * (about 4 KB) so AMP pages remain recognisably TECHBISS.
 *
 * @var App\Core\Seo $seo @var App\Core\View $view
 */
$jsonLd = $seo->jsonLd();
?>
<!DOCTYPE html>
<html ⚡ lang="<?= e(config('app.locale', 'en')) ?>">
<head>
<meta charset="utf-8">
<script async src="https://cdn.ampproject.org/v0.js"></script>
<title><?= e($seo->title()) ?></title>
<link rel="canonical" href="<?= e($seo->canonical()) ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?= e($seo->description()) ?>">
<meta name="robots" content="<?= e($seo->robots()) ?>">
<meta name="theme-color" content="#06080d">
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<?php if ($jsonLd !== ''): ?>
<script type="application/ld+json"><?= $jsonLd ?></script>
<?php endif; ?>
<style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
<style amp-custom>
:root{--bg:#06080d;--surface:#10151f;--surface2:#161d29;--line:rgba(255,255,255,.09);--ink:#f1f4fa;--ink2:#a6b0c2;--ink3:#6e7889;--accent:#3d7bff;--accent2:#22d3ee}
*,*::before,*::after{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.62;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
h1,h2,h3{margin:0;font-weight:640;line-height:1.2;letter-spacing:-.02em}
p{margin:0}
ul{margin:0;padding:0;list-style:none}
.wrap{width:100%;max-width:760px;margin-inline:auto;padding-inline:20px}
.top{border-bottom:1px solid var(--line);padding:14px 0;position:sticky;top:0;background:rgba(6,8,13,.92);z-index:9}
.top .wrap{display:flex;align-items:center;justify-content:space-between;gap:12px}
.logo{display:flex;align-items:center;gap:9px;font-weight:700;letter-spacing:-.03em;font-size:17px}
.mark{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#3d7bff,#22d3ee)}
.nav{display:flex;gap:14px;font-size:13px;color:var(--ink2);flex-wrap:wrap}
.hero{padding:44px 0 34px;background:radial-gradient(40rem 22rem at 20% 0%,rgba(61,123,255,.20),transparent 62%)}
.eyebrow{display:block;font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--accent2);margin-bottom:12px}
h1{font-size:30px;letter-spacing:-.028em;line-height:1.1}
.lede{color:var(--ink2);font-size:17px;margin-top:14px}
.btn{display:inline-block;padding:12px 20px;border-radius:10px;font-size:15px;font-weight:600;border:1px solid var(--line);margin:18px 8px 0 0}
.btn-primary{background:linear-gradient(135deg,#3d7bff,#22d3ee);color:#fff;border-color:transparent}
.sec{padding:34px 0;border-top:1px solid var(--line)}
h2{font-size:21px;margin-bottom:16px}
h3{font-size:16px}
.card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:12px}
.card p{color:var(--ink2);font-size:14px;margin-top:7px}
.meta{font-size:12px;color:var(--ink3);margin-top:12px}
.stats{display:grid;grid-template-columns:repeat(2,1fr);gap:1px;background:var(--line);border:1px solid var(--line);border-radius:16px;overflow:hidden;margin-top:22px}
.stat{background:var(--surface);padding:16px}
.stat b{display:block;font-size:23px;font-weight:660;letter-spacing:-.028em}
.stat span{display:block;font-size:12px;color:var(--ink3);margin-top:3px}
.tag{display:inline-block;font-size:11px;font-weight:600;padding:3px 9px;border-radius:999px;background:rgba(61,123,255,.13);border:1px solid rgba(61,123,255,.34);color:var(--accent2);margin:0 5px 5px 0}
.price{font-size:19px;font-weight:660;letter-spacing:-.02em}
.prose{color:var(--ink2);font-size:16px;line-height:1.75}
.prose h2{color:var(--ink);font-size:19px;margin:28px 0 10px}
.prose p{margin-bottom:14px}
.prose ul li{margin-bottom:9px;padding-left:16px;position:relative}
.prose ul li::before{content:"";position:absolute;left:0;top:10px;width:5px;height:5px;border-radius:50%;background:var(--accent)}
.prose code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:13px;background:var(--surface2);padding:2px 5px;border-radius:5px;color:var(--accent2)}
.prose ol{padding-left:20px;margin-bottom:14px}
.prose ol li{margin-bottom:8px;list-style:decimal}
.byline{display:flex;align-items:center;gap:10px;margin-top:18px;font-size:13px;color:var(--ink3)}
.avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#3d7bff,#22d3ee);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:660;color:#fff}
.field{display:block;margin-bottom:14px}
.field span{display:block;font-size:13px;font-weight:560;color:var(--ink2);margin-bottom:6px}
.input,.textarea{width:100%;padding:12px 14px;min-height:46px;background:var(--surface);border:1px solid rgba(255,255,255,.14);border-radius:10px;color:var(--ink);font-size:15px;font-family:inherit}
.textarea{min-height:120px}
.foot{border-top:1px solid var(--line);padding:26px 0 36px;color:var(--ink3);font-size:13px}
.foot a{color:var(--ink2);margin-right:14px;display:inline-block;margin-bottom:8px}
.full{display:block;text-align:center;margin-top:20px}
@media (min-width:640px){h1{font-size:38px}.stats{grid-template-columns:repeat(4,1fr)}}
</style>
</head>
<body>
<header class="top">
  <div class="wrap">
    <a class="logo" href="<?= e(url('/')) ?>"><span class="mark"></span>TECHBISS</a>
    <nav class="nav">
      <a href="<?= e(url('/services')) ?>">Services</a>
      <a href="<?= e(url('/marketplace')) ?>">Marketplace</a>
      <a href="<?= e(url('/work')) ?>">Work</a>
      <a href="<?= e(url('/contact')) ?>">Contact</a>
    </nav>
  </div>
</header>

<?= $view->section('content') ?>

<footer class="foot">
  <div class="wrap">
    <div>
      <a href="<?= e(url('/services')) ?>">Services</a>
      <a href="<?= e(url('/solutions')) ?>">Solutions</a>
      <a href="<?= e(url('/marketplace')) ?>">Marketplace</a>
      <a href="<?= e(url('/work')) ?>">Work</a>
      <a href="<?= e(url('/about')) ?>">About</a>
      <a href="<?= e(url('/legal/privacy')) ?>">Privacy</a>
    </div>
    <p style="margin-top:14px">© <?= date('Y') ?> <?= e(config('app.legal_name', 'TECHBISS')) ?>. Viewing the AMP edition — <a href="<?= e($seo->canonical()) ?>" style="color:var(--accent2)">open the full site</a>.</p>
  </div>
</footer>
</body>
</html>
