# TECHBISS — audit round 5

Focused pass: verify this session's work (multi-image gallery, footer
hardcode, app.js guards, pricing) holds up, then a fresh read of the areas
earlier rounds covered less (OTP/email-change, installer, mailer). Seven
real issues found and fixed — several were gaps in fixes prior rounds
believed complete. Everything below was verified in code and, where it
mattered, against a live database.

---

## Fixed this pass

| # | Sev | Issue | Fix |
|---|-----|-------|-----|
| R1 | med | First paint called `doRender` directly, so a render exception on a **hard load or direct link** blanked the page — only client navigation went through `safeRender`. | Initial render uses `safeRender` too; a render error now shows the fallback panel, never a blank page. |
| R2 | med | `applyTheme()` dereferenced `#themeToggle` **before** the guard added last commit, and `initTheme()` calls it at load — a missing toggle still aborted the whole script. | Guarded inside `applyTheme`. |
| R3 | med | The mobile sheet's `ROUTES.forEach` did `mobileNav.appendChild` unguarded at load. | `if(mobileNav)` around the loop. |
| R4 | low | `doRender`'s nav sync hit `navLinksEl` / `mobileNav` / `#navLoginBtn` raw — under markup drift these threw into the fallback panel. | All guarded. |
| SEC1 | med | `install/index.php` `run_migrations` gated on the **lock file alone**. A redeploy that dropped the gitignored `install.lock` (schema still present) let an **anonymous** visitor run migrations and write the lock — the same N1 hole the other pre-lock actions were hardened against. | Routed through `install_guard_installed()` (installed-for-real, not lock alone). |
| L1 | med | The OTP per-hour **issue** and **failure** caps counted every purpose together. The email-change flow issues two codes per attempt, so a few retries could exhaust the budget and **lock the customer out of login**. | Both caps scoped per `purpose`. |
| R5 | low | A gallery-image save that failed on a filesystem error (uploads folder not writable) was reported with the green success flash. | Flash now says the product saved but the images didn't, with the reason. |

### Verified live
- **Markup-drift resilience**: with any of `#themeToggle`, `#mobileNav`,
  `#navLinks`, `#navBurger`, `#dockMenuBtn` missing, the page still renders
  fully (23,333 chars, **0 JS errors**). Before R2/R3 a missing toggle or
  sheet blanked it.
- **SEC1**: anonymous `run_migrations` with the lock removed but the schema
  present is refused and writes no lock; a signed-in staffer still can.
- **L1**: after an email-change code burst, a `login` code is still
  issuable — the purposes no longer share a budget.

### Verified sound — no change needed
The gallery/footer/migration risks called out for this round all hold up:
- `remove_gallery` is scoped `WHERE product_id = ? AND id IN (…)` — a
  tampered id can only touch the edited product's own images (tested: editing
  p2 could not delete p1's image).
- Gallery files are unlinked by their stored, `realpath`-confined path before
  the cascading delete; traversal via `existing_id` is blocked; no orphans
  (tested end to end).
- Removing the primary image re-points `image_path` at the next one; no
  `sort_order` renumbering needed.
- Migrations 034/035/036 are idempotent (re-ran cleanly; no duplicates).
- The inlined footer `<style>` is static CSS with no interpolated data; the
  product-images query is grouped in PHP (no N+1) and falls back cleanly on a
  mid-upgrade missing table.

---

## Still open — unchanged, your call (from AUDIT-4 §2–4)

1. **Customer self-service re-download** — the dashboard "Download again"
   re-sends the original (expiring) token; no customer-facing re-issue.
2. **Account-linked support tickets** for signed-in customers (today they hit
   the anonymous contact form).
3. **Admin search + pagination** on the list screens.
4. **Settings save is last-write-wins** across all six tabs (the one
   remaining data-integrity bug — worth scoping per tab).
5. The deferred set: Stripe checkout, hardcoded Process/Solutions/Resources
   copy, vestigial `businesses.plan`, UNIQUE business name.
