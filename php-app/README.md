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
- Customer sign up / log in / log out — real accounts in `customers`,
  hashed passwords (`password_hash`/`password_verify`), PHP sessions.
- `/dashboard` (hash route `#/dashboard`) requires a signed-in customer —
  it redirects to `#/login` otherwise. The dashboard content itself is
  still the same illustrative "preview" shown to every signed-in customer
  (it isn't wired to per-business real data yet — see "Natural next
  steps" below).
- The **staff admin panel** at `/admin/` is a completely separate app from
  the public SPA. It requires a real staff login (`admin/login.php`,
  session-gated) and every number on it — business accounts, MRR, open
  tickets, staff, top sellers — is a live query against MySQL. It is not
  linked from anywhere on the public site; you reach it by typing the URL.

**Not implemented (flagged, not silently faked):**
- No payment processor — the marketplace "buy" flow does not charge
  anyone. Wire Stripe/PayPal into a new `api/checkout.php` before
  accepting real orders.
- No outbound email — the contact form saves to the database but does not
  send a notification email. Add `mail()`/PHPMailer/a transactional email
  API in `api/contact.php` if you want that.
- The "Continue with SSO" button on the login page is decorative, exactly
  as it was in the original design concept — it does nothing.
- The customer dashboard shows the same illustrative content to every
  customer rather than that customer's real projects — see below.

## Requirements

- PHP 8.0+ with the `pdo_mysql` extension (check with `php -m | grep pdo_mysql`)
- MySQL 5.7+ or MariaDB 10.4+
- Any standard host that gives you PHP + MySQL + phpMyAdmin (shared
  hosting, a VPS, etc.)

## Setup

1. **Create a database** in phpMyAdmin (or your host's control panel).
   Note the database name, a database username, and its password.
2. **Import the schema.** In phpMyAdmin, select your new database, open
   the **Import** tab, choose `schema.sql`, and click **Go**. This creates
   every table and seeds realistic sample data (businesses, tickets,
   staff, marketplace products) so the site looks right immediately.
3. **Edit `config.php`** with the database host, name, username and
   password from step 1.
4. **Upload everything** in this folder to your host, keeping the folder
   structure intact (`index.php` at the site root, `admin/`, `api/`,
   `assets/`, `includes/` alongside it).
5. Visit your domain — the public site should load exactly like the
   design concept, now backed by MySQL.

## Staff admin login

Visit `/admin/` (it will redirect you to `/admin/login.php`). The seed
data creates four staff accounts, all sharing one starter password so you
can log in immediately:

| Email | Role |
|---|---|
| mara@techbiss.com | Founder & CEO |
| devon@techbiss.com | Head of Engineering |
| rhea@techbiss.com | Head of Design |
| admin@techbiss.com | VP Client Success |

**Password for all four:** `techbiss-admin-2026`

**Change this before putting the site anywhere public.** The quickest way:
run this once on your server (PHP CLI or a one-off script) to generate a
new hash, then update each row's `password_hash` in phpMyAdmin:

```php
<?php echo password_hash('your-new-password', PASSWORD_DEFAULT);
```

Or simpler: sign in as each staff member and give them a proper
"change my password" flow — this is a natural next step (see below), not
yet built.

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
- Letting staff update ticket status / reassign tickets from the admin
  panel (right now the admin panel is read-only — it reflects the
  database but doesn't yet write back to it beyond auth).

## File structure

```
index.php              Public site shell — queries products, renders the SPA
assets/style.css        All site styling (unchanged from the design concept)
assets/app.js           All site behavior — now talks to api/*.php instead of faking submissions
api/contact.php         Saves contact form submissions
api/signup.php          Creates a customer account
api/login.php           Signs a customer in
api/logout.php          Signs a customer out
api/me.php              Returns the signed-in customer, if any
admin/login.php         Staff sign-in (separate from customer accounts)
admin/logout.php        Staff sign-out
admin/index.php         The real admin panel — session-gated, queries MySQL directly
includes/db.php         Database connection, session setup, auth helpers
includes/icons.php      Small icon set used by the server-rendered admin panel
config.php              Edit this: your database host/name/user/password
schema.sql              Import this into phpMyAdmin to create + seed the database
```
