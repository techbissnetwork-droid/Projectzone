# TECHBISS

**Your Digital Business Starts Here.**

A complete website and content management system for a digital transformation
company — the public marketing site, a prepaid package/commerce flow, lead
capture, and a full PHP/MySQL admin panel behind it.

Built with PHP 8.1+, MySQL and vanilla JavaScript. No Composer, no build step,
no framework. Upload it to an Apache host and it runs.

---

## What is in here

| Area | What it does |
|---|---|
| **Public site** | 20 page types — home, services, packages, portfolio case studies, industries, blog, testimonials, FAQs, contact, quote, a six-step onboarding form and legal pages |
| **Prepaid packages** | Regular price / prepaid price / saving, computed from the database and never invented. Add-ons, a request flow and manual payment confirmation |
| **Admin CMS** | Every visible element is editable: hero copy, homepage bands, services, packages, portfolio, industries, blog, testimonials, FAQs, navigation, footer, SEO and global settings |
| **Leads** | Contact messages, quote and journey requests, customers, purchases and newsletter subscribers, with CSV export |
| **Access control** | Four roles with configurable permissions, enforced per request rather than only hidden in the UI |
| **SEO** | Per-record titles, descriptions, canonicals, Open Graph, Twitter cards, schema.org and a generated sitemap |

---

## Requirements

- PHP **8.1** or newer, with `pdo_mysql`, `mbstring`, `json` and `fileinfo`
  (`gd` for image thumbnails, `intl` for accented slugs — both optional)
- MySQL **5.7+** or MariaDB **10.4+**
- Apache with `mod_rewrite` (an nginx equivalent is given below)
- `config/`, `storage/` and `uploads/` writable by PHP

The setup wizard checks all of this for you and tells you exactly what is
missing, so you do not need to verify it by hand.

---

## Installation

Upload the files to your web root and open the site in a browser. There is no
config file to edit — you are redirected into a setup wizard that detects your
site address, writes the configuration and builds the database for you.

### 1. Open your site

```
https://yourdomain.com/
```

Any request made before setup is complete redirects to
`/database/install.php`. You can also go there directly.

### 2. Work through the wizard

| Step | What happens |
|---|---|
| **Requirements** | Checks the PHP version, the extensions it needs and that `config/`, `storage/` and `uploads/` are writable. Shows you the **site address, sub-directory, HTTPS status and timezone it detected**. |
| **Database** | Enter the details from your hosting panel. The connection is tested before anything is written, and the wizard can create the database for you if your user has the rights. |
| **Your site** | Company name, the pre-filled site address, and your administrator account. Optionally load the demo content. |

The wizard then writes `config/config.php` for you, containing:

- your database credentials
- a freshly generated 64-character `app_key`
- the **detected site URL and sub-directory**
- `cookie_secure` set from whether it reached you over HTTPS
- the server's timezone

### 3. Delete the installer

```bash
rm database/install.php
```

It refuses to run once an administrator exists, but there is no reason to leave
it on the server. Then sign in at `/admin/login`.

### Sub-directory installs

The URL is detected from the request, so serving the site from
`https://example.com/clients/acme/` works with no configuration — links, assets,
canonicals and the sitemap all pick up the prefix automatically.

If you later move the site to a different domain or directory, you can blank
`url` and `base_path` in `config/config.php` and both will be detected per
request again.

### Command line

For scripted deployments:

```bash
php database/install.php \
    --db-name=techbiss --db-user=USER --db-pass=SECRET \
    --name="Your Name" --email=you@example.com --password='a-strong-password' \
    --site-url=https://yourdomain.com
```

Useful flags: `--check` reports requirements and exits, `--create-db` creates
the database, `--demo` loads the demo content, `--db-socket=` connects through a
Unix socket, `--force` reloads the schema over an existing install (destructive).

### Optional demo content

Tick the box in the wizard, or load it later:

```bash
mysql -u USER -p DATABASE < database/demo-content.sql
```

> Every company, person and quote in that file is **fictional**, and it says so
> in the data itself. Remove it before launch with
> `database/demo-content-remove.sql` and delete `uploads/media/demo/`.

---

## Project structure

```
├── admin/              Admin panel — front controller, views, assets
├── assets/             Public CSS, JS, images, fonts
├── config/             Configuration (config.php is gitignored)
├── database/           Schema, seed data, installer, optional demo content
├── includes/           Application code
│   ├── Admin/            Declarative resource registry
│   ├── Controllers/      Public and admin controllers
│   ├── Core/             Database, router, auth, validation, uploads, SEO…
│   ├── Repo/             One repository per content type
│   └── data/             Static lookup data
├── pages/              Public templates and partials
├── storage/            Cache and logs (writable)
├── uploads/            Uploaded media (writable, executes nothing)
└── index.php           Public front controller
```

There is no giant file: database access lives in `includes/Repo`, request
handling in `includes/Controllers`, and templates hold markup only.

---

## Adding a new content type

Simple content types are declared, not coded. Add an entry to
`includes/Admin/Resources.php` describing the table, its list columns and its
form fields, and the generic controller provides create, edit, delete, publish,
reorder and search for it — including validation and the admin UI.

Content types with genuinely bespoke behaviour — portfolio, packages,
purchases, media, navigation, settings and users — have their own controllers.

---

## Security

The measures actually implemented, not a checklist:

- **SQL** — every query is a PDO prepared statement; identifiers are validated
  against a whitelist pattern before ever reaching a query
- **Output** — everything is escaped at the point of output; admin-authored
  rich text is run through a tag/attribute whitelist on save
- **Structured data** — JSON-LD is encoded with `JSON_HEX_TAG`, so no raw `<`
  can reach a `<script>` block
- **CSRF** — every state-changing request is verified; failures return 419
- **Passwords** — `password_hash()` with automatic rehash on algorithm change
- **Login** — attempts are recorded and throttled per email and per IP
- **Sessions** — HttpOnly, SameSite=Lax, regenerated on login, bound to a
  client fingerprint, idle-expired
- **Uploads** — accepted on real MIME type read from the file contents, with
  the extension derived from that type rather than the filename; SVGs are
  rejected if they contain script; `uploads/.htaccess` disables execution
- **Authorization** — checked per action in the controller, so a forbidden
  endpoint returns 403 even when posted to directly
- **Public forms** — honeypot field plus a per-IP submission throttle
- **Money** — prices are re-read from the database at checkout; anything the
  browser posts about price, discount or total is ignored

### Before going live

1. Delete `database/install.php`
2. Serve everything over HTTPS. If you installed over HTTP, set
   `cookie_secure => true` in `config/config.php` afterwards — the wizard sets
   it automatically when it reaches you over HTTPS
3. Confirm `config/`, `includes/`, `database/` and `storage/` are not
   web-accessible (the shipped `.htaccess` files handle this on Apache)
4. Replace or remove the demo content

`debug` is already `false` and `app_key` is already unique — the wizard
generates a fresh key for every installation.

---

## nginx

The project ships Apache `.htaccess` files. The equivalent nginx server block:

```nginx
server {
    root /var/www/techbiss;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location /admin {
        try_files $uri $uri/ /admin/index.php?$query_string;
    }

    # Application internals are never served directly
    location ~ ^/(config|includes|database|storage)/ { deny all; }

    # Uploads are data, never code
    location ^~ /uploads/ {
        location ~ \.php$ { deny all; }
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

---

## Notes on content

Three things are deliberately **empty** on a fresh install:

- **Testimonials** — a testimonial is a claim about a real person. Add your own.
- **Portfolio** — same reasoning. Case studies describe real work.
- **Statistics** — the homepage stats band hides itself until you enter figures
  you can stand behind.

The site is designed to look complete without them; empty states are styled
rather than left blank.
