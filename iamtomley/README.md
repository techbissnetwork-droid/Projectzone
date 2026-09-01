# iamtomley — Premium 2026 portfolio (PHP + Admin panel)

A premium dark / glassmorphism developer portfolio with an editable admin panel.
The public site is fully content-managed; the design is never touched by the admin.

## What's here

```
index.php                 Public site (renders from the database)
config.php                Configuration (storage driver, base URL)
includes/                 db.php · functions.php · auth.php   (app internals — web-protected)
admin/                    Admin panel (login, dashboard, settings, projects, stats, games, account)
assets/css/styles.css     Public design system  (do NOT edit from admin — this is the look)
assets/js/app.js          Public interactions
assets/js/games-data.js   The 20 self-contained HTML5 games (code, mapped by ID)
favicon/ · bg.jpg         Static assets
data/                     SQLite database (auto-created, git-ignored)
uploads/                  Uploaded media (git-ignored)
```

## Requirements

- PHP 8.0+ with `pdo_sqlite` (default) — or `pdo_mysql` if you prefer MySQL.
- Any web server (Apache/Nginx) or `php -S` for local dev.

## Run locally

```bash
php -S localhost:8000
# visit http://localhost:8000/        (public site)
# visit http://localhost:8000/admin/  (admin panel)
```

The database is created and seeded automatically on first load.

## Admin panel

- URL: `/admin/`
- Default login: **admin / admin** — you'll be prompted to change it on first sign-in
  (Account page). Please change it before going live.

You can edit from the admin: brand & default theme, the entire hero (text, buttons,
status badge, photo upload), the project cards & their tags, the four stat widgets,
the games list (rename / recategorise / reorder / show-hide), the contact block,
footer and SEO meta. The 20 playable games are self-contained and fixed by ID —
adding a brand-new *playable* game means adding its code to `assets/js/games-data.js`.

## Storage

Defaults to zero-config **SQLite** at `data/app.db`. To use **MySQL**, set the driver
and credentials in `config.php` (or via environment variables):

```php
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'iamtomley');
define('DB_USER', '...');
define('DB_PASS', '...');
```

Tables and seed data are created automatically for either driver.

## Security & hardening

- **Not-set-up state** shows a styled "under construction" page (HTTP 503); the
  installer is never auto-linked publicly.
- **Maintenance mode** (Admin → Site Settings) shows a "be right back" page to
  visitors while signed-in admins can still preview the site.
- **Security headers** on every response: Content-Security-Policy (tuned so the
  self-contained games still run), X-Frame-Options, X-Content-Type-Options,
  Referrer-Policy, Permissions-Policy, and HSTS over HTTPS.
- **Brute-force protection**: admin logins are rate-limited per IP (locks after
  6 failures for ~15 min). CSRF tokens protect every form, including the installer.
- **No source download / no directory listing**: `.htaccess` disables indexes and
  blocks `data/`, `includes/`, `config*.php`, databases, backups, dotfiles, etc.;
  scripts can't execute from `uploads/`. (On Nginx, replicate these denies in your
  server block.)
- **Auto-detected site URL**: canonical/OG tags use the current host automatically
  (override in Site Settings if needed).

## Clean URLs

With Apache + mod_rewrite, `.php` is hidden automatically (e.g. `/admin/login`),
and any `…/x.php` GET is 301-redirected to the clean URL. The app still works
without mod_rewrite (URLs just keep `.php`).

## Deployment notes

- Ensure `data/` and `uploads/` are writable by the web server.
- On Apache, the included `.htaccess` files protect `data/`, `includes/` and
  `config.php`, and block script execution in `uploads/`. On Nginx, deny access to
  those paths in your server block.
- Works at the web root or in a subdirectory (base URL is auto-detected).
