# TECHBISS platform

A PHP + MySQL application for a digital agency: a customisable public website, an
admin panel, a marketplace for premade projects, and a client portal where the
businesses you build for can track their sites, renewals and support requests.

No Composer, no build step, no framework — upload the folder and run the installer.

---

## Install

1. Upload everything to your web root.
2. Create an empty MySQL database.
3. Open `https://yourdomain.com/install/`. The site URL and timezone are detected
   for you; check the database details and go.
4. **Delete the `install/` folder** when it finishes.

The installer inspects the database and offers the right path:

| Database | What it offers |
|---|---|
| Empty | Fresh install — schema, admin account and starter content |
| Already has TECHBISS tables | **Update** (keeps everything) or **wipe and install fresh** (requires typing `DELETE`) |

### After a code update

Sign in and use **System → Run database update**. It adds new tables, columns and
settings and leaves your records alone; running it twice does nothing. You never
need to touch `install/` for an upgrade.

If the database is behind the code, the site stays up rather than white-screening:
new sections switch off, and the admin panel shows a banner pointing at System.

### The installer lock

Installing writes `install/.installed`, and the installer then refuses to run —
it has no password prompt of its own. To reinstall from scratch you must sign in
as an administrator and unlock it from **System**, confirming with your own
password. You can re-lock it there too. Deleting the folder is still the safest end state.

Requirements: PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo` and `openssl`;
MySQL 5.7+ or MariaDB 10.3+; `config/` and `uploads/` writable.

The installer checks all of this on the first step and writes nothing until you confirm.

---

## What is in it

### Public site
`index.php` · `services.php` · `portfolio.php` · `portfolio-item.php` ·
`marketplace.php` · `product.php` · `contact.php` · `login.php` · `page.php` (your own pages)

Every headline, colour, service, project and price comes from the database.
The animated hero ecosystem and the system-architecture diagram are still there;
the offline→online transformation and the process timeline are plain single rows
of stages rather than scroll-driven set pieces. All of them are partials in
`partials/section_*.php`, shared between pages.

### Admin panel — `/admin/`
| Page | What it does |
|---|---|
| Dashboard | Live counts, and every domain / hosting / SSL renewal due inside the warning window |
| Projects | Create a delivered site with owner details, domain, hosting, SSL, email and fees |
| Project detail | Renewal meters, maintenance & upgrade history, linked tickets, owner record |
| Clients | Accounts, roles, suspension, password reset. Cannot delete the last admin |
| Support | Ticket queue sorted by priority, threaded replies, status and priority control |
| Portfolio | Add completed projects; **delete** them; flip each between public and admin-only |
| Marketplace | List premade projects with price, sale price, includes, demo link and cover |
| Orders | Confirm payments; a sale is counted once, when it first reaches paid |
| Services | The modules shown on the site, reorderable and switchable |
| Enquiries | Contact-form inbox with read / archive / delete |
| **Home page** | Turn each section on or off and drag the order; edit the hero, the quote and the heading of every section |
| **Content** | Every repeatable list: hero stats, the offline→online journey, the process timeline, the trust pillars, the architecture diagram, the transformations |
| **Pages** | Create any page — About, Privacy, Terms — with its own header style and SEO |
| **Menus** | Build the header menu and three footer columns from built-in pages, your own pages or any URL |
| **Appearance** | Logo, favicon, share image, five colours, ten fonts, corner style, custom CSS and head/body code |
| Settings | Brand, contact details, social links, currency, feature switches, SEO |
| **System** | Run the database update, lock or unlock the installer, see the environment |

### Signing in

Two separate doors, both passwordless:

| Who | Where | How |
|---|---|---|
| Clients | `/login.php` | Enter your email, get a six-digit code, enter it |
| Staff | `/staff-login.php` | Same, plus a password fallback |

Codes are stored hashed, work once, expire in ten minutes, die after five wrong
attempts, and are limited to five per address per hour. Requesting one never
reveals whether an account exists. A client code cannot open the staff door and
an administrator cannot sign in on the client page.

The staff page keeps a password fallback on purpose: if your server cannot send
mail, that is the difference between a bad afternoon and being locked out of your
own site. Set **Email sender address** in Settings to an address your host is
allowed to send from, or the codes will land in spam.

### Client portal — `/client/`
Created automatically: ticking **"Create a portal login from the owner email"**
on a project generates the account.

The client signs in with that email — a code is emailed, no password — and gets
their sites, colour-coded renewal meters, the maintenance history of what you
actually did and when, one-click maintenance / upgrade / problem requests, a
threaded conversation with you, and their marketplace purchases.

---

## How the pieces connect

```
Project ──┬── owner name / email / phone
          ├── domain     + registrar + expiry ──┐
          ├── hosting    + plan      + expiry ──┼─→ renewal meters (admin + client)
          ├── SSL        + issuer    + expiry ──┘
          ├── business email + mailbox count
          ├── maintenance_logs ─────────────────→ "what we have done" in the portal
          ├── tickets ──────────────────────────→ support / maintenance / upgrade thread
          ├── user account ─────────────────────→ the client's login
          └── portfolio entry ──────────────────→ public case study, or admin-only
```

A portfolio entry is `public` or `private`. Private entries never leave the admin
panel, so you can record a delivery before the client agrees to publish it.

---

## What is editable

Nothing on the public site is hardcoded any more.

- **Every section of the home page** — order, visibility, and the eyebrow,
  headline and paragraph of each one, all on the Home page screen.
- **Every repeatable list** — the four journey stages, five process steps, six
  pillars, ten architecture nodes, six transformations and three hero stats are
  rows in one table you edit, reorder, hide or delete. Add a sixth process step
  and the timeline, the counter and the graphics all follow.
- **Menus** — header and three footer columns, each link pointing at a built-in
  page, one of your own pages, or any URL.
- **Pages** — write plain text; blank lines make paragraphs, a short line ending
  in a colon becomes a heading, and lines starting with `-` become a list.
- **Appearance** — logo and favicon uploads, accent, background and text colours,
  headline and body fonts from ten families, corner radius, plus custom CSS and
  head/body code injected on every public page.

The hero's orbiting ecosystem is built from your service names, so it always
matches what you actually sell.

## Payments

Set up methods under **Payments**. Two kinds:

**Manual** — bank transfer, a wallet QR, cash. The buyer sees your instructions,
account details and a reference; you mark the order paid in Orders once the money
lands. Works with no configuration beyond typing your account number.

**Gateways** — eSewa, Khalti and Stripe. The buyer is redirected, pays, and comes
back. Each needs its own keys, entered under Payments and stored in the database.

No order is ever marked paid because a browser came back with a success URL. On
return the result is re-checked against the provider's own API, and the amount
and order reference on their record must match ours before anything changes.
Every attempt is logged, so a failed or abandoned payment stays visible.

Buyers land on `order.php`, reachable by a per-order token, which shows the
payment steps and stays current — safe to bookmark or email.

**Before taking real money:** run one full transaction in the gateway's sandbox
(the "Sandbox / test mode" switch on the method), confirm the order flips to
paid, then switch it off. The gateway code is written to each provider's current
API but has not been run against a live merchant account.

---

## Security

- Every query is a prepared statement; every output goes through `e()`.
- CSRF token on every POST, verified in one place (`Csrf::check()`).
- Session ID regenerated on login; `httponly`, `samesite=Lax`, `secure` over HTTPS.
- Sign-in is by emailed one-time code; codes are hashed, single-use, expire in
  ten minutes and are rate limited per address. Password attempts (the staff
  fallback) are throttled to 6 failures per email per 15 minutes.
- Uploads: MIME whitelist, SVG scanned for script, random filenames, and
  `uploads/.htaccess` turns the PHP engine off in that folder.
- Role checks on every admin and portal page; clients can only reach their own
  records (`require_owner`).
- `config/`, `app/` and `partials/` are blocked in `.htaccess`, and each partial
  also refuses to run when loaded directly.

**On nginx** there is no `.htaccess`, so add this to your server block:

```nginx
location ~ ^/(config|app|partials)/ { deny all; }
location ~ \.(sql|md|log)$          { deny all; }
location ^~ /uploads/ { location ~ \.php$ { deny all; } }
location /install/ { allow 203.0.113.4; deny all; }   # your IP, then delete the folder
```

---

## Layout

```
app/          bootstrap, Database, Auth, Settings, Content, Csrf, Flash, Upload, helpers, guards
config/       config.sample.php — the installer writes config.php next to it
install/      the wizard, schema.sql, migrations.php and the starter content
partials/     public + app shells, the home-page sections, shared cards
admin/        17 admin pages
client/       6 portal pages
assets/       base.css, main.css, pages.css, app.css, install.css, site.js, viz.js, app.js
uploads/      portfolio/, products/, site/ — written by the admin panel
```

`assets/css/base.css` holds the design tokens. Change them there, or change the two
accent colours in **Settings**, and the whole site follows.

---

## After installing

- Delete `install/`.
- Set your real contact details, social links and currency in **Settings**.
- Replace the four seeded marketplace products with your own.
- Add your delivered projects under **Projects**, and publish the ones you want
  on the public portfolio.
- Rewrite the seeded About, Privacy and Terms pages under **Pages**.
- Make it yours under **Appearance**: upload a logo, pick your colours and fonts.
