# What was fixed

Applied to the 2026-09-05 deploy package. Three commits on this branch:
the upstream package as a baseline, then security, then the rest — so every
change is reviewable as a diff against what you uploaded.

The uploaded update itself changed five files (mobile menu polish, plus the
removal of the `wipe_reinstall` action). Worth knowing: the branch that was
removed was the **auth-gated** one. The unauthenticated
`fresh_install_existing` was still there, and it is the one that mattered.

---

## Fixed

### Critical

| # | Was | Now |
|---|---|---|
| N1 | `install.lock` is gitignored and `is_installed()` depended on it, so a git deploy re-opened `/install/` on a live site — including an anonymous "wipe this database" form | Installed-state comes from the presence of the app's tables; the lock file is rewritten when a deploy drops it; every reconfiguring or destructive branch also requires a staff session once the database has data |
| S1 | Checkout took no payment and returned a working download link to any anonymous caller | Gated behind `payments_enabled`, defaulting to **off**; the endpoint 503s and the product page routes buyers to Contact |
| S2 | Order price came from the request body | Read from the product row; the client no longer sends a price at all |
| F1 | Every contact enquiry went into a table nothing read | `admin/messages.php` with a needs-a-reply queue, plus a notification email per submission |

### High

| # | Was | Now |
|---|---|---|
| S3 | Guest checkout attached orders to any existing account matching the submitted email, and mailed that person a receipt | An email that already has an account must sign in first |
| S4 | The SMTP password was printed into the Settings page source on every load | Field always renders empty; only writes when a new value is typed; explicit "clear" checkbox |
| S5 | The installer seeded three full-access staff accounts on the owner's own password | Only your account is created. Existing installs get a warning in Staff listing any account still sharing a password hash |
| S6 | The `staff` permission let you grant yourself full access, or take over a colleague's account | Granting access, creating/deleting accounts and changing others' passwords are owner-only |
| S7 | Session cookie had no `Secure` flag | Set whenever the request is HTTPS (direct or via `X-Forwarded-Proto`) |
| S8 | Staff login had a 200 ms sleep and nothing else | Throttled per email and per IP, backed by a shared `rate_limits` table |
| N2 | `detect_base_url()` fed the `Host` header into `SITE_URL`, which builds every emailed magic link | Host validated, and the field carries an explicit warning to check it |
| N3 | "Site zoom" was a viewport `initial-scale`, which desktop browsers ignore | Also emits a root `font-size`, so it works everywhere; migration comment corrected |
| N4 | Desktop nav hidden below 1360px, so ordinary laptops got the phone UI | Moved to 1024px |
| F2, F3 | Newsletter subscribers and marketplace orders were invisible to staff | `admin/orders.php` — orders with one-click link re-issue, subscribers with CSV export |

### Medium and low

Security: headers (`X-Frame-Options`, `nosniff`, `Referrer-Policy`, HSTS) from
PHP and Apache (S9); download tokens expire after 30 days, cap at 25 uses, and
resolve inside the uploads directory only (S10, S11); rate limits and honeypots
on contact and newsletter (S12); sign-in codes capped per account *and* per IP,
with a failure budget that a fresh code no longer resets (S13); `otp_codes`
pruned and indexed (S14, B13); logout is POST-only with a same-origin check
(S15); deleting a product no longer cascades away purchase history (S16); mail
sender validated and CR/LF stripped, bodies CRLF with correct dot-stuffing plus
`Message-ID` and `Reply-To` (S19, S20); same-origin checks across the JSON
endpoints; uploads directory blocks script execution (N21); `install.lock` no
longer web-readable (B12).

Correctness: expiry countdowns now agree between admin and dashboard (B1); admin
forms keep what was typed on a validation failure (B2); marketing cap survives a
race and leads de-duplicate on phone number (B3, B4); product saves validate every
upload before moving any, "remove" no longer eats a simultaneous replacement, and
deleted products clean up their files (B5, B6, B7); `businesses.customer_id` has
a real foreign key (B8); `JSON_CONTAINS` guarded with `JSON_VALID` (B9);
`APP_DEBUG` guarded with `defined()` (B10); the four remaining unescaped
interpolations closed (B11, N13); `time_ago()` handles future dates (B14);
render errors are logged and shown instead of swallowed (N12); `color-mix()` has
static fallbacks (N22).

Features: the portfolio checkbox drives a real section on `/work` (F4); admin
tickets can name a project, so a project task reaches the customer (F7); unknown
URLs return a genuine 404 (F8); an unknown product id shows a not-found panel
instead of a different product (F9); content items can be reordered (N9); the
"view current file" link no longer points at a path `.htaccess` denies (N6);
picking an owner clears the new-user fields (N7); the test-email card states
plainly that unsaved changes aren't included (N8); the ticket form no longer
defaults to the alphabetically-first client (N23); marketplace chips follow the
real catalogue (part of N14); `last_activity_at` is updated on real events (N5);
the installer checks the directory it actually writes to and reports a failed
self-delete honestly (N15, N20); README corrected (F10, F11).

---

## Deliberately not changed — these need your decision

1. **Checkout is off by default.** Turning it on hands the paid file to anyone
   who clicks, because nothing charges a card. The switch is in
   Settings > Email &amp; checkout, and the copy there says so. The real fix is
   Stripe Checkout with `product_orders` written only from a verified
   `payment_intent.succeeded` webhook — that's a feature build, not a bug fix,
   so it isn't in this branch.

2. **Hardcoded content** (N14, F5): the Process page's five stages, the
   Solutions "Pick your path" timelines, and the Resources article list are
   still in `assets/app.js`. The Resources cards' dead "Read →" affordance is
   removed so they no longer look clickable, but the articles themselves are
   placeholder copy. Making these editable means new Content tabs — say the
   word and I'll add them.

3. **The `plan` field** (F6) is still a required dropdown on Businesses with
   nothing behind it since migration 027 dropped pricing plans. Removing it
   touches stored data, so I left it for you to confirm.

4. **`businesses.name` is still UNIQUE** (N19). Two clients genuinely can share
   a name; dropping the constraint is a one-line migration, but it changes what
   "duplicate business" means in your workflow.

5. **Migrations 029/030 still cancel each other, and 014 still creates a table
   027 drops** (N16, N17). They're already applied on your live database, so
   deleting the files changes nothing there and only saves a fresh install some
   churn. Left alone rather than rewriting applied history.

6. **`pricing_starting_price` still defaults to $5** (N18) while Solutions says
   builds start at $900. That's a copy decision, not a bug — change it in
   Settings > Stats.

---

## Verification

- `php -l` across all 84 PHP files: clean.
- `node --check assets/app.js`: clean.
- Smoke tests of the database-free logic (mail header sanitising, SMTP
  dot-stuffing and CRLF handling, `time_ago` on future dates, zoom clamping):
  all pass.
- **No MySQL server was available in this environment**, so migration 033 and
  the changed queries are reviewed but not executed. Run
  `/install/` once after deploying (signed in as staff) to apply migration 033,
  and check Messages, Orders and Staff load before you rely on them.

## After you deploy

1. Visit `/install/`, sign in, run the pending update (migration 033).
2. Open **Staff** — if the shared-password warning appears, delete the sample
   accounts you don't use and set fresh passwords on any you keep.
3. Open **Settings > Email** and set the notification address for contact-form
   enquiries.
4. Confirm **Settings > Email &amp; checkout** shows checkout **off** unless you
   intend to give products away.
