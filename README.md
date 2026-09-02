# TECHBISS — company website + admin

Website and content management system for TECHBISS: we build websites and apps,
and take offline businesses online — domain, hosting, SSL, business email, SEO
and support, all handled by one partner.

Built in plain PHP with no framework and no build step, so it uploads straight
to normal cPanel / shared hosting by FTP.

---

## Installing on cPanel

1. **Create the database.** cPanel → *MySQL Databases*. Create a database,
   create a user, then add the user to the database with **All Privileges**.
   Note all three values — cPanel prefixes them with your account name.
2. **Upload the files** to `public_html` (or a subfolder).
3. **Visit `https://yourdomain.com/install.php`** and fill in the form: database
   details, the admin account you want to sign in with, and where enquiries
   should be emailed.
4. **Delete `install.php` from the server.** It refuses to run twice, but it has
   no business being there afterwards.
5. Sign in at `https://yourdomain.com/admin/`.

### The email "from" address

Set it to something on your own domain (`website@yourdomain.com`), not Gmail or
Yahoo. Shared hosts refuse to send mail claiming to be from a domain they do not
control, and receiving servers treat it as spoofed. Create the mailbox in cPanel
→ *Email Accounts* first.

If mail fails, **nothing is lost** — every enquiry is written to the database
before mail is attempted, and the dashboard says how many could not be sent.

---

## Running it locally

```sh
php -S localhost:8000 router.php
```

Then open `http://localhost:8000/install.php` and choose **SQLite** as the
database type — no MySQL server needed. `router.php` is only for this; on real
hosting `.htaccess` does the same job.

---

## What you can edit from the admin area

Everything on the public site, with no HTML:

| Section | Covers |
| --- | --- |
| **Enquiries** | Read, search, filter, status, private notes, CSV export |
| **Page text** | Every heading, paragraph and button label, grouped by page |
| **Company details** | Name, email, phone, hours, social links, footer text |
| **Services** | The 10 bento cards and their full detail rows |
| **Industries** | The colour cards and the sector detail grid |
| **Pricing packages** | Build packages and monthly care plans |
| **Add-ons** | The extras grid |
| **Comparison table** | Rows and cells |
| **FAQs** | Questions per page |
| **Testimonials, Statistics, Process steps, Working rules, Team** | |
| **Admin users** | Add admins, change your password |

Conventions used in the editing forms:

- **Bullet points / options** — one per line.
- **Status panel rows** — one per line, written as `label | value`.
- **Statement lines** — put `{curly braces}` around the words to highlight green.
- Every list has an ordering control and a **Visible on the site** switch, so you
  can hide something without deleting it.

---

## Layout

```
index.php services.php industries.php     public pages, rendered from the database
pricing.php about.php contact.php 404.php
install.php                               one-time setup, delete after use
router.php                                local development only
.htaccess                                 blocks /app, pretty URLs, 404 page

app/
  config.php        your credentials — created by the installer, git-ignored
  config.sample.php what config.php looks like
  bootstrap.php     loaded first by every page
  db.php            PDO connection (MySQL or SQLite) and query helpers
  schema.php        table definitions
  seed.php          the starting content
  auth.php          login, sessions, brute-force throttling
  mailer.php        enquiry notification
  helpers.php       escaping, CSRF, content lookup
  resources.php     defines every editable list and its fields
  partials/         shared page pieces

admin/              the admin area (one generic CRUD screen drives every list)
assets/             css, js, favicon — unchanged from the static site
storage/            SQLite database and runtime files (git-ignored)
```

Adding a new editable list means adding a table to `app/schema.php` and an entry
to `app/resources.php`. No new admin page is needed.

---

## Security

- Passwords hashed with `password_hash()`, re-hashed when PHP's cost changes.
- CSRF token required on every form; a request with no token is rejected.
- Every query uses prepared statements; table names are pattern-checked.
- All output escaped with `htmlspecialchars()`.
- Login throttled to 5 failures per 15 minutes, per IP.
- Contact form limited to 5 messages per hour per IP, with a hidden honeypot
  field and a minimum fill-in time to catch bots.
- `app/` and `storage/` blocked in `.htaccess`, with a second `.htaccess` inside
  each as a fallback.
- Sessions are HTTP-only, SameSite=Lax, and Secure when the site is on HTTPS.

After installing, set `app/config.php` to permissions **644** and keep `debug`
set to `false`.

---

## Before going live

- **Contact details** — the phone number and social links are placeholders
  (Admin → Company details).
- **Prices** — the figures are illustrative (Admin → Pricing packages).
- **Statistics and testimonials** — placeholder figures and quotes.
- **Domain** — `sitemap.xml` and `robots.txt` assume `techbiss.com`.
