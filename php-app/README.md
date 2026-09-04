# TECHBISS — PHP + MySQL edition

This is the real, deployable version of the Bloom concept site: PHP on the
front end and API, MySQL/MariaDB for data (manage it with phpMyAdmin), and
a genuinely access-controlled staff admin panel.

## What's real here (and what isn't yet)

**Real, working, backed by the database:**
- The public marketing site (all pages) — served by `index.php`, styled by
  `assets/style.css`, driven by `assets/app.js`.
- Marketplace products — pulled live from the `products` table.
- The contact form — saves every submission to `contact_messages`.
- The Resources page newsletter signup — saves to `newsletter_subscribers`
  (previously faked a "you're on the list" success without saving anything).
- Customer sign up / log in / log out — real accounts in `customers`,
  hashed passwords (`password_hash`/`password_verify`), PHP sessions.
- `/dashboard` (hash route `#/dashboard`) requires a signed-in customer —
  it redirects to `#/login` otherwise. The dashboard content itself is
  still the same illustrative "preview" shown to every signed-in customer
  (it isn't wired to per-business real data yet — see "Natural next
  steps" below).
- The **staff admin panel** at `/admin/` is a completely separate app from
  the public SPA, with its own header (logo, section nav, signed-in-as,
  log out) and its own bottom nav on mobile — not linked from the public
  site; you reach it by typing the URL. It requires a real staff login
  (session-gated) and is full read/write, not just a dashboard:
  - **Businesses** — add, edit, delete client accounts (plan, MRR, status).
  - **Tickets** — create, edit, reassign, change status/priority, delete.
  - **Products** — add, edit, delete marketplace listings; changes go
    live on the public marketplace immediately.
  - **Staff** — add, edit, delete staff accounts (a delete is blocked if
    it would remove the last remaining account, so you can't lock
    yourself out).
  - **Settings** — edit the homepage headline/subheading, footer
    tagline, contact email/phone, SEO title/meta description, the
    social-share image, the logo, the favicon, and the default light/dark
    theme for new visitors — all live immediately with no redeploy. See
    "Site settings" below.
- **A real installer at `/install/`** — auto-detects your site's URL,
  checks PHP/MySQL requirements, writes `config.php` for you after testing
  the database connection, and lets you set your own admin password
  (nothing ships with a hardcoded default). It also tells the difference
  between a fresh install and an already-installed site: visiting
  `/install/` again later only offers to run any new pending updates —
  it will never re-run setup, overwrite `config.php`, or let anyone
  recreate an admin account once the site is installed. See "Installing"
  below.

**Not implemented (flagged, not silently faked):**
- No payment processor — the marketplace "buy" flow does not charge
  anyone, for either monthly or one-time-priced products (see Products
  in the admin panel — each product has a Monthly/Fixed pricing type,
  which only controls the "/mo" label, not actual billing). Wire
  Stripe/PayPal into a new `api/checkout.php` before accepting real orders.
- No outbound email — the contact form saves to the database but does not
  send a notification email. Add `mail()`/PHPMailer/a transactional email
  API in `api/contact.php` if you want that.
- The "Continue with SSO" button on the login page is a disabled
  "coming soon" placeholder (grayed out, can't be clicked) rather than a
  live no-op — wire real OAuth into it once you have a provider set up.
- The customer dashboard shows the same illustrative content to every
  customer rather than that customer's real projects — see below.

## Requirements

- PHP 8.0+ with the `pdo_mysql` extension (check with `php -m | grep pdo_mysql`)
- MySQL 5.7+ or MariaDB 10.4+
- Any standard host that gives you PHP + MySQL + phpMyAdmin (shared
  hosting, a VPS, etc.)

## Installing (recommended: the web installer)

1. **Create an empty database** in phpMyAdmin (or your host's control
   panel). Note the database name, a database username, and its password.
   Don't import anything into it yet — the installer does that.
2. **Upload everything** in this folder to your host, keeping the folder
   structure intact (`index.php` at the site root, `admin/`, `api/`,
   `assets/`, `includes/`, `install/` alongside it).
3. **Visit `https://yourdomain.com/install/`** in a browser. It will:
   - Check PHP version and the `pdo_mysql` extension are present.
   - Auto-detect your site's URL (you can edit it if it guessed wrong —
     useful if you installed into a subfolder).
   - Ask for your database host/name/username/password, test the
     connection, and write `config.php` for you.
   - Ask you to create your own admin account (name, email, password) —
     this becomes your real `/admin/` login. A few illustrative
     teammates (Devon, Rhea, Jonah) are added alongside you sharing that
     same password, purely so the admin panel isn't empty on day one;
     rename, repurpose or delete them once you're in.
4. Once it says "You're live", the public site and `/admin/` both work
   immediately. You can delete the `install/` folder for tidiness — it
   isn't required for security, since the installer permanently refuses
   to reconfigure the database or create another admin account once a
   site is installed (see "How the installer protects itself" below).

**Re-running `/install/` later** (e.g. after uploading a future update
that adds a new `install/migrations/*.php` file) detects it's already
installed and only offers to run the new pending update(s) — it never
touches your existing data or asks for database credentials again.

### How the installer protects itself

The moment install finishes, it writes `install.lock`. From then on,
`/install/`:
- Will never show the database-credentials form again (so a leftover
  `install/` folder can't be used to repoint a live site at a different
  database).
- Will never show the "create admin account" form again (so it can't be
  used to mint a new admin login on a site that already has one).
- Only remaining action is "run pending migrations" — safe, additive,
  and using the same `CREATE TABLE IF NOT EXISTS` / count-before-seed
  pattern as the initial install, so it never drops or duplicates data.

## Manual setup (alternative, if you'd rather not use the web installer)

1. Create a database and import `schema.sql` via phpMyAdmin's Import tab
   — this creates every table and seeds sample data directly.
2. Copy `config.sample.php` to `config.php` and fill in your database
   host/name/username/password (and `SITE_URL`).
3. Manually create an empty `install.lock` file next to `config.php` (any
   content, e.g. just today's date) — this tells the app setup is
   complete, matching what the web installer would have written.
4. Upload everything to your host as in step 2 above.

With this path, the seed staff logins all share one starter password:

| Email | Role |
|---|---|
| mara@techbiss.com | Founder & CEO |
| devon@techbiss.com | Head of Engineering |
| rhea@techbiss.com | Head of Design |
| admin@techbiss.com | VP Client Success |

**Password for all four:** `techbiss-admin-2026` — **change this before
putting the site anywhere public.** Generate a new hash and update each
row's `password_hash` in phpMyAdmin:

```php
<?php echo password_hash('your-new-password', PASSWORD_DEFAULT);
```

(This is exactly the problem the web installer avoids — with it, you pick
your own password at setup time and no shared default ever exists.)

## Site settings

Admin > Settings edits a small `settings` key/value table, split into
three groups:

**Homepage & footer** — the homepage headline (in two parts, so the
highlighted word/phrase keeps its accent color), the homepage subheading,
the footer tagline, and the contact email/phone shown in the footer and
on the Contact page.

**SEO & social sharing** — the page title (browser tab + search results),
the meta description, and the social share image (`og:image`/
`twitter:image`, shown when the site link is pasted into Slack, iMessage,
Twitter/X, etc. — recommended 1200×630px). The site ships with a default
brand-colored share image and favicon (built from `assets/brand/*-source.svg`)
so these all look right before you ever touch Settings.

**Branding & appearance** — upload a custom logo (replaces the default
"T" mark everywhere: public header/footer and the admin header) and a
custom favicon; pick one of the 11 color themes explored during design
(Bloom, Fresh, Dusk, Ember, Sunrise, Lagoon, Orchid, Citrus, Slate Bloom,
Midnight Bloom, Noir) as the site's brand color — this applies to every
visitor site-wide (it's a business-identity choice, not a per-visitor
toggle, so there's no public picker for it); and choose the default
light/dark theme for **new** visitors (Automatic/Light/Dark — anyone who
has already toggled the theme themselves keeps their own choice; this
only sets what a first-time visitor sees). Uploaded files are saved to
`assets/uploads/` (created automatically — needs to be writable by the
web server, same requirement as `install/` needing to write
`config.php`) and are capped at 2MB; allowed types are PNG/JPG/WebP for
the logo and social image, PNG only for the favicon.

It does not (yet) cover every string on the site — full-page copy for
Services/Solutions/Pricing/etc. is still in `assets/app.js`, since making
literally every sentence on the site database-editable would mean
rebuilding the whole site as a page-builder, which was out of scope here.
Extending the `settings` table with more keys and wiring them into the
relevant `Pages['/...']` function in `assets/app.js` (same pattern as the
homepage) is the natural way to grow it.

**Trust note:** settings and product fields are staff-only input and are
rendered as-is (no HTML sanitization) on the public site, the same way a
CMS trusts logged-in editors with post content. Don't give admin accounts
to anyone you wouldn't trust to edit the site's HTML directly. Uploaded
images are validated as real images (`getimagesize()`) and capped at
2MB, but are not otherwise re-encoded — don't upload files from sources
you don't trust.

## Security notes

- Passwords are hashed with PHP's `password_hash()` (bcrypt) — never
  stored in plain text.
- All database queries use prepared statements (PDO) — no string-built
  SQL, so this isn't vulnerable to SQL injection from user input.
- Session cookies are set `HttpOnly` and `SameSite=Lax`.
- The admin login form uses a CSRF token.
- **Put this behind HTTPS in production.** Nothing here forces HTTPS —
  that's a hosting/server-config step (most hosts offer free
  Let's Encrypt certificates). Once you have HTTPS, add `'secure' => true`
  to the session cookie params in `includes/db.php`.
- The API endpoints (`api/login.php`, `api/signup.php`) sleep 200ms on a
  failed attempt to slow down brute-force guessing. For real production
  traffic, also add rate limiting at the web server or a service like
  Cloudflare in front of the site.

## Natural next steps (not built, scope was kept deliberately tight)

- Per-customer dashboard data (link a `customers` row to a `businesses`
  row, so `/dashboard` shows that specific business's real projects and
  tickets instead of the same illustrative preview for everyone).
- A real marketplace checkout (Stripe/PayPal) and an `orders` table.
- Outbound email for contact form submissions and password resets.
- A "change password" flow for both customers and staff.
- Extending Admin > Settings to cover more of the site's copy (see
  "Site settings" above) — currently the homepage headline/sub, footer
  tagline, contact details, SEO/social fields, logo/favicon and default
  theme.

## File structure

```
index.php                            Public site shell — queries products + settings, renders the SPA
assets/style.css                      All site styling, admin panel included
assets/app.js                         All public-site behavior — talks to api/*.php instead of faking submissions
api/contact.php                       Saves contact form submissions
api/newsletter.php                    Saves Resources page newsletter signups
api/signup.php                        Creates a customer account
api/login.php                         Signs a customer in
api/logout.php                        Signs a customer out
api/me.php                            Returns the signed-in customer, if any
admin/login.php                       Staff sign-in (separate from customer accounts)
admin/logout.php                      Staff sign-out
admin/index.php                       Dashboard — KPIs + recent activity, links into every section below
admin/businesses.php                  Business accounts — add/edit/delete
admin/tickets.php                     Support tickets — add/edit/reassign/delete
admin/products.php                    Marketplace products — add/edit/delete (live on the public site)
admin/staff.php                       Staff accounts — add/edit/delete (can't delete the last one)
admin/settings.php                    Homepage/footer copy, SEO/social fields, logo/favicon uploads, default theme
install/index.php                     The web installer — requirements, DB setup, admin account, migrations
install/reset.php                     Lets install/ retry a botched DB step; permanently inert once installed
install/migrations/001_initial.php    Creates the core tables, seeds sample data + your admin account
install/migrations/002_newsletter.php Adds newsletter_subscribers
install/migrations/003_settings.php   Adds the settings table, seeded with the original hardcoded copy
install/migrations/004_pricing_type.php  Adds products.pricing_type (monthly vs. one-time fixed price)
install/migrations/005_branding_seo.php  Adds SEO/social/logo/favicon/default-theme settings
install/migrations/006_color_palette.php Adds the site-wide brand color theme setting
includes/db.php                       Database connection, session setup, auth/flash/settings/logo helpers, install-guard
includes/migrate.php                  Tiny migration runner used by install/
includes/icons.php                    Icon set used by the server-rendered admin panel
includes/admin_layout.php             Shared admin header (incl. theme toggle) + bottom nav, used by every admin/*.php page
assets/brand/*-source.svg             Source vector art for the default logo/social image (not served directly)
assets/favicon.ico, apple-touch-icon.png, social-default.png  Default brand assets, generated from assets/brand/
assets/uploads/                       Staff-uploaded logo/favicon/social image land here (gitignored, created on first upload)
config.sample.php                     Template — the installer writes the real config.php for you
schema.sql                            Manual-setup alternative to the installer (see "Manual setup" above)
```
