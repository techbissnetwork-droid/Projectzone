# TECHBISS — audit round 4 (features · UX · UI · copy)

Where rounds 1–3 covered security and correctness, this pass is about the
product: what's missing, what's misplaced, what reads wrong, and the design
and copy polish. Everything was read in the code and, where visual, rendered
in a real mobile browser against a live database.

Two new features shipped this session first (multiple product images; the
whole marketplace card opening the product), then this audit.

---

## 1. Fixed this pass

| # | Was | Now |
|---|---|---|
| Pricing "$5" | Pricing hero showed a giant **"Starting from $5"** while the same page lists Build from $900 / Buy from $59 | Teaser aligned to the real floor **$59**; migration 035 lifts the stored value only when still the untouched $5 |
| Every product "/mo" | All 8 seeded products defaulted to `monthly`, so one-time themes/kits read **"$349 /mo"**, "$199 /mo"… | Seeded catalogue flipped to one-time (migration 036); new-product default is now **One-time**, dropdown lists it first |
| Priced button dead-ends | Product button said **"Get this for $59/mo"** but, with checkout off, led to "we can't sell online yet" | With checkout off the button now reads **"Enquire about this"** — no price it can't honour |
| Desktop "Site zoom" half-worked | `body{font-size:16px}` in absolute px ignored the `:root{font-size:X%}` zoom, so base text didn't scale | `body{font-size:1rem}` — base text scales with the setting like everything else |
| Wrong admin icon | Renewals "WhatsApp" link used the two-people icon | Uses the chat icon, matching the mail/phone row |
| `time_ago(null)` | A NULL `last_activity_at` hit `strtotime(null)` — a PHP 8.1 warning and "53 years ago" | Guarded — returns "never" |
| Product icon typos | Icon was free text; "carts" silently drew the fallback icon | A dropdown of the real public icon keys — can't miss |
| "Industries" vs "Solutions" | Admin → Content tab said **Industries**; the public page is **Solutions** | Tab and section label renamed **Solution(s)** so staff know what it drives |
| CTA drift | Header said "Book a call", in-page heroes said "Book a free call" | Standardised on **"Book a call"** |
| Redundant login copy | "…exactly where you left them — pick up right where you left off" (said twice) | "…all here — sign in to pick up where you left off." |
| Stale admin hint | Download-file help said "required for customers to receive something after buying" (nothing is "bought" with checkout off) | "the file a customer downloads once you deliver their order" |

Plus the two features: **multiple images per product** (gallery on the
product page, per-image management in admin) and **tapping a marketplace card
opens the product** (it was inert before).

---

## 2. Recommended next — feature gaps (your call)

1. **Customer self-service re-download.** Download links expire (30 days / 25
   uses), but the dashboard's "Download again" just re-sends the same stale
   token → a dead 410 page. Staff got a re-issue button; customers didn't.
   Add a "get a fresh link" action to the dashboard for the signed-in owner.
2. **Real support tickets for logged-in customers.** "Open a support ticket"
   (account) and "Contact us" (dashboard) drop signed-in clients on the
   anonymous contact form — it doesn't prefill their details and lands in
   `contact_messages` unlinked to their account, while a proper per-project
   ticket flow already exists but isn't offered here.
3. **Admin search + pagination.** Messages, Orders, Marketing, Businesses,
   Users and Tickets all render flat `LIMIT 500` tables with no search or
   paging. Fine now, a wall once the lists grow.
4. **A global "all projects" view** in admin (currently only reachable per
   business; not in the nav) — staff can't see everything in flight at once.
5. **Dashboard should hide "Download again"** for an order whose product file
   was later removed (it currently 404s), and should show a project ticket's
   *status* to the customer between opened and closed, not just its title.

## 3. Recommended next — misplaced / not worth keeping

6. **`businesses.plan`** (Starter/Growth/App+Web/Scale) is still a required
   dropdown that nothing reads since pricing plans were dropped in migration
   027. Remove or repurpose it.
7. **Resources page is entirely fake** — 8 hardcoded articles with "min read"
   figures and category chips, none real, none editable. Either wire it to
   editable content or cut it back to the working newsletter signup.
8. **Hardcoded public copy** with no admin control: the hero eyebrow and the
   three "float chips", the homepage "How we work" block, the Services "Ways
   to work with us" trio, the Process 5 stages, the Solutions timelines.
9. **Migrations 029↔030 cancel out**, 014 builds a table 027 drops, and
   `includes/default_content.php` still carries the dead `pricing` array.
   Harmless on migrated databases; churn on fresh installs.

## 4. Recommended next — remaining inconsistencies & one real bug

10. **Settings save is last-write-wins** (`admin/settings.php`): every submit
    writes all ~50 keys across all six tabs, so two admins editing different
    tabs silently clobber each other. The one genuine data-integrity bug left
    — worth scoping the save per tab.
11. Smaller drifts: the homepage teases a **4-step** process while `/process`
    says **five**; "Orders" is named three ways (nav "Orders", permission
    "Orders & newsletter", H1 "Orders & subscribers"); `noindex` is on 3 of 11
    admin pages; the recurring "go to contact" link has four different labels
    ("Talk to us" / "Start a conversation" / "Contact us" / "Get a free
    quote"). All cosmetic, all cheap to align.

---

## 5. Deploying this

Same rule as always — the change touches `assets/`, `admin/`, `index.php`
and adds migrations:

1. **Delete `assets/`, `admin/`, `install/` and `includes/` on the server,
   then upload fresh** — don't merge folder-by-folder.
2. Visit `/install/` signed in as staff and run the pending updates
   (migrations 034 gallery, 035 pricing teaser, 036 one-time products).
3. Check the admin dashboard — a red "upload looks incomplete" card means
   step 1 didn't take.
