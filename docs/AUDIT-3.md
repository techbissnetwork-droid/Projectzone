# TECHBISS — third audit pass

A third full read of the codebase, driven end to end against a live
MariaDB again. This pass went looking specifically for the class of bug the
first two passes reasoned about but never *executed*: input that reaches the
filesystem or a query builder. It found one that matters, now fixed.

- 71 PHP files · `php -l` clean · `node --check` clean on `app.js`
- Every admin page, public route and API endpoint exercised on a live DB
- Installer's migrate-vs-fresh decision re-tested for the unauthenticated
  database-wipe hole — still closed
- Zero PHP fatals, warnings or notices anywhere

---

## 1. Found and fixed this pass — path traversal in product uploads (critical)

**Where:** `admin/products.php`, the `save` and `delete` actions.

**What:** A product id is only ever minted by `slugify_id()`, which emits
lowercase letters, digits and hyphens. But on an *edit* the id arrived from
the client as `existing_id`, and on a *delete* as `id`, and both flowed
straight into `glob()`, `unlink()` and `move_uploaded_file()` paths without
being checked against that shape.

**Impact:** A staff member holding only the narrow `products` permission —
not an owner, not full-access — could send

```
existing_id = ../../../config      + remove_download = 1
```

and the server would `unlink()` files well outside `assets/uploads/`:
`config.php`, `install.lock`, any uploaded asset. Deleting `config.php`
forces the site back into the installer; combined with a second request
that is exactly the unauthenticated-wipe path the installer already guards,
but the file deletion alone is a denial-of-service and a data-integrity
hole. The upload path had the mirror-image problem: a traversing id set the
destination basename for `move_uploaded_file()`.

**Proven, not asserted.** On the live instance, signed in as staff, a
sentinel file one to three directories above the uploads folder was deleted
by the request above. After the fix the same request leaves the sentinel
untouched and returns "That product could not be found," while a legitimate
edit of product `p1` still saves normally.

**Fix:** a single `product_id_is_safe()` check
(`^[a-z0-9][a-z0-9-]{0,63}$`, the exact shape `slugify_id()` produces),
applied at both entry points *and* inside the two upload helpers as defence
in depth, so a traversing id can never reach the filesystem regardless of
caller. All eight seeded product ids (`p1`–`p8`) and every slugified id pass
it, so no legitimate edit or delete is affected.

Branding uploads in `admin/settings.php` were checked for the same shape and
are safe — their destination basenames (`logo`, `favicon`, `social`) are
hardcoded literals, never client-supplied.

---

## 2. Re-verified sound this pass

- **SQL.** Every query in `admin/` and `api/` is parameterised. The only
  dynamic identifiers — `content.php`'s table and column names — come from
  the hardcoded `$SECTIONS` whitelist and are validated with
  `isset($SECTIONS[$section])`; row ids are int-cast. The marketing
  duplicate-phone `LIKE` binds a digits-only string, so no wildcard
  injection.
- **Access control.** `staff.php` is airtight: a non-owner cannot create an
  account, set anyone's password, change their own access level, edit the
  owner, or delete anyone. The dashboard gates every widget behind the same
  permission its section requires.
- **Auth & throttling.** Staff login throttles per email and per IP and
  refuses the correct password while throttled. Customer OTP caps codes per
  account and failures per hour, and the sign-in endpoints rate-limit per
  IP. All confirmed live.
- **CSRF / same-origin.** Every admin POST checks a session CSRF token;
  every JSON endpoint checks same-origin and 403s a foreign `Origin`.
- **Uploads.** Extension allowlist + `getimagesize()` for images, size
  caps, server-rebuilt filenames, and an `assets/uploads/*` `.htaccess` that
  turns off the PHP engine and blocks executable extensions. Downloads are
  token-gated, expiry- and count-limited, and `realpath()`-confined to the
  products folder.
- **Mailer.** Header values are stripped of CR/LF/NUL; the SMTP body is
  CRLF-normalised and dot-stuffed; STARTTLS verifies the peer.
- **Installer.** With data present and no lock, it *asks* migrate-or-fresh
  and runs neither for a signed-out visitor — the signed-out wipe attempt
  left the staff table intact and wrote no lock; the signed-in migrate wrote
  the lock and preserved the data. `is_installed()` still never rewrites the
  lock on its own.
- **Secrets.** `config.php` and `install.lock` are gitignored and untracked;
  no credentials or keys in the tree.

---

## 3. Still open — unchanged from AUDIT-2 §3

No new deliberate-deferral items this pass. The eight in `AUDIT-2.md` §3
(Stripe checkout, hardcoded Process/Solutions/Resources content, the
vestigial `plan` column, the UNIQUE business name, the paired-off migrations
029/030, the $5 default price, the stashed admin Projects page, and the
dead `.nav-burger` rule) all still stand and are all still the owner's call.
