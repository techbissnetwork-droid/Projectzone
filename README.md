# TECHBISS — website, admin panel, marketplace and client portal

The public site, a full admin area, a marketplace for premade projects, and a
portal where clients see their own project and talk to you.

Plain PHP 8.1+ and MySQL. No framework, no Composer, no build step — it uploads
to ordinary cPanel hosting by FTP and runs.

---

## Installing on cPanel

1. **Create the database.** cPanel → *MySQL Databases*. Create a database, create
   a user, then add the user to the database with **All Privileges**. Note all
   three values — cPanel prefixes them with your account name.
2. **Create the mailbox** you want mail sent from, in cPanel → *Email Accounts*.
   It must be on your own domain (`website@yourdomain.com`), not Gmail or Yahoo.
3. **Upload the files** to `public_html`.
4. **Open `https://yourdomain.com/install.php`** and fill in the form. The site
   address is detected for you.
5. **The installer deletes itself.** If it cannot — the web server has no
   permission — it says so and you remove it by hand.
6. Sign in at `https://yourdomain.com/admin/`.

Make sure `app/` and `storage/` are writable (755) before step 4.

### Updating later

Upload the newer files over the old ones, then **Settings → Database → Run the
update** in the admin area. It adds any new tables, columns and settings the new
version expects. It never deletes anything and never overwrites text you have
edited.

### If you keep install.php

Once a site is installed the installer writes `app/install.lock`, and from then
on it will not do anything without an administrator password — even if it is
re-uploaded later. Signed in, it offers three things: run the database update,
delete itself, or erase everything and start again (that one needs you to type
`ERASE` and set up a new administrator).

### If mail fails

**Nothing is lost.** Every enquiry, order and support message is written to the
database *before* mail is attempted, and the dashboard tells you how many could
not be sent. The usual cause is a "from" address on a domain the host does not
control.

## Running it locally

```sh
php -S localhost:8000 router.php
```

Open `http://localhost:8000/install.php` and pick **SQLite** — no database
server needed. `router.php` is only for this; on real hosting `.htaccess` does
the same job.

---

## What is in it

### Public site
Home, Services, Industries, **Work** (portfolio), **Marketplace**, Pricing,
About and Contact. Every word is editable from the admin area.

### Admin area — `/admin/`

| Section | What you can do |
| --- | --- |
| **Dashboard** | Renewals falling due, new enquiries, orders and open support, all on one screen |
| **Enquiries** | Read, filter, set status, keep private notes, export CSV |
| **Orders** | Marketplace orders — confirm, mark paid, mark delivered |
| **Support** | Every client request, with replies and internal notes |
| **Projects** | One record per site you run: domain, hosting, SSL, email, care plan, renewal dates |
| **Client accounts** | Portal logins, password resets, suspend or delete |
| **Maintenance log** | Everything done to every project, per project or all together |
| **Page text** | Every heading, paragraph and button on the public site, grouped by page |
| **Branding & SEO** | Logo, favicon, sharing image, page titles, the words Google shows, your address |
| **Services / Industries / Pricing / Add-ons / FAQs / Testimonials** | Add, edit, reorder, delete |
| **Portfolio** | Completed work — public on the site, or **admin only** as an internal record |
| **Marketplace** | Premade projects for sale, with sale prices and cover images |
| **Settings** | Company details, contact addresses, social links, currency |
| **Team** | Admin and staff accounts, and setting a colleague's password |
| **Backup** | Download the whole database as a .sql file |
| **Activity** | Audit trail of who changed what |

### Client portal — `/client/`

Created from a project: put the owner's email in, tick *Give the owner a login*,
and the account exists. **Clients have no password.** They go to the portal, type
their email address, and a six-digit code is emailed to them — good once, and for
ten minutes. A client cannot change their own email address, because it is the
only way into the account; only you can, from Client accounts.

Staff and admins are different: they sign in to `/admin/` with a password, so a
broken mail server can never lock you out of your own site. If somebody forgets
theirs, another admin sets a new one under Team.

In the portal a client sees:

- their project's domain, hosting, SSL and email renewal dates, colour-coded
- the maintenance history — but only entries you marked client-visible
- support, maintenance and upgrade requests, with two-way messaging
- their own name and contact details

---

## Portfolio: public or private

Every portfolio entry has a **Visibility** setting:

- **Public** — appears on `/portfolio.php` and has its own page
- **Admin only** — stays inside the admin area as an internal record. It is not
  listed, and its direct URL returns 404.

Deleting is permanent and removes the cover image too.

## Marketplace: how a sale works

1. Someone opens a listing and sends the order form. No card details are taken
   and no account is created.
2. The order is saved, and emailed to you and to them.
3. You confirm, invoice, and mark it paid, then delivered.
4. If they ticked the setup service, create a project for them — that also gives
   them a portal login.

---

## Renewal reminders

The dashboard shows what is falling due, but the reminders that actually reach
your inbox come from `cron.php`. Set it up once, in cPanel → *Cron Jobs*, to run
daily:

```
/usr/local/bin/php /home/ACCOUNT/public_html/cron.php --token=YOUR_TOKEN
```

The token is generated under **Settings**. Without it the script does nothing, so
the URL is safe to leave public. Reminders go out 45, 14 and 3 days before a
renewal and again once it is overdue — those numbers are configurable, and each
one is recorded so the same reminder is never sent twice. Anything that fails to
send is *not* recorded, so the next run tries again.

Optionally the client gets a copy too, so a renewal charge never surprises them.

## Selling a premade project

1. Attach the files (a zip) to the listing under **Marketplace**. They are stored
   in `storage/files`, which the web server refuses to serve.
2. When an order is paid, open it and press **Email the download link**. The
   buyer gets a link carrying a random token, valid for as long as you choose.
3. If it expires before they fetch it, send a fresh one — the old link dies the
   moment you do.

## Security

- Passwords hashed with `password_hash()`; they cannot be read back, only reset
- CSRF token on every form; failures return 419 rather than acting
- Sign-in throttled to 8 attempts per address per 15 minutes, requests for a
  sign-in code included
- Sign-in codes are stored hashed, expire in ten minutes, work once, and die
  after five wrong guesses
- All queries use prepared statements
- Everything printed is escaped
- Admins and clients are separate roles that cannot reach each other's areas
- Uploads accept images only, checked by content and not by file extension, are
  downscaled to 1800px (which also strips EXIF, so photos stop carrying the GPS
  coordinates of where they were taken), and `storage/uploads/.htaccess` stops
  anything there being executed
- Files for sale live in `storage/files`, denied by Apache *and* by `router.php`,
  and are only ever handed over by `download.php` after it checks the token
- `app/` and `storage/` deny direct web access
- `app/config.php` is gitignored — real credentials never reach the repository

Do not store client passwords in the project notes field. Use a password manager.

---

## Layout

```
install.php            one-time installer — delete after use
index.php …            the public pages
admin/                 admin area
client/                client portal
app/                   config, database, auth, mail, content, templates
assets/                shared CSS, JS, favicon
storage/uploads/       uploaded images
designs/               the five design concepts from round one
```

`app/resources.php` defines the tables the admin edits generically. Adding a new
editable section means adding an entry there and nothing else.

---

## Notes

- Prices, statistics and testimonials are placeholder content. Replace them with
  your real figures before this goes near a customer.
- The three portfolio entries and four marketplace listings are examples, so you
  can see the shape of a good one. Delete them when you add your own.
