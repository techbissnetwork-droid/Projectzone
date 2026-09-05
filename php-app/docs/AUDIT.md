# TECHBISS — full project audit

Scanned: `techbissdeploy.zip` — 82 files, ~7,700 lines (PHP 8, MySQL, vanilla-JS SPA).
Date: 2026-09-05.

> **Status: most of this is now fixed in this branch.** See
> [`docs/FIXES.md`](FIXES.md) for what was changed, what was deliberately left,
> and what still needs a decision from you. The findings below are kept as the
> record of what was found and why each one mattered.

Every PHP file passes `php -l` and `assets/app.js` passes `node --check`, so nothing
below is a syntax error. These are behavioural, security and design problems found by
reading the code.

**Read this first — the four things that matter most:**

1. **Anyone can download any paid product for free** (S1) — the store takes no payment.
2. **Every contact-form enquiry is silently lost** (F1) — nothing in the app ever reads
   `contact_messages`.
3. **Your SMTP password is printed in the HTML of the Settings page** (S4).
4. **The installer creates three extra full-admin accounts sharing your password** (S5).

Severity key: **CRITICAL** = active loss or compromise today · **HIGH** = one step away ·
**MEDIUM** = real but bounded · **LOW** = polish.

---

## 1. Security

### S1 · CRITICAL — the marketplace takes no payment; every paid download is free
`api/purchase.php` · `assets/app.js:1226`

A bare `POST /api/purchase.php` with `{"product_id":"bloom-theme"}` — no session, no
card, no token — creates an order row and returns a working `download_url` in the
response body. The buy panel itself says *"Card details (demo only)"* and *"No payment
is actually processed in this concept, but your download and account are real."*

`README.md` does flag this under "Not implemented", but nothing else does: the admin
Products screen calls the attached file *"required for customers to actually receive
something after buying"*, and the public product page shows a live **"Buy for $59"**
button. The site is currently a free file host with a checkout-shaped skin.

**Fix.** Move order creation behind a real payment event — a Stripe Checkout session,
with `product_orders` inserted only from a verified `payment_intent.succeeded` webhook,
never from a browser request. Until that exists, hide the Buy tab and route the button
to Contact (the code path for that already exists — it's what products with no attached
file already do).

### S2 · CRITICAL — the order price is supplied by the client
`api/purchase.php:12,53`

```php
$total = max(0, (float)($body['total'] ?? 0));
// ...
->execute([$orderRef, $product['id'], $customerId, (int)round($total * 100), $downloadToken]);
```

`total` comes from the request body and is written straight to `price_cents`. Any
caller can record a $0 order — or a $1,000,000 one. Read the price from
`products.price` server-side and ignore the client value entirely.

### S3 · HIGH — guest checkout writes orders onto other people's accounts
`api/purchase.php:36-47`

Guest checkout looks the submitted email up in `customers` and, on a match, attaches the
order to that existing account — then emails *them* a "Thanks for your order!" message.
So an unauthenticated attacker who knows a customer's email address can fill that
customer's dashboard with orders they never placed and send them mail from your domain,
burning your sending reputation.

**Fix.** If the email already belongs to an account, don't attach — require the buyer to
sign in with the existing OTP flow first.

### S4 · HIGH — the SMTP password is stored and re-rendered in plaintext
`admin/settings.php:124` and `admin/settings.php:374`

```php
<input type="password" name="smtp_pass" value="<?= e($current['smtp_pass'] ?? '') ?>" …>
```

`type="password"` only hides it from the screen. The live mail-server credential is in
the page source of every Settings load — visible in view-source, in the browser cache,
to every staff member holding the `settings` permission, and to any future XSS. It is
also stored unencrypted in the `settings` table, which is dumped by any SQL-injection or
backup leak.

**Fix.** Never echo it back: render an empty field with a "leave blank to keep current"
hint and only write when the submitted value is non-empty. Encrypt at rest, or move SMTP
credentials into `config.php` where they're outside the database entirely.

### S5 · HIGH — the installer seeds three extra full-access admins on your password
`install/migrations/001_initial.php:80-89`

```php
$hash = $context['admin_password_hash'];
$stmt->execute([$context['admin_name'], $context['admin_email'], $hash, …]);
$stmt->execute(['Devon Kwan',    'devon@' . $context['email_domain'], $hash, …]);
$stmt->execute(['Rhea Solano',   'rhea@'  . $context['email_domain'], $hash, …]);
$stmt->execute(['Jonah Traeger', 'jonah@' . $context['email_domain'], $hash, …]);
```

These three are created with `permissions = NULL`, which `staff_permissions()`
(`includes/db.php:191`) treats as **full access to every admin section**. They share the
exact password you chose during install, they're never disabled, and rotating your own
password later leaves theirs unchanged forever. `README.md` documents the arrangement
publicly, so the account names are not a secret.

**Fix.** Don't seed staff accounts at all. If the empty-panel-on-day-one concern is
real, seed them with `permissions = '[]'` (dashboard only) and a random 32-byte password
hash nobody holds.

### S6 · HIGH — the `staff` permission is silently full admin
`admin/staff.php:36-51`

A staff member granted only the "Staff & permissions" section can:

- open their own record, tick **Full access**, and save — unlocking every other section
  (the sole guard, at line 45, just forces `staff` to stay in their own list); and
- set any other non-owner account's email *and* password, taking it over.

So `staff` is not one section among nine — it is a superuser bit.

**Fix.** Restrict `full_access` granting and other-user password changes to `is_owner`,
and block self-permission edits outright.

### S7 · HIGH — the session cookie has no `Secure` flag
`includes/db.php:11-19`

```php
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
]);
```

`httponly` and `samesite` are set; `secure` is not. Any plain-HTTP request to the domain
— a stray `http://` link, a captive portal, a mistyped URL — transmits the staff or
customer session id in the clear.

**Fix.** Add `'secure' => true` (detecting HTTPS via `$_SERVER['HTTPS']` or
`HTTP_X_FORWARDED_PROTO` if you also serve HTTP), and force HTTPS in `.htaccess`.

### S8 · HIGH — no brute-force protection on the staff login
`admin/login.php:18`

A flat `usleep(200000)` and nothing else: no attempt counter, no lockout, no IP
throttle, no CAPTCHA, no alert. That's ~5 guesses/second on one connection and unlimited
in parallel, against the account that controls the whole panel.

**Fix.** A `login_attempts` table keyed on email + IP, with exponential backoff and a
hard lock after N failures.

### S9 · MEDIUM — no security headers at all
`.htaccess`

Missing `X-Frame-Options`/`frame-ancestors` (the admin panel is clickjackable),
`X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Strict-Transport-Security`, and
any CSP. All four are a few lines in `.htaccess`; a CSP is harder because the app builds
HTML by string concatenation and uses inline `<script>` in `index.php` and
`includes/admin_layout.php`, so start with the first four.

### S10 · MEDIUM — download tokens never expire and aren't tied to anyone
`api/download.php`

The error text says *"invalid or has expired"*, but nothing expires: there is no
`expires_at`, no download counter, no revocation, no session check. A link forwarded
once, or leaked from an inbox, is a permanent anonymous download of a paid product.

**Fix.** Add `expires_at` and `download_count` to `product_orders`, cap re-downloads,
and let the dashboard mint a fresh short-lived link on demand.

### S11 · MEDIUM — file path built from a database value without confinement
`api/download.php:25`

```php
$path = __DIR__ . '/../' . $order['download_path'];
```

`download_path` is admin-written today, so this isn't exploitable now — but it is one
bug away from arbitrary file read. Add a `realpath()` check that the resolved path stays
inside `assets/uploads/products/`.

### S12 · MEDIUM — contact and newsletter endpoints are wide open
`api/contact.php`, `api/newsletter.php`

No auth, no rate limit, no honeypot, no CAPTCHA — a five-line script fills
`contact_messages` and `newsletter_subscribers` indefinitely. Add a per-IP rate limit
and a hidden honeypot field at minimum.

### S13 · MEDIUM — OTP brute force is only half-mitigated
`includes/db.php:109-148`

Each code allows 5 attempts (good), but a fresh code can be requested every 60 seconds
with no per-IP cap and no per-account failure counter, so an attacker gets a steady ~5
guesses/minute against a 6-digit space, indefinitely, and nothing ever locks or alerts.

**Fix.** Count failed verifies per account (not per code) and lock after a threshold;
rate-limit `otp-request.php` per IP as well as per customer.

### S14 · MEDIUM — `otp_codes` is never pruned
No `DELETE FROM otp_codes` exists anywhere. Used and expired rows accumulate forever, on
a table with no index beyond its primary key (see B14). Delete expired rows inside
`otp_issue()`.

### S15 · LOW — logout has no CSRF protection and accepts any method
`api/logout.php` checks nothing — no `REQUEST_METHOD` guard, no token. A third-party
page can sign your customers out. Annoying rather than dangerous, and a two-line fix.

### S16 · MEDIUM — deleting a product destroys purchase history
`install/migrations/031_marketplace_purchases.php:157`

```sql
FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
```

One click on the trash icon in admin Products wipes every order ever placed for that
product and every affected customer's download access — with no warning beyond a generic
JS `confirm()`. Change to `ON DELETE RESTRICT` and add an "unpublish" flag instead.

### S17 · MEDIUM — deleting a business cascades to all its projects and tickets
`admin/businesses.php:87`, cascades in migrations 011 and 018. Same shape as S16: no
soft delete, no undo, one `confirm()` between a mis-click and the loss of a client's
entire history.

### S18 · LOW — deleted products leave their files on disk
`admin/products.php:199-202` deletes the row but not
`assets/uploads/products/<id>.zip` or `<id>-img.png`. The next product whose name
slugifies to the same id silently inherits the orphaned files.

### S19 · MEDIUM — the SMTP client sends non-conformant messages
`includes/mailer.php:331-339`

The body is assembled with bare `\n` where RFC 5321 requires `\r\n`, and dot-stuffing
(`str_replace("\n.", "\n..")`) misses the `\r\n.` case. There's also no `Message-ID` and
no `Reply-To`. Expect mangled bodies and spam-folder placement on strict MTAs.

**Fix.** Normalise the body to CRLF and dot-stuff on that, add the missing headers — or
drop in PHPMailer and delete 90 lines of hand-rolled SMTP.

### S20 · MEDIUM — mail header/command injection via admin-set sender
`includes/mailer.php:326,332` — `smtp_from_email` and `smtp_from_name` go unvalidated
into `MAIL FROM:<…>` and the `From:` header. A newline in either is SMTP command
injection. Validate `smtp_from_email` with `FILTER_VALIDATE_EMAIL` on save and strip
CR/LF from the name.

---

## 2. Features that don't work

### F1 · CRITICAL — every contact enquiry is silently lost
`api/contact.php` writes to `contact_messages`. **Nothing anywhere reads that table** —
no admin page, no notification email, no export. Confirmed by grep: the only two
mentions in the codebase are the `INSERT` and the `CREATE TABLE`.

"Book a call" is in the site header, the footer, the hero, the pricing page and the
dashboard. Every one of those leads lands in a table no human ever opens.

This is the most expensive bug in the project. Two fixes, both small:
`send_mail()` already works (it's what sends OTP codes), so notify on submit; and add a
`admin/messages.php` inbox mirroring the existing `tickets.php` layout.

### F2 · HIGH — newsletter signups are equally invisible
`newsletter_subscribers` has the same problem: written by `api/newsletter.php`, read by
nothing. No admin list, no CSV export. Visitors get "You're on the list — welcome!" and
join a list that doesn't exist as far as your team is concerned.

### F3 · HIGH — marketplace orders are invisible to staff
`product_orders` has no admin page either. Staff cannot see what sold, to whom, or
re-issue a download link when a customer emails asking. The admin dashboard's only money
figure is "Monthly recurring revenue" from `businesses`; product sales appear nowhere.

### F4 · MEDIUM — "Show this project in the public portfolio" does nothing
`admin/projects.php:36,177` writes `projects.portfolio_visible`. No other code reads it.
The public `/work` page renders `content_case_studies`, a separate hand-typed list in
admin → Content. The checkbox is a placebo: ticking it changes nothing anywhere.

### F5 · MEDIUM — the Resources page is entirely fake
`assets/app.js:594-626`. All five article cards are hardcoded in JavaScript, their
"Read →" links go nowhere, and none of it is editable from admin → Content. The category
chips filter fictional content. For a site selling web presence, a visibly dead
Resources tab is a poor look.

### F6 · LOW — the "plan" concept is vestigial
Migration 027 removed fixed pricing plans as "misleading", but `businesses.plan`
(Starter / Growth / App + Web / Scale) is still a required dropdown in admin and shown in
the businesses table. It now maps to nothing on the public site.

### F7 · MEDIUM — admin-created project tasks never reach the customer
`admin/tickets.php` has no `project_id` field, so a ticket created in admin with type
`project_task` gets `project_id = NULL`. `api/dashboard.php:41-43` selects open tickets
`WHERE project_id = ?`, so those tickets never appear on the customer's project card.
Only customer-raised tickets link correctly. Add a project picker to the ticket form,
filtered by the selected business.

### F8 · MEDIUM — there is no 404
`assets/app.js:1300` — `parseRoute()` falls back to `Pages['/']` for anything unknown, so
`/asdf` renders the homepage with HTTP 200. That's unlimited duplicate-content URLs for
crawlers and a confusing dead end for users. Add a `/404` page, and have `index.php`
emit a real 404 status for paths that aren't routes.

### F9 · LOW — a bad product URL silently shows a different product
`assets/app.js:1178` — `wireProductDetail` falls back to `PRODUCTS[0]` when the id in
`/marketplace/detail/<id>` doesn't match anything, so a stale link shows the wrong item
with no indication.

### F10 · MEDIUM — `schema.sql` doesn't exist
The README's entire "Manual setup" path (step 1: "import `schema.sql`") is unfollowable —
the file isn't in the package. `.htaccess` even carries a rule protecting it. Either ship
the file or delete that section and the rule.

### F11 · LOW — the README describes an older version of the app
Stale claims: customers have "hashed passwords (`password_hash`/`password_verify`)" (they
switched to OTP-only in migration 019); the dashboard "shows the same illustrative content
to every customer" (it's per-business now); hash routes `#/dashboard` (clean URLs now); a
"Continue with SSO" button on the login page (no longer in `app.js`); "No outbound email"
(there's a full SMTP client in `includes/mailer.php`).

---

## 3. Logic and correctness bugs

### B1 — expiry countdowns disagree between admin and customer
`assets/app.js:755` computes `new Date("2026-01-15")`, which JavaScript parses as **UTC**
midnight, minus `new Date(new Date().toDateString())`, which is **local** midnight.
`admin/index.php:40` does the same arithmetic with PHP `strtotime` in server-local time.
In any timezone behind UTC the customer sees one fewer day remaining than staff do. Pin
both to UTC, or compute the day count server-side and send it in the API response.

### B2 — validation failures throw away everything the admin typed
Every admin page follows `flash(error)` → `header('Location: …')` → re-render an empty
form. Mistype an email on a product with a long description and the description is gone.
Repopulate the form from `$_POST` on error, or stash it in the session alongside the flash.

### B3 — the marketing daily cap is a check-then-act race
`admin/marketing.php:28-34` counts today's leads, then inserts. Two concurrent submits
both pass the check. Enforce it in the write path instead.

### B4 — duplicate marketing leads are unlimited
Nothing stops the same business name and phone being submitted repeatedly, so the daily
goal, the progress bar and the "success rate" stat are all trivially gamed. Add a
uniqueness check on normalised phone number.

### B5 — half-applied product saves
`admin/products.php:182-187`: the image upload runs and moves the file to disk *before*
the download-file error is checked. If the download errors, the DB row is never updated —
so the new image is on disk, unreferenced, and the admin sees only an error message.
Validate both uploads before moving either.

### B6 — "remove file" silently discards a simultaneous replacement
`admin/products.php:24-29`: ticking "Remove the current file" returns early, so a new file
uploaded in the same submit is thrown away without a word.

### B7 — a failed insert orphans an uploaded file
Same file: the upload is moved to `<slug>.zip` before the `INSERT`. If the insert throws,
the file stays on disk forever.

### B8 — `businesses.customer_id` has no foreign key and isn't validated
`admin/businesses.php:73` casts `$_POST['owner_id']` to int and stores it unchecked;
migration 011 added the column with no FK. A hand-crafted POST points a business at a
customer that doesn't exist.

### B9 — `JSON_CONTAINS` on a `TEXT` column
`admin/marketing.php:121`. Fine on MySQL 5.7+/MariaDB 10.2+ (the README requires 10.4+),
but it throws `ER_INVALID_JSON_TEXT` if `permissions` is ever an empty string — which
`staff_permissions()` (`includes/db.php:193`) explicitly accepts as a valid "full access"
value. One manual DB edit and the Marketing page 500s.

### B10 — `APP_DEBUG` used without a `defined()` guard
`includes/db.php:68`, inside the DB-connection error handler. A hand-written `config.php`
following the README's manual path may not define it — turning a friendly "check your
config" message into a fatal error.

### B11 — `dashEmptyState()` skips escaping
`assets/app.js:752` interpolates `title` and `body` straight into HTML while the file's
own header comment (line 15-20) says every admin-sourced field must go through `esc()`.
Every current caller passes a literal, so it isn't exploitable today — but it's the one
hole in an otherwise careful escaping discipline, and the next caller won't notice.

### B12 — `install.lock` is web-readable
`.htaccess` blocks `schema.sql`, `README.md` and dotfiles, but not `install.lock`. Minor
information leak (the exact install timestamp). Add it to the `FilesMatch`.

### B13 — no index on the OTP lookup columns
`otp_codes` has only its primary key. `otp_verify_code()` filters on
`(customer_id, purpose, used_at, expires_at)` and `otp_verify_token()` on `token_hash` —
both full scans, on a table that (per S14) is never pruned.

### B14 — `time_ago()` mishandles future timestamps
`includes/db.php:412` — a negative diff falls through to "just now" and then to negative
day counts.

---

## 4. Worth improving

**Architecture**
- `assets/app.js` is 1,480 lines in a single IIFE holding the icon set, theme, splash,
  background animation, router, thirteen page renderers and all event wiring. Split it.
- Every admin page repeats the same twelve-line `<head>`. It belongs in
  `includes/admin_layout.php` next to `admin_header()`.
- `index.php` inlines products, settings and all six content sections into every HTML
  response — tens of KB before first paint, growing with content. Move to a cached
  `/api/content.php`, or at least add ETag and gzip.

**Hardening**
- No `api/*.php` endpoint checks CSRF or `Origin`. `SameSite=Lax` blocks classic form
  CSRF, but an explicit origin check costs three lines and closes the gap.
- No structured logging. SMTP failures reach `error_log`; everything else fails silently.
- No tests, no CI, no linter — for an app handling customer accounts and file delivery.

**Product and SEO**
- No `sitemap.xml`, no `robots.txt`, despite thirteen crawlable routes.
- Google Fonts loads from a third party on every page: a render-blocking request and a
  GDPR consideration for an EU-facing business. Self-host the two families.

**Accessibility**
- Marketplace flip cards are mouse-only — no keyboard path to the back face.
- The pricing accordion toggles `aria-expanded` but never moves focus into the opened
  panel.
- `admin/index.php:71` labels the dashboard "Internal — staff access only" as a `badge`;
  fine visually, but the page has no `<h1>`-before-badge ordering for screen readers.

---

## Suggested order of work

| # | Item | Why first |
|---|---|---|
| 1 | F1 — contact form inbox + notification email | Losing leads costs money every day |
| 2 | S1, S2, S3 — checkout | Free products; client-set prices; account pollution |
| 3 | S4, S5, S6 — credentials and admin privilege | Standing compromise, no attacker needed |
| 4 | S7, S8 — session `Secure` flag, login throttling | Two small, high-value fixes |
| 5 | F3, F2 — orders and subscribers admin views | Staff can't run the business without them |
| 6 | S16, S17 — cascade deletes | One mis-click from irreversible data loss |
| 7 | F4, F5, F6, F7 — dead and disconnected features | Remove or wire up; don't ship placebos |
| 8 | Everything else | |

---
---

# Round 2 — second pass

The first pass followed the logic paths. This pass covers what it skipped:
`assets/style.css`, all 32 migrations, the installer's views, and the HTML bodies of
every admin page. It found **23 more issues, including one that outranks everything
above.**

## N1 · CRITICAL — a git deploy re-arms the installer, handing anyone a "wipe this database" button

This is the worst finding in the audit. Three facts combine:

1. `.gitignore` line 3 excludes `/install.lock`.
2. `is_installed()` (`includes/db.php:23`) returns false without that file.
3. `install/index.php` gates every destructive action on `!$lockExists` — **not** on
   whether the database already holds real data.

So any deploy that pushes from git, or re-uploads the repo, arrives with no
`install.lock`. On a live site with real customers, `/install/` then reopens to the
public:

- **Config present, tables present, lock missing** → `$view = 'existing_data'`
  (`install/index.php:275`). An anonymous visitor sees *"Option B — Fresh install (wipes
  this database)"*. The confirmation phrase it asks for is printed on the same page as
  the input's placeholder. They type `DELETE EVERYTHING`, and
  `install/index.php:200-210` runs `SET FOREIGN_KEY_CHECKS = 0` and drops every table.
  No login required at any point.
- **Config also missing** (it's gitignored too, line 2) → `$view = 'setup'`. An anonymous
  visitor points your domain at *their* database, then walks through `create_admin` and
  owns the panel.
- Either way `install/reset.php` will delete `config.php` for them — its only guard is a
  CSRF token, which any visitor mints by loading `/install/` first.

The README states that deleting `install/` "isn't required for security, since the
installer permanently refuses to reconfigure the database or create another admin
account once a site is installed." That guarantee rests entirely on a file the project's
own `.gitignore` throws away.

**Fix.** Stop deriving "installed" from a gitignored file. Treat a non-empty
`schema_migrations` (or the presence of a `staff` row) as the lock, and refuse
`fresh_install_existing`, `save_db` and `create_admin` outright whenever the connected
database already contains this app's tables — regardless of what's on disk.

## N2 · HIGH — `Host` header decides the URL in every email you send

`detect_base_url()` (`install/index.php:32`) reads `$_SERVER['HTTP_HOST']` and offers the
result as the pre-filled Site URL, which is written into `config.php` as `SITE_URL`.
`SITE_URL` then builds every OTP magic link (`api/otp-request.php:26`) and every download
URL (`api/purchase.php:55`, `api/dashboard.php:24`).

Poison the `Host` header on the request that renders the install form — or simply be the
one who runs the installer — and every future "click here to sign in" email points at a
domain you control. Validate the submitted URL, and warn loudly when it differs from the
canonical hostname.

## N3 · HIGH — "Site zoom" does nothing for desktop visitors

`ui_zoom_scale()` renders `<meta name="viewport" content="width=device-width,
initial-scale=1.30">`. **Desktop browsers ignore the viewport meta entirely** — it is a
mobile-only mechanism. So the slider is a no-op on exactly the screens where "fit more
on screen" matters most, including the admin panel.

The feature is documented three incompatible ways:

- `admin/settings.php:207` — "Shrinks or enlarges the whole site — public pages and this
  admin panel — for every visitor."
- `install/migrations/022_ui_zoom.php:5` — "Applied via a `zoom` CSS style on `<html>`."
- `includes/db.php:346-354` — explains it is deliberately *not* CSS `zoom`, and is
  `initial-scale` instead.

Only the third is true of the code. Either implement it with a root `font-size` /
`rem`-based scale that works everywhere, or scope the UI copy to mobile and fix the
migration comment.

## N4 · HIGH — the desktop navigation disappears below 1360px

`assets/style.css:236` and `:585`:

```css
@media (max-width:1360px){ .nav-links{ display:none; } #navLoginBtn{ display:none !important; } .nav-burger{ display:flex; } }
@media (max-width:1360px){ .admin-nav{ display:none; } .admin-who{ display:none; } }
@media (min-width:1361px){ .bottom-dock{ display:none; } }
```

1360px is an enormous breakpoint. A 1366×768 laptop, a 13" MacBook at 1280×800, and any
browser window that isn't maximised all fall under it — and get the phone UI: hamburger
menu, a fixed bottom dock over the content, no section nav in admin, and the signed-in-as
name hidden. In the admin panel that means three nav items plus a "Menu" sheet on a
laptop.

`.admin-page` is capped at `max-width:1300px`, so there is a 60-pixel band in which the
desktop layout is actually the intended one. That strongly suggests 1360 is a typo or a
leftover. The conventional value here is 900–1024px.

## N5 · MEDIUM — "Last activity" is really "date added", forever

`businesses.last_activity_at` is written exactly once, by the INSERT in
`admin/businesses.php:81` (`NOW()`). Nothing else ever updates it — not the UPDATE on
edit, not a new ticket, not a customer signing in. Yet `admin/businesses.php:114` sorts
the whole table by it and `:227` prints it under a **"Last activity"** column.

So a client you've worked with daily for a year shows "350 days ago", and the list is
ordered by signup date wearing an activity label. Either update the column on real events
or rename it "Added".

## N6 · MEDIUM — the admin's "view current file" link always returns 403

`admin/products.php:274` renders a link to `../assets/uploads/products/<id>.zip`. But
`assets/uploads/products/.htaccess` denies exactly those extensions:

```apache
<FilesMatch "\.(zip|pdf)$">
    Require all denied
</FilesMatch>
```

The block is correct and necessary — but it means staff can never open the file they just
uploaded to check it's the right one. Route it through a staff-authenticated download
endpoint instead.

## N7 · MEDIUM — picking an owner doesn't cancel the "new user" fields

`admin/businesses.php:210-217` hides the New-user name/email inputs with
`wrap.style.display='none'` when an existing owner is selected. **Hidden inputs still
submit.** Server-side, `$creatingNewUser = $newUserEmail !== ''` (line 50) is evaluated
*before* the owner select is consulted, so it wins.

Type a new user's email, change your mind, pick an existing owner from the dropdown, save
— and you get a duplicate customer record, assigned as the owner instead of the person
you chose. Clear the inputs on hide, or let the explicit select win.

## N8 · MEDIUM — "Send a test email" throws away everything you just typed

The test-email form (`admin/settings.php:412`) is a separate POST that redirects back to
`settings.php`. The main settings form is not submitted with it, so any SMTP credentials
typed but not yet saved are lost.

The card's own hint says "Save your SMTP settings above first" — but the natural moment to
click "Send test" is immediately after typing the credentials you want to test. Make the
test button submit the settings first, or disable it until the form is clean.

## N9 · MEDIUM — content items can't be reordered

`admin/content.php` assigns `sort_order = MAX(sort_order)+1` on insert (line 148) and
never exposes it again — there is no drag handle, no up/down control, no number field.
The public site orders every content section by `sort_order ASC`
(`includes/db.php:286-325`).

To move the third service above the second, an admin has to delete and retype everything
below it. For the panel's most-used screen, that's a significant gap.

## N10 · MEDIUM — every settings save writes all six tabs

`admin/settings.php:104-137` writes all ~50 setting keys on every submit, including the
tabs the admin never opened (the CSS-only tab panels are `display:none`, which still
submits). Two admins editing different tabs at the same time will silently overwrite each
other — last write wins across the whole table, not per field.

## N11 · LOW — the three branding uploads can exceed `post_max_size` together

Logo, favicon and social image are capped at 2MB each (`branding_upload`, line 45), but
nothing caps their sum. On a host with the common `post_max_size = 8M`, exceeding it makes
PHP discard `$_POST` entirely — so `csrf_check('')` fails and the admin sees **"Your
session expired — please try again."** with no hint that size was the problem. Check
`$_SERVER['CONTENT_LENGTH']` against `post_max_size` and say so.

## N12 · MEDIUM — every page-render error is silently swallowed

`assets/app.js:1434` and `:1439`:

```js
if(!wipeEnabled){ try{ doRender(); }catch(e){} transitioning=false; return; }
// ...
try{ doRender(); }
catch(e){}
```

Any exception thrown by any page renderer produces a blank or frozen page with **nothing
in the console**. That's not hypothetical: on an install with no products,
`Pages['/marketplace/detail']` (line 493) evaluates `PRODUCTS[0]` → `undefined` → throws
on `p.cat`, and the visitor gets a silent dead page. At minimum `console.error(e)` and
render a fallback.

## N13 · LOW — four more places skip `esc()`

Beyond `dashEmptyState()` (B11 above): `Pages['/process']` interpolates `s.t`, `s.d` and
each `s.out` raw (line 533-556); `Pages['/services']` does the same for `m[0]`/`m[1]` in
"Ways to work with us" (line 450); `Pages['/resources']` for `r.t`, `r.min`, `r.k` (line
612); and `Pages['/marketplace/detail']` prints `p.rating` unescaped at line 501 while the
marketplace grid escapes the identical value at line 992.

Every one is a literal or a numeric cast today, so none is exploitable. They're listed
because the file opens with an explicit rule (lines 15-20) that admin-sourced values must
all pass through `esc()`, and these are the exceptions someone will eventually copy.

## N14 · MEDIUM — a lot of "editable" content isn't

Admin → Content covers six sections. These are hardcoded in JavaScript and can't be
touched without a code change:

- The **Process page**'s five stages — titles, descriptions and all fifteen outcome
  badges (`app.js:533-556`).
- The Services page's **"Ways to work with us"** trio (`app.js:450`).
- The Solutions **"Pick your path"** timelines — "2–6 weeks", "2–5 days", "3–8 weeks"
  (`app.js:470-472`). The *prices* in that same table are admin-editable settings; the
  timelines next to them are not.
- The **marketplace filter chips** (`app.js:480`) are a JS array, while
  `admin/products.php:11` has its own `$CATEGORIES` array. Add a category in the admin
  list and products will appear that no chip can filter to.

## N15 · LOW — the installer checks the wrong folder for writability

`install/index.php:335` shows a green "install/ folder writable" row from
`is_writable(__DIR__)`. But `config.php` is written to `CONFIG_PATH` — the **parent**
directory (`includes/db.php:4`). A writable `install/` inside a read-only webroot passes
the check and then fails at the write, with a message about the wrong folder. Should be
`is_writable(dirname(__DIR__))`.

## N16 · LOW — two migrations exist only to cancel each other

`029_hero_copy_refresh_2.php` sets the hero copy to "Your Digital Business / Starts Here.";
`030_revert_hero_copy.php` sets it straight back. On a fresh install both run in sequence
for zero net effect.

Worth noting for whoever writes `033`: 008 and 012 destructure `[$old, $new]`, while 029
and 030 destructure `[$new, $old]` from arrays that look identical. Both are internally
correct — but copying the wrong one silently rewrites live site copy in the wrong
direction.

## N17 · LOW — a table is created, seeded, then dropped during install

`014_content_tables.php:43` creates `content_pricing_plans` and seeds it from
`includes/default_content.php`; `027_simplify_pricing.php:54` drops it. Fresh installs
build and populate a table that's deleted moments later, and `default_content.php:69-73`
still carries the `pricing` array that feeds it. Dead weight in both files.

## N18 · LOW — the Pricing page ships saying "Starting from $5"

`pricing_starting_price` defaults to `'5'` (`027_simplify_pricing.php:50`,
`index.php:25`), so a fresh install's Pricing page headline reads **$5** — while the
Solutions page on the same site lists Build from $900, Publish from $1,500. Whatever the
intent, $5 as the headline number undercuts the positioning everywhere else.

## N19 · LOW — two clients can't share a business name

`businesses.name` is `VARCHAR(150) NOT NULL UNIQUE` (`001_initial.php:32`). Two "Joe's
Pizza" in different towns is a normal situation for a local-business agency, and it
surfaces as the misleading "Another business already uses that name."

## N20 · LOW — deleting the install folder reports success either way

`install_self_destruct()` (`install/index.php:16-24`) uses `@rmdir`/`@unlink` and checks
nothing, then redirects to `../admin/`. If permissions block the deletion, the admin is
told it worked and believes a publicly-reachable installer is gone when it isn't — which,
given N1, matters.

## N21 · LOW — no PHP-execution guard on `assets/uploads/`

Uploads are extension-allowlisted *and* validated with `getimagesize()`, and filenames are
rebuilt server-side as `<id>.<ext>`, so this isn't exploitable today. But the directory
holds attacker-influenced bytes and has no `php_flag engine off` / `RemoveHandler`. Two
lines of defence in depth against a future regression.

## N22 · LOW — `color-mix()` with no fallback

`--accent-3` and `--grad-soft` (`style.css:18,21`) are built with `color-mix()`, which
needs Chrome 111 / Safari 16.2 / Firefox 113 (2023). On anything older those variables are
invalid, so the accent gradient and the soft focus ring silently render as nothing. Add a
static fallback declaration before each.

## N23 · LOW — the ticket form defaults to the first business

`admin/tickets.php:102` renders the Business `<select>` with no empty first option, so a
new ticket silently defaults to whichever business sorts first alphabetically. One
distracted save files a client's issue against someone else's account.

---

## Revised priority

N1 goes to the top of the list — above the free-downloads bug. Everything else in the
original ordering holds, with N2 joining step 3, N3/N4/N5 joining step 7, and the rest
following.

| # | Item | Why |
|---|---|---|
| 0 | **N1** — installer re-arms on deploy | Anonymous, unauthenticated, total data loss |
| 1 | F1 — contact form inbox | Losing leads costs money every day |
| 2 | S1, S2, S3 — checkout | Free products; client-set prices |
| 3 | S4, S5, S6, **N2** — credentials, admin privilege, SITE_URL | Standing compromise |
| 4 | S7, S8 — session flag, login throttling | Two small, high-value fixes |
| 5 | F3, F2 — orders and subscribers views | Staff can't run the business without them |
| 6 | S16, S17 — cascade deletes | One mis-click from irreversible loss |
| 7 | **N4, N3, N5** — laptop layout, dead zoom control, fake activity column | Visible daily |
| 8 | F4–F7, **N6–N9, N14** — dead and half-wired features | Remove or finish |
| 9 | Everything else | |
