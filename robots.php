<?php
declare(strict_types=1);

/**
 * robots.txt, generated - because one line in it cannot be written by hand.
 *
 * `Sitemap:` MUST be an absolute URL. The spec says so and Google's parser
 * enforces it: the static file said `Sitemap: /sitemap.xml`, which is not a
 * URL, so the line was skipped and the site had - as far as any crawler could
 * tell - no sitemap at all. Nothing in the report says "your sitemap line is
 * invalid"; the pages simply go undiscovered, which is the worst shape a bug
 * can take.
 *
 * Everything else is identical to the file it replaces, and robots.txt is
 * still there as a fallback for a host with no rewriting - a relative Sitemap
 * line is ignored, but the Disallow rules in it are still obeyed, so the
 * fallback is degraded rather than broken.
 */
require __DIR__ . '/src/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

// Same base as the canonical tags and the sitemap - a Sitemap: line
// pointing at a host the site does not consider canonical sends the
// crawler to the copy, not the original.
$base = \SignalMasterAi\View::indexBase();
?>
# SignalMasterAi
#
# Two rules of thumb decide everything below.
#
# 1. Disallow means "do not READ this", not "do not index this". A page you
#    Disallow can still appear in results as a bare URL with no title, and -
#    worse - a crawler forbidden from reading it can never see the noindex tag
#    that would have removed it properly. So the member-facing pages are NOT
#    disallowed here; they carry noindex themselves, which is the tag that
#    actually works, and a crawler has to be allowed in to find it.
#
# 2. Only endpoints that are pointless to crawl are blocked: the admin panel,
#    the data directory, the JSON API and the two things that are triggered
#    rather than read.

User-agent: *
Allow: /
Disallow: /admin/
Disallow: /data/
Disallow: /api.php
Disallow: /cron.php
Disallow: /payment_webhook.php
Disallow: /install.php

# logo.php and pm-image.php serve IMAGES, and they are referenced by the icon
# tags and the structured data on every page. Blocking a resource a page
# depends on does not hide it - the crawler finds the reference anyway and
# files it under "Indexed, though blocked by robots.txt", while losing the
# ability to see what it actually is. They are allowed, and they are not
# pages, so nothing indexes them as one.
Allow: /logo.php
Allow: /pm-image.php

# Absolute, because a relative Sitemap line is silently ignored.
Sitemap: <?= $base ?>/sitemap.xml
Sitemap: <?= $base ?>/sitemap.php
