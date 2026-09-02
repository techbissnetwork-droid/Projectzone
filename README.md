# TECHBISS platform

A PHP + MySQL application for a digital agency: a customisable public website, an
admin panel, a marketplace for premade projects, and a client portal where the
businesses you build for can track their sites, renewals and support requests.

No Composer, no build step, no framework — upload the folder and run the installer.

---

## Install

1. Upload everything to your web root.
2. Create an empty MySQL database.
3. Open `https://yourdomain.com/install/` and follow the four steps.
4. **Delete the `install/` folder** when it finishes.

Requirements: PHP 8.1+ with `pdo_mysql`, `mbstring`, `fileinfo` and `openssl`;
MySQL 5.7+ or MariaDB 10.3+; `config/` and `uploads/` writable.

The installer checks all of this on step 1 and writes nothing until step 3.

---

## What is in it

### Public site
`index.php` · `services.php` · `portfolio.php` · `portfolio-item.php` ·
`marketplace.php` · `product.php` · `contact.php` · `login.php`

Every headline, colour, service, project and price comes from the database.
The animated hero ecosystem, the offline→online scroll transformation, the
system-architecture diagram and the process timeline are all still there — they
are now partials in `partials/section_*.php`, shared between pages.

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
| Services | The nine modules shown on the site, reorderable and switchable |
| Enquiries | Contact-form inbox with read / archive / delete |
| Settings | Brand, colours, hero copy, contact details, social links, currency, feature switches |

### Client portal — `/client/`
Created automatically: ticking **"Create a portal login from the owner email"**
on a project generates the account and shows the password once.

The client signs in with that email and gets their sites, colour-coded renewal
meters, the maintenance history of what you actually did and when, one-click
maintenance / upgrade / problem requests, a threaded conversation with you, and
their marketplace purchases.

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

## Payments

Marketplace orders are recorded and confirmed **manually**: the buyer places an
order, sees your payment instructions (editable in Settings) and a reference, and
you mark it paid once the money lands. Nothing is charged automatically.

Adding a gateway means one new file that flips an order to `paid` on a verified
callback — the order table already carries `payment_method` and `payment_ref`.

---

## Security

- Every query is a prepared statement; every output goes through `e()`.
- CSRF token on every POST, verified in one place (`Csrf::check()`).
- Session ID regenerated on login; `httponly`, `samesite=Lax`, `secure` over HTTPS.
- Login throttled to 6 failures per email per 15 minutes.
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
app/          bootstrap, Database, Auth, Settings, Csrf, Flash, Upload, helpers, guards
config/       config.sample.php — the installer writes config.php next to it
install/      the four-step wizard, schema.sql and the starter content
partials/     public + app shells, the designed home-page sections, shared cards
admin/        11 admin pages
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
