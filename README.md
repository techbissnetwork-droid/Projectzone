# TECHBISS Platform

A launch-ready, multi-page digital transformation platform: marketing site,
premium marketplace, an advanced installer with migration tooling, and three
separate authenticated portals — admin, staff and client.

Server-rendered PHP with **no external dependencies**. There is no build step,
no `node_modules`, no Composer install. Copy the files to any PHP 8.1+ host and
open `/install`.

---

## Why it is built this way

The brief asked for a real multi-page platform that is fast on slow mobile
networks. Server-rendered HTML with an inlined critical stylesheet gets the
first paint out in a single round trip, and every interactive behaviour is a
progressive enhancement layered on markup that already works without it.

The measured result, enforced by `tests/performance.php`:

| Metric | Budget | Actual |
| --- | --- | --- |
| Heaviest page, gzipped HTML | 40 KB | **21.7 KB** |
| Critical CSS, inlined and gzipped | 8 KB | **5.8 KB** |
| Deferred stylesheet, gzipped | 14 KB | **11.6 KB** |
| Motion layer, gzipped | 4 KB | **2.8 KB** |
| JavaScript, deferred and gzipped | 8 KB | **5.5 KB** |
| Self-hosted fonts, both faces | 60 KB | **48.9 KB** |
| Render-blocking stylesheets | 0 | **0** |
| Render-blocking scripts | 0 | **0** |
| Third-party resource origins | 0 | **0** |

Marketplace thumbnails and case-study artwork are generated as deterministic
inline SVG seeded from each record's slug. That removes the largest image
payload a catalogue normally carries, stays sharp at any density, and adapts to
the active theme.

## Typography

Two self-hosted variable faces, latin subset, served from this origin so there
is no third-party font request and no external connection on the critical path:

- **Sora** — display and headings. Geometric and slightly technical, which is
  the register the positioning asks for.
- **Manrope** — body, UI and controls. Clean and warm at small sizes, where
  most of the platform actually lives.

Both are preloaded and set with `font-display: swap`, so text paints
immediately in the fallback stack and re-renders once the face lands. Neither
ever leaves text invisible.

## Motion

Motion is a layer, not a dependency. `public/assets/css/motion.css` is 2.8 KB
gzipped and every animation runs on `transform`, `opacity` or `filter` only —
asserted by the performance suite, which parses both stylesheets and fails the
build on anything that would force layout or paint.

- **Page load is choreographed**, not scattered: the header settles, the
  headline wipes up line by line, then the supporting art and rail arrive.
- **Scroll-driven effects use native scroll timelines** (`animation-timeline:
  view()`), which run off the main thread entirely. Browsers without them fall
  back to an IntersectionObserver class.
- **Cross-document view transitions** turn navigation into a crossfade where
  supported, with the header and logo persisting across the change.
- **Pointer effects** — cursor-lit card edges, 3D tilt, magnetic buttons — are
  gated behind `(hover: hover) and (pointer: fine)` and share a single
  requestAnimationFrame loop rather than one per element.
- **Touch gets its own treatment**, not a disabled one: the same emphasis
  arrives on press.
- **`prefers-reduced-motion` stands the whole layer down**, including the
  scroll rail and view transitions.

---

## Quick start

```bash
# 1. Serve it
php bin/techbiss serve            # http://localhost:8000

# 2. Install it (or open /install in a browser for the guided wizard)
php bin/techbiss install \
  --email=you@example.com \
  --password='ChooseSomethingStrong!' \
  --name='Your Name'

# 3. Verify
./tests/run.sh
```

The default database is SQLite at `storage/db/techbiss.sqlite` — no
configuration required. MySQL and MariaDB are equally supported.

### Demo accounts

Created when demo data is enabled during installation.

| Portal | Email | Password |
| --- | --- | --- |
| Admin | the address you installed with | the password you chose |
| Staff | `engineer@techbiss.com` | `StaffDemo!2026` |
| Client | `client@northwind.example` | `ClientDemo!2026` |

Change or remove the demo accounts before going live.

---

## What is in it

### Public site
Home, Services, Solutions (plus six industry pages), Work (plus six case
studies), Process, About, Pricing, Resources (plus eight long-form articles),
Contact, and four legal documents. Each page has its own composition; all of
them share one design language.

### Marketplace
Faceted catalogue with live search, product detail with a preview gallery and
three licence tiers, cart, checkout, and an order confirmation that issues real
licence keys. Twelve seeded products.

### Advanced Installer
Eight guided steps, none of which write anything until the last:

1. **Requirements** — PHP version, extensions, PDO drivers, writable paths.
2. **Environment** — automatic URL detection through reverse proxies,
   sub-directory installs and upstream TLS termination.
3. **Database** — SQLite or MySQL, with a live connection test and error
   messages that say what to fix.
4. **Existing site** — detects WordPress, Joomla, Drupal, Laravel, Magento, a
   static site or a prior TECHBISS install, in the filesystem and the database.
5. **Migration** — imports JSON or CSV content and rewrites every absolute URL
   from the old origin to the new one.
6. **Configuration** — site name, timezone, owner account, demo data.
7. **Install** — applies the schema, seeds, writes config, locks itself.
8. **Deploy** — post-install checklist plus Apache, nginx and cron snippets.

The same engine runs headless for CI and container builds:

```bash
php bin/techbiss install --driver=mysql --host=db --database=techbiss \
  --username=techbiss --db-password=secret \
  --url=https://example.com --email=owner@example.com \
  --password='strong-password' --no-demo
```

### Portals
Three separately branded sign-in surfaces on one auth engine. A credential
valid for one portal cannot open another — verified in `tests/flows.php`.

- **Admin** — overview, users and access, catalogue, orders, pipeline,
  deployments, activity log, settings.
- **Staff** — today's work, tasks, projects with milestones, support queue with
  threaded replies, pipeline board.
- **Client** — projects and milestones, licences with keys and downloads,
  deployments with install tokens, invoices, support tickets.

---

## Responsive design

Mobile, tablet and desktop are composed separately rather than scaled:

- **Mobile** — full-screen navigation drawer with focus trapping, horizontal
  snap-scroll rails in place of card grids, a sticky bottom action bar, tables
  that become stacked cards, 44px minimum targets, and press states instead of
  hover.
- **Tablet** — two-column grids, split heroes, condensed navigation.
- **Desktop** — full grids, a mega menu on hover and focus, pointer-tracked
  sheen and 3D tilt, all gated behind `(hover: hover) and (pointer: fine)`.

Every desktop pointer interaction has a touch counterpart. Everything respects
`prefers-reduced-motion`, and animations are limited to `transform` and
`opacity` so they stay on the compositor.

---

## SEO

Per-page titles, descriptions clamped to 158 characters, canonicals, Open Graph
and Twitter cards, and a JSON-LD `@graph` carrying Organization, WebSite with
SearchAction, BreadcrumbList, Service, Product with AggregateOffer, Article,
FAQPage and HowTo nodes as each page warrants.

Generated at runtime: `/sitemap.xml`, `/robots.txt`, `/feed.xml`,
`/manifest.webmanifest` and `/health`.

Valid AMP variants are served at `/amp`, `/amp/services`, `/amp/contact`,
`/amp/resources/{slug}` and `/amp/marketplace/{slug}`, paired with their
canonical pages in both directions and validated by the performance suite.

---

## Layout

```
app/
  Core/          Container, Config, Request, Response, Router, Database,
                 Session, Csrf, Validator, Auth, View, Seo, Cache, Mailer,
                 Migrator, Seeder, Installer, Application
  Controllers/   Page, Marketplace, Contact, Resource, Seo, Installer,
                 Auth/, Admin/, Staff/, Client/
  Models/        Product, CaseStudy, Resource
  Support/       autoload, helpers, Icon (inline SVG set), Art (generated SVG)
  Views/         layouts/, partials/, pages/, marketplace/, auth/, admin/,
                 staff/, client/, install/, amp/, emails/, errors/
config/          app, database, session, cache, mail, navigation, site,
                 solutions, legal
database/        schema.php, seeds/
public/          index.php, .htaccess, assets/{css,js,fonts,img}
deploy/          nginx.conf, Dockerfile, docker-compose.yml, supervisord.conf
tests/           run.sh, smoke.php, flows.php, performance.php
build/           export-preview.php
bin/techbiss     CLI
```

---

## Commands

```bash
php bin/techbiss install       # headless Advanced Installer
php bin/techbiss migrate       # apply the schema
php bin/techbiss seed          # populate catalogue and demo data
php bin/techbiss cache:clear   # flush the filesystem cache
php bin/techbiss health        # platform and database state
php bin/techbiss routes        # list registered GET routes
php bin/techbiss serve         # development server
```

## Tests

```bash
./tests/run.sh              # everything
php tests/smoke.php         # 38 routes: status, markers, HTML health
php tests/flows.php         # installer, portals, purchase, contact, CSRF
php tests/performance.php   # byte budgets, critical path, SEO, AMP validity
```

`tests/flows.php` drives real POST requests: it runs the installer end to end
against a sandbox database — including a content import that must rewrite
absolute URLs — then restores the development installation.

`tests/performance.php` also audits the motion layer: it extracts every
`@keyframes` body and `transition` declaration across all three stylesheets and
fails if anything animates a property that forces layout or paint.

---

## Deployment

Point the document root at `public/`. The repository root carries a deny-all
`.htaccess` so a misconfigured root still cannot serve application source.

- **Apache** — `public/.htaccess` handles rewriting, HTTPS, security headers,
  compression and immutable asset caching. Needs `AllowOverride All`.
- **nginx** — copy `deploy/nginx.conf`, set the server name and FPM socket.
- **Docker** — `docker compose -f deploy/docker-compose.yml up --build`
  brings up the app with MariaDB on port 8080.

After installing, confirm `storage/install.lock` exists and point monitoring at
`/health`. Re-running the installer requires deliberately creating
`storage/install.unlock`.

### Security

Prepared statements everywhere, contextual output escaping, per-session CSRF
tokens compared in constant time, `password_hash` with automatic rehashing,
sign-in throttling per address and per account, session rotation on privilege
change, and portal isolation enforced server-side on every request.

---

## Design preview

`php build/export-preview.php` renders the live platform into a single
self-contained HTML file — every stylesheet, script and image inlined, internal
links rewritten to hash routes — for sharing the design without a server.
