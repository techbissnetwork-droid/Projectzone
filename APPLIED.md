# Instrument design system — applied

21 files changed, +517 / −170 lines. Audit: **0 failing, 0 warnings, 24 passing**
(`python3 tools/design-audit.py --baseline /path/to/old/copy`).

---

## What I found when I opened the zip

The token layer was **already in `assets/style.css`** — the Instrument palette, the
light/contrast/calm themes, the `--font-*` declarations, the motion tiers. So the
job was not "apply the design", it was "find out why it isn't showing up". Six
reasons, all of them wiring:

### 1. Every public page repainted itself back to the old palette

`src/View.php` wrote an inline `<style>` **after** the stylesheet:

```php
<style>:root { --accent: #2f81f7; --up: #3fb950; --down: #f85149; }
```

Later in the cascade, so it won. Worse, it named three variables and left the
rest, so `--accent-soft` — the hover tint derived from the accent — stayed
indigo while the button went GitHub blue. Every button on the site was one
palette with a hover state from another, and the stylesheet looked broken when
the stylesheet was right.

Fixed: the picker defaults **are** the design tokens now, so an install that
never touched them emits nothing at all and the stylesheet is left alone. When
an operator has genuinely chosen a colour, the paired tint is derived from
their pick with `color-mix` instead of being left behind.

### 2. The fonts were never loaded

`--font-display: 'Instrument Serif'` … and nothing anywhere requested it. Every
install has been rendering the fallback chain: a Palatino-class serif, the
platform UI face, the platform mono. The tabular figures that stop a live price
jittering as it ticks come from JetBrains Mono, so that was gone too.

Fixed: loaded in both heads, behind a new `webfonts` setting (Appearance), with
`display=swap`.

### 3. …and the CSP would have blocked them silently

`src/bootstrap.php` declared `style-src 'self' 'unsafe-inline'` and **no
`font-src` at all**, so fonts fell through to `default-src 'self'`. Adding the
`<link>` without touching this is worse than not adding it: the browser refuses
both, the page renders in the fallback, and the only evidence is a console line
nobody reads.

Fixed: `font-src` and `style-src` name the two Google origins **only while the
setting is on**. Turning webfonts off narrows the policy back down, so the
switch is a real reduction in what the page may load. The header moved below
`Database::boot()` because it now reads a setting — following the precedent the
file had already set for HSTS.

### 4. Changing defaults would not have reached a single existing install

Every install has the old colours **stored in the settings table** from its own
first boot, and stored beats default. Fixed: a migration in `Database::migrate()`
that moves only values still *equal to* what the old palette shipped. An
operator who deliberately picked their own brand colour keeps it — a migration
that overwrites a deliberate choice is a bug report, not an upgrade.

### 5. The admin panel was still entirely on the old palette

`admin/admin.css` opened with the full GitHub set. An operator moved between
GitHub-dark admin pages and Instrument member pages on the same install, which
reads as two products. Ported to the shared palette at tier-3 density: `--dur`
remapped to 120ms wholesale, mono/tabular numerals on every figure, light and
contrast themes reaching the panel for the first time, and the filled amber
warning replaced by a brass rule.

### 6. 118 legacy literals across 13 files

Swept, with the values chosen per context rather than by table — a volume bar
mapped to the hairline colour would have been invisible, so it went to muted at
28% instead.

---

## Accessibility — three real failures, one of them mine

The audit measures every token against its surface. Four came back under 4.5:1,
which is the bar for the small text these are actually used on (a `+2.14%` in
`--up` is 12px):

| token | was | now | on |
|---|---|---|---|
| dark `--dim` | #5C6578 · 3.21:1 | **#727D95** · 4.54:1 | `--surface` |
| light `--dim` | #8A94A8 · 3.05:1 | **#6E7686** · 4.57:1 | white |
| light `--up` | #0F9E72 · 3.41:1 | **#0D8762** · 4.51:1 | white |
| light `--brass` | #9A7530 · 4.23:1 | **#94702E** · 4.55:1 | white |
| light `--down` | #D93F35 · 4.46:1 | **#D73E35** · 4.54:1 | white |

And one regression the audit caught in my own work: my blanket sweep of the dark
`--dim` value also hit light-mode `--muted`, which happened to share the hex,
costing it two points of contrast. Restored. This is the argument for the audit
script existing rather than eyeballing a diff.

---

## Mobile

Your existing posture was better than I expected — 40+ breakpoints, `pointer:
coarse` blocks, table overflow wrappers, and the `--fs-*` phone-floor token
idea, which is the right pattern. Four gaps:

- **`100vh` → progressive `100dvh`** (8 declarations). On iOS Safari `100vh`
  excludes the URL bar from its subtraction, so a `min-height:100vh` shell is
  taller than the visible area: the page scrolls with nothing to scroll to and
  sticky bars sit under the chrome.
- **The admin panel had no phone floor at all** — 10px section labels, and a
  10.5px `data-label` that appears *only* on phones in the stacked-table view.
  Given the same `--fs-*` tokens and the same 720px step-up.
- **30px admin controls under a thumb.** Now 44px minimum, inside
  `@media (pointer: coarse)` so the desktop keeps its density.
- **Admin reduced-motion** covered one rule and none of the dropdown carets or
  toggles added since. Now a scoped universal block.

---

## One aesthetic change, flag it if you disagree

`body::before/::after` were two 520px radial gradients at `blur(90px)`,
animating on 26s and 32s loops, forever, on every page. I replaced them with the
blueprint grid from the spec and turned it off entirely under 720px.

The reason is performance before taste: a large blurred layer can't be cached as
a static texture while it's being transformed, so the compositor re-rasterises a
520px blur every frame for as long as the tab is open, whether or not anyone
scrolls — competing for the same compositor time as the scroll it sits behind.
The decoration was making the content it decorated feel worse, most on the
cheapest devices.

Secondary reason: blurred gradient blobs are the house style of every AI-SaaS
landing page of the last three years, which is the thing the palette was changed
to get away from.

If you want the orbs back, it's one block in `assets/style.css` under
`ambient layer` — the old code is in the git history of your own copy.

---

## What I could not verify

Be aware of the limits of the above:

1. **No PHP binary in my sandbox**, so `php -l` never ran. I substituted a
   *differential* check: every edited PHP file's brace / paren / `<?php`-tag
   balance is compared against your untouched copy, and all 15 are unchanged.
   That proves my edits didn't unbalance anything; it does not prove the files
   parse. **Run `php -l` on the 15 changed PHP files before deploying**, or just
   load the site once with `display_errors` on.
2. **No browser**, so nothing here is visually confirmed. The contrast numbers
   are computed, not observed; the layout is unrendered.
3. **`color-mix()`** in the derived accent tint needs Safari 16.2+ / Chrome 111+.
   It only appears when an operator overrides the accent, and the fallback is an
   untinted hover, so it degrades quietly.
4. **The admin layout is a top bar, not a sidebar.** I noticed mid-way that
   `admin.css` line ~782 globally overrides `.admin-shell` to `display:block` —
   you'd already migrated away from the sidebar. My earlier admin mockup assumed
   a sidebar and is wrong on that point; the CSS I wrote works with the top bar
   you actually have. The dead grid rules from the old sidebar are still in the
   file (lines ~90 and ~429) — worth deleting, but I left them rather than
   guess at what else references them.

---

## Suggested first run

```bash
php -l src/View.php && php -l src/bootstrap.php && php -l src/Database.php
python3 tools/design-audit.py --baseline ../your-old-copy
```

Then load `/` and `/admin/` on a phone with the network throttled, and confirm
the type is actually Instrument Serif / Inter Tight / JetBrains Mono rather than
the fallback — that single check tells you items 2 and 3 above both landed.
