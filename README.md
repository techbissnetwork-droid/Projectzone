# TECHBISS — website

Marketing site for TECHBISS, a digital transformation company that moves businesses
from offline operations to a complete online ecosystem.

**No build step. No framework. No dependencies.** Open `index.html`, or serve the
folder statically — that is the whole deployment story.

```bash
python3 -m http.server 8000     # then open http://localhost:8000
```

## Structure

```
index.html            single page, semantic sections, JSON-LD, OG/Twitter metadata
assets/css/base.css   design tokens, reset, typography, buttons, nav, cursor
assets/css/main.css   the nine sections + footer, and their responsive rules
assets/js/site.js     nav, menu, cursor, reveals, magnetics, tilt, scroll-driven sections
assets/js/viz.js      three canvas visualisations (hero ecosystem, architecture, CTA field)
assets/img/           favicon
robots.txt, sitemap.xml
```

## The narrative

The page is one argument, in order: **business → transformation → technology → build →
launch → grow.**

| # | Section | What carries it |
|---|---------|-----------------|
| 01 | Hero | Interactive ecosystem: a TECHBISS core with nine orbiting nodes (Business → Domain → Website → App → Hosting → Email → Security → Payments → Growth), DOM labels over a canvas of edges, travelling pulses and cursor-reactive parallax |
| 02 | Offline → Online | Pinned, scroll-driven: OFFLINE → DIGITAL → ONLINE → GROWING, each stage a different composition (paper records → a conversion ledger → a live storefront → compounding metrics) |
| 03 | Services | Nine modules in a deliberately uneven bento; hover or click expands one in place |
| 04 | Architecture | Canvas system diagram, four layers, animated data packets, live-looking status panel |
| 05 | Process | Pinned five-stage timeline: the active step becomes dominant, the rest collapse to a line |
| 06 | Work | Four case studies, four different compositions, each with a CSS/SVG animated preview |
| 07 | Transformation | Six business types, each morphing into its digital counterpart |
| 08 | Trust | Six infrastructure pillars with animated technical indicators |
| 09 | CTA + footer | Full-bleed closing statement over a drifting particle field |

## Design system

Deep near-black foundation (`#06070A`), one restrained signal blue (`#8FB0FF`) with a warm
counterpoint (`#E7BB8D`), hairline borders, controlled glow. No neon, no rainbow gradients.
Display type is Inter Tight with tight negative tracking; body is Inter; small technical
labels use the system monospace stack. All tokens live at the top of `base.css` — change
them there and the whole site follows.

## Animation

One `requestAnimationFrame` scroll bus drives every scroll-reactive section; canvases run
their own loops, gated by `IntersectionObserver` and `visibilitychange` so nothing animates
off-screen or in a background tab. Only `transform` and `opacity` are animated.

`prefers-reduced-motion: reduce` is fully honoured — reveals resolve immediately, loops are
replaced with a single static frame, and the custom cursor is disabled.

## Responsive

Desktop, laptop, tablet and mobile each get their own composition, not a scaled-down one.
The architecture diagram re-lays out into two columns on phones; the transformation list
becomes a snap carousel; the service grid recomposes at 1100px and again at 720px; the hero
moves the ecosystem below the headline. Verified at 390 / 768 / 1024 / 1512 px with zero
horizontal overflow.

## Performance

~40 KB gzipped total for HTML + CSS + JS. No images, no icon font, no runtime dependency —
every graphic is inline SVG, CSS, or canvas. Fonts load non-render-blocking from Google
Fonts with a full system fallback stack; self-host `assets/fonts/` if you would rather not
depend on a third party.

## Before going live

- Replace `https://techbiss.com/` in the canonical URL, JSON-LD, OG tags and `sitemap.xml`.
- Add `assets/img/og.png` (1200×630) — the OG tags already point at it.
- Point the footer social links at the real profiles.
- **The four case studies in section 06 are illustrative**, labelled as such on the page.
  Swap in real projects and real numbers before publishing, or keep the disclaimer.
