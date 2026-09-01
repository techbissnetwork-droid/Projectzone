# iamtomley — Premium 2026 portfolio (PHP + Admin panel)

A premium dark / glassmorphism developer portfolio with an editable admin panel.
The public site is fully content-managed; the design is never touched by the admin.

## What's here

```
index.php                 Public site (renders from the database)
game.php                  Serves a game whose HTML you pasted into the admin (sandboxed)
sitemap.php · robots.php  Generated sitemap.xml and robots.txt
config.php                Configuration (storage driver, base URL)
includes/                 db.php · functions.php · auth.php · imagefetch.php
                          (app internals — web-protected)
admin/                    Admin panel (login, dashboard, settings, projects, stats, games, account)
admin/detect-image.php    Finds a site's logo from its address, for the Detect buttons
assets/css/styles.css     Public design system  (do NOT edit from admin — this is the look)
assets/js/app.js          Public interactions
assets/js/games-data.js   The 20 self-contained HTML5 games (code, mapped by ID)
favicon/ · bg.jpg         Static assets
data/                     SQLite database (auto-created, git-ignored)
uploads/                  Uploaded media, incl. uploads/logos (git-ignored)
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
the games list, the contact block, footer and SEO meta.

### Project card images, found for you

### Project cards filled in from a link

Paste the project's address into **Link URL**, press **Detect**, and the site is
read for everything the card needs:

- its **picture** — the Open Graph image first, then the Twitter card image,
  then the apple-touch-icon, then the favicon. A copy is saved locally, so your
  page never depends on someone else's server and nothing is fetched again on a
  page load.
- its **name** — from `og:site_name`, falling back to the page title.
- its **description** — from `og:description` or the page's meta description.

Only empty boxes are filled: anything you have already typed is left exactly as
you wrote it, and you can overwrite any of it afterwards.

**Refresh all from the web** (the button on the Projects page) does the same for
every project at once. Each site is read in turn and the results appear in a
table — name, description and picture, all editable — with a tick box per
project and tick-all / untick-all. Nothing is written until you apply, and a
picture fetched for a row you did not keep is deleted again. Leave the image blank
on a new project and the same lookup runs by itself when you save. You can
always upload your own file or paste a path instead, and **Clear** removes it.

Only public `http`/`https` addresses are ever opened, redirects are limited, and
downloads are capped — the lookup cannot be pointed at your own server's private
network. SVGs containing scripts are refused outright.

### Everything slides

Projects, sold projects and games each slide four (or eight) to a view, with
buttons, swipe, and arrow keys that drive whichever slider you are looking at.
Each slider is as tall as the slide you are on, so a last slide holding one
card does not leave empty space below it.

Clicking anywhere on a project card opens its site; the Buy and Preview buttons
on a for-sale card keep their own behaviour.

### Sold projects

Anything marked **Sold** gets its own sliding section under the main one, four
to a slide, working exactly like the projects slider — arrow keys drive
whichever slider you are looking at. A switch in **Site Settings** decides
whether the figure each one sold for is shown publicly or kept to yourself.

### Adding your own games

The twenty shipped games are self-contained and mapped by ID, but the games list
is no longer limited to them. **Admin → Games → Add a game** takes:

| Source | What it is |
|---|---|
| **Link to a game** | Paste the address a game is played at and it opens inside your site. If that site refuses to be framed, visitors get an "open in a new tab" button instead, so the game always works. |
| **Pasted HTML game** | Paste a whole single-file HTML game. It is stored in the database and served by `game.php` inside a sandbox — it can play, draw and read the keyboard, but cannot touch the rest of your site. |
| **Built-in game** | One of the twenty in `assets/js/games-data.js`, chosen by its number (1–20). |

Each game can have a **cover image** in place of its emoji — upload one, or use
the same **Detect** button to take the cover from the game's own site. Typing a
new **category** on a game adds it as a filter button on the public page.

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
- **Outbound lookups are fenced in**: the image detector only opens public
  http/https addresses on standard ports, follows at most four redirects,
  caps every download, and re-checks safety on each hop.

## Search engines

`sitemap.xml` and `robots.txt` are generated from the database, so they are never
out of date. Submit `https://yourdomain.com/sitemap.xml` once in Google Search
Console and nothing further is needed.

- The sitemap carries a `lastmod` that moves whenever you save anything in the
  admin, and lists every project image against the page — that is how those
  images get indexed at all.
- `robots.txt` opens the public page and keeps `admin/`, `install/`, `includes/`,
  `data/` and `game.php` out of the index.
- Turn on **Discourage search engines** (Admin → Site Settings), or switch on
  maintenance mode, and both files close the site off while the page itself
  carries `noindex`.
- Projects are published as `ItemList` structured data alongside the existing
  `Person` markup.
- CSS and JavaScript URLs carry the file's timestamp, so a returning visitor
  never gets a stale cached copy after you change something.

Both files are served through the `.htaccess` rewrite. Without `mod_rewrite`
they are still reachable at `/sitemap.php` and `/robots.php` — on Nginx, map
`/sitemap.xml` and `/robots.txt` to those two scripts.

## Clean URLs

With Apache + mod_rewrite, `.php` never appears: the site writes its own links
without it, so there is no redirect hop and nothing shows in the address bar.

It works this out rather than assuming it. The `.htaccess` redirects a `.php`
address to its clean form; when that redirected request arrives, the app can see
its address has no `.php` while the running script does, and remembers the
answer. On a host without `mod_rewrite` nothing is ever proved, links keep their
`.php`, and the site works exactly as before.

## Deployment notes

- Ensure `data/` and `uploads/` are writable by the web server.
- On Apache, the included `.htaccess` files protect `data/`, `includes/` and
  `config.php`, and block script execution in `uploads/`. On Nginx, deny access to
  those paths in your server block.
- Works at the web root or in a subdirectory (base URL is auto-detected).
