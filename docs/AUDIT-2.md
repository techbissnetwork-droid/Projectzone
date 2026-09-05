# TECHBISS — clean audit after the fixes

Second full pass over the whole codebase, now that a real MariaDB is available.
Everything below was **executed**, not reasoned about — the original audit had no
database and said so.

- 88 files · ~8,900 lines · PHP 8 + MySQL/MariaDB + vanilla-JS SPA
- `php -l` clean across all 74 PHP files · `node --check` clean on `app.js`
- Fresh install, upgrade and recovery flows all driven end to end
- Zero PHP fatals, warnings or notices across every page and endpoint

---

## 1. What was executed

### Install, upgrade, recovery

| Flow | Result |
|---|---|
| Fresh install: setup → save_db → create_admin | "You're live", 33 migrations applied |
| Staff rows seeded | **1** (the original installer created 4 sharing your password) |
| `rate_limits` table created by migration 033 | yes |
| Lock file deleted, data present, signed out | asks migrate-or-fresh; both refused; no lock written |
| Same, signed in | migrate runs, lock written, manage screen |
| Unknown route `/nope-404` | HTTP **404** (was 200 with the homepage) |

### Every page

All 12 admin pages return 200 with **no PHP notices**: dashboard, users, businesses,
messages, orders, marketing, tickets, products, content, staff, settings.
(`projects.php` 302s to businesses without a `?business=` — unchanged behaviour.)

All 14 public routes return 200: `/`, services, solutions, marketplace, work,
process, pricing, about, resources, contact, login, privacy, terms, and a product
detail page.

### Every endpoint

| Endpoint | Unauthenticated result |
|---|---|
| `me.php`, `dashboard.php`, `project-request.php` | 401 |
| `purchase.php` | 503 — checkout is off, so no free downloads |
| `contact.php`, `newsletter.php`, `otp-request.php` | 200, then 429 at the cap |
| any endpoint with a foreign `Origin` | 403 |

### The security fixes, proven rather than asserted

- **Contact form → admin inbox.** Posted an enquiry; it landed in
  `contact_messages` and renders in `admin/messages.php` under "needs a reply".
  This table was previously write-only.
- **Rate limiting.** Sixth contact post in the window returns 429.
- **Honeypot.** A post with the hidden field filled returns 200 and writes **no
  row** (row count unchanged), so bots don't retry.
- **Login throttle.** Attempt 8 trips it, and the *correct* password is refused
  while throttled — no bypass.
- **Foreign origin.** 403.
- **Cascade fix.** `product_orders → products` is now `RESTRICT`; deleting a
  product can no longer erase purchase history.

---

## 2. Bugs found and fixed in this pass

### The 500 you hit — mine

Open `/install/`, enter database details, press Connect → HTTP 500:

```
PDOException: SQLSTATE[42S02]: Base table or view not found:
1146 Table 'staff' doesn't exist   (includes/db.php:393)
```

Introduced by my own view-logic refactor. `current_staff()` used to run only
inside `$lockExists && current_staff()`, so on a fresh install it never ran. The
refactor evaluates it whenever config exists and the database connects — and a
browser still holding a staff session cookie from a previous install then queries
a `staff` table the schema hasn't created yet.

`current_staff()` and `current_customer()` now return `null` when the lookup
fails. Not knowing who you are is "signed out", and it must never be a 500 on the
one page you'd use to recover.

### The installer stopped asking — also mine

To close the unauthenticated database-wipe hole I had `is_installed()` silently
rewrite `install.lock`, which answered "migrate or start fresh?" before anyone was
asked. Split into two questions: *what is offered* (always the choice) and *who
may act on it* (staff only, once the database holds this app's tables).
`migrate_existing` was previously unguarded — only the wipe was.

### Partial uploads no longer fail silently

Four separate "this looks broken" reports had one cause: new PHP against an old
`assets/style.css`. PHP is never cached so markup lands; the stylesheet doesn't,
so the rules it needs are missing, and correct code reads as a layout bug.

- `?v=` is now a hash of the assets' own contents — it cannot fail to change.
- Each asset carries a release marker; the admin dashboard shows a red card when
  they disagree.
- Every entry point checks for `asset_version()` and, if `includes/` is older,
  prints what happened instead of a blank 500.

---

## 3. Still open — deliberate, needs your call

1. **No payment processor.** `payments_enabled` defaults off, `purchase.php`
   returns 503, the buy button routes to Contact. Turning it on gives products
   away. Real fix is Stripe Checkout with orders written only from a verified
   webhook.
2. **Hardcoded content** — the Process page's five stages, the Solutions
   timelines, the Resources article list. Not editable from admin.
3. **`plan` field** on businesses is vestigial since migration 027.
4. **`businesses.name` is UNIQUE** — two clients can't share a name.
5. **Migrations 029/030 cancel out**, and 014 creates a table 027 drops. Harmless;
   already applied on your database.
6. **`pricing_starting_price` defaults to $5** while Solutions says builds start
   at $900.
7. **Admin Projects page** — the all-projects view is stashed, not shipped.
   The dock is still Dashboard · Users · Businesses.
8. **`.nav-burger` is dead** — its `display:none` is declared after the media
   query that shows it, the same cascade mistake the footer had, and it is in the
   original package too. Left hidden on purpose: the dock's Menu already opens
   that sheet.

---

## 4. Deploying this

1. **Delete `assets/` and `includes/` on the server, then upload fresh.** Do not
   merge — most file managers skip folders that already exist, which caused every
   layout complaint in this session.
2. Visit `/install/` signed in as staff and run any pending update.
3. Check the admin dashboard: a red "upload looks incomplete" card means step 1
   didn't take.
4. Settings → Email: set the contact notification address.
5. Settings → checkout: confirm it reads **off**.
6. Staff: if the red shared-password warning appears, delete the sample accounts
   and reset passwords on any you keep.

Two test suites ship with the code and need no database:

```
php install/view-logic-test.php     # installer decision table, 13 cases
php -l <file>                       # syntax
```
