# Instrument design system — applied

**Round 2: the component layer.** Round 1 was tokens and wiring — colours reaching the page. That is not a design, and you were right to say so. This round restyles the actual components.

24 files modified, 2 added (`src/Chrome.php`, `tools/design-audit.py`). Audit: 24 passing, 0 failing.

## What round 2 added

**A component layer, targeting your real class names.** I counted the classes in your templates rather than inventing them — `.hint` appears 525 times, `.btn` 174, `.form-card` 67, `.badge` 43 — and restyled those: buttons (one filled per view, destructive as a border never a fill), cards and `form-card` accordions, tables (hairlines, sticky mono headers, no zebra striping), inputs and 44px checkbox rows, badges, segmented controls, stat blocks, messages, empty states, the trade ticket, and the evidence ledger. Appended last at equal specificity, so it wins on order without a single `!important`.

**Two errors that check caught.** I had written `.badge.up` / `.badge.pending` — your markup only ever emits `on`, `off`, `neutral`, `buy`, `sell`, so those rules would have matched nothing. And `ui.js` adds the class `in`, not `in-view`, so my `.reveal` rule was dead and every scroll reveal on the site would have stayed invisible or unanimated. Both fixed and re-verified: 63 of the styled classes are confirmed present in your markup, and the rest are components I deliberately introduced.

**The four standalone pages, which had no design at all.** The installer, the "not installed" screen, the admin 503, and the member-verify notice each render their own `<html>` because they run when the normal shell isn't available — and each had grown its own miniature design system with its own token names (`--card`, `--good`, `--bad`), its own type stack, its own idea of a button. The installer is your entire first impression and a 503 is read at the worst possible moment. They now share `src/Chrome.php`: one token block, the fonts, and the base components, with no dependency on the database or the stylesheet on disk.

**Role tints derived instead of literal.** `#12261a` was a dark GitHub-green wash still sitting behind `--up` after `--up` became mint — a hue clash you notice without being able to name, in four families across 57 places. They're now `color-mix` derivations of the tokens, so they track a repainted brand and every theme.

**Links stopped being a second blue.** The hover pair was still `#7cb8ff` / `#073e8f`, so a link and the button beside it were different hues and hovering changed which palette the page appeared to be in.

---

Run the audit yourself at any time:

```bash
python3 tools/design-audit.py --baseline /path/to/an/unmodified/copy
```

---

## What I found first

The token layer was **already in `assets/style.css`** when you sent this — the Instrument palette, the light/contrast/calm themes, the font-stack declarations, the ledger CSS. What was missing was everything that makes tokens actually reach a screen. Five things were each independently enough to hide the whole design:

### 1. The stylesheet was being repainted on every page load

`src/View.php` wrote this into a `<style>` block *after* `style.css`:

```php
:root { --accent: #2f81f7; --up: #3fb950; --down: #f85149; }
```

Later in the cascade wins, so every public page rendered in the old GitHub palette. Worse, it named three variables and left the rest — so `--accent-soft`, the hover tint *derived* from the accent, stayed indigo underneath a GitHub-blue button. Every button on the site was one palette with a hover state from another, and the stylesheet looked broken when the stylesheet was right.

Fixed: the defaults **are** the design tokens now, so an install that never touched the colour picker emits nothing and the stylesheet is left alone. When an operator has genuinely picked a colour, the tint that pairs with it is derived from the pick via `color-mix` instead of being left behind.

### 2. The fonts were never loaded

`--font-display: 'Instrument Serif'`, `--font-sans: 'Inter Tight'`, `--font-mono: 'JetBrains Mono'` — all three declared, none ever fetched. Every install has been rendering the fallback chain: a Palatino-class serif, the platform UI face, the platform mono. Legible, and not the design — and the tabular figures that stop a live price jittering as it ticks come from the mono.

Added to both heads, behind a new `webfonts` setting (Admin → Appearance) because this app deliberately depends on no external service anywhere else.

### 3. Adding those fonts would have failed silently

`src/bootstrap.php` sends a CSP with `style-src 'self' 'unsafe-inline'` and **no `font-src` at all**, so fonts fell through to `default-src 'self'`. A `<link>` to `fonts.googleapis.com` would have been refused, the page would have rendered in the fallback chain, and the only evidence would be a console line nobody reads.

The CSP now names the font origins — and only while `webfonts` is on, so turning it off genuinely narrows what the page may load rather than just changing a preference. The header moved below `Database::boot()` so it can read the setting, following the precedent HSTS in that same file already set for exactly this reason.

### 4. Every PHP colour default still shipped the old palette

`src/Database.php` (installer seed), `admin/settings.php` (sanitiser *and* form inputs), `index.php`, `charts.php`, `page.php`, `performance.php`, `src/View.php` — 13 separate literals. All now resolve to one constant, `View::BRAND_DEFAULTS`, so the seed cannot drift from the stylesheet again.

### 5. Existing installs would have kept the old colours anyway

Changing a default only helps an install that never stored the setting. Yours has the old values sitting in the `settings` table from its own first boot, and stored beats default.

Added a migration in `Database::migrate()`. It only moves values **still equal to what the old palette shipped** — an operator who deliberately picked their own brand colour set it to something else, and a migration that overwrites a deliberate choice is a bug report, not an upgrade. Verified against four cases including "accent chosen, rest legacy".

---

## The admin panel was untouched

`admin/admin.css` was still entirely on the GitHub palette. An operator moved between GitHub-dark admin pages and Instrument member pages on the same install, which reads as two products.

Ported to the same tokens at tier-3 density: `--dur` remapped to 120ms wholesale so every transition inherited from a shared component drops to instant without editing the component, no serif anywhere, all numbers mono and tabular. Light and contrast themes now reach the panel too — they were public-site only, so an operator on light mode got a near-black admin.

**Note:** your admin was converted from a sidebar to a horizontal top bar at some point (`.admin-shell { display: block }` at what is now line ~845 supersedes the grid above it). My earlier mockup assumed a sidebar; I've left your top bar alone and themed it. The `208px` grid rule and its media query above are dead CSS as a result.

---

## Colour sweep

118 legacy literals → Instrument equivalents across 126 files, then 9 more caught by the audit. Three were **not** blanket-replaced, because context mattered:

- `volume: '#30363d'` in `assets/app.js` — a volume bar is data, not a rule line. Mapped to the hairline it would have become invisible on near-black, which is the one thing a volume pane cannot do. Now `rgba(138,148,168,.28)`.
- Inline `<style>` blocks in `admin/_admin.php`, `src/Setup.php`, `upgrade.php`, `install.php` got `#181A1F` — the opaque equivalent of the 7% hairline composited over the new ground, since a translucent border stacks differently against a nested surface.
- `src/Mailer.php` / `src/TradeMail.php` keep their **light** ramp deliberately: most mail clients render on white and dark-mode email is unreliable. But the CTA was a `linear-gradient(#2563eb, …)` — Outlook drops gradients and falls back to `background-color`, so the button was one colour in Gmail and another in Outlook. Flattened to one brand colour that renders the same everywhere.

---

## Accessibility — two real failures found

The audit measures every token against its surface in both themes.

- **`--dim` failed AA for small text in both themes.** `#5C6578` was 3.21:1 on `--surface` — fine for a border or a heading, under the 4.5:1 bar for the 11–12px captions `--dim` exists for. It was the one token in the palette failing the standard the rest of it passes, and it failed on the smallest type on the site. Now `#727D95` (4.54:1) dark, `#6E7686` (4.57:1) light.
- **Three light-theme tokens were carried over from the dark set and tuned by eye.** `--up` measured 3.41:1 on white; a `+2.14%` set in it is 12px text. `--brass` 4.23:1, `--down` 4.46:1. All three re-solved to ≥4.5:1 at the same hue.

The audit also caught **a regression I introduced**: sweeping the old `--dim` value globally also hit light-mode `--muted`, costing it two points of contrast. Restored.

---

## Mobile

Your mobile posture was better than I expected — 40+ breakpoints, `pointer: coarse` blocks, table overflow wrappers, and the `--fs-micro/tiny/small` phone-floor tokens. Four gaps:

- **`100vh` in 8 places, `100dvh` in one.** On iOS Safari and Android Chrome `100vh` excludes the browser chrome, so a `min-height:100vh` shell is taller than the visible area: the page scrolls with nothing to scroll to and sticky bars sit under the URL bar. All 9 now carry a `dvh` line after the `vh` fallback.
- **The admin panel had no phone floor at all** — an operator on a phone read 10px section labels. It now has the same named sizes as the public stylesheet, stepped up under 720px.
- **Admin controls were 30px on touch.** Now 44px minimum under `pointer: coarse` only, so the desktop keeps its density.
- **Admin had one reduced-motion block** that predated the dropdown carets and toggle knobs. Now covers the whole shell.

### One aesthetic change, flag it if you disagree

The ambient layer was two 520px radial gradients at `blur(90px)`, animating on 26s and 32s loops, forever, on every page. I replaced them with the blueprint grid from the spec and turned it off entirely under 720px.

The reason is performance before taste: a large blurred layer can't be cached as a static texture while it's being transformed, so the compositor re-rasterises a 520px blur every frame for as long as the page is open, whether or not anyone scrolls. On a mid-range phone that's measurable battery drain competing for the same compositor time as the scroll it sits behind — the decoration makes the content it decorates feel worse. Secondarily, blurred gradient blobs are the house style of every AI-SaaS landing page of the last three years, which is the thing the palette was changed to get away from.

`git checkout assets/style.css` and re-apply the token block if you want the orbs back.

---

## What I could not verify, and what you should do

I have **no PHP binary and no browser** in this sandbox. So:

1. **Run `php -l` on the 15 edited PHP files.** What I did instead was a differential structural check — braces, parens, and `<?php`/`?>` pairs compared against your untouched copy, all unchanged. That proves my edits didn't unbalance anything; it does **not** prove the PHP is valid. My first attempt at a from-scratch brace counter reported 33 files broken because it stripped `//` out of `https://` as a comment — worth knowing before you trust any similar check.
2. **Load one page and confirm the fonts arrive.** DevTools → Network, filter `font`. If they're blocked, the CSP change didn't take — check that `bootstrap.php` reaches the new header block.
3. **Hard-refresh.** `style.css` is cache-busted by `filemtime`, but the font CSS is not.
4. **Check `charts.php` on a real phone.** It's your densest page and the one I could least verify statically.
5. If you use the colour picker, set it back to `#6E7BFF` to return to the palette — anything else now correctly emits an override.
