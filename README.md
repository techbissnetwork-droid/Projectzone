# TECHBISS — Premium Website

TECHBISS builds websites, web applications, and digital systems, and helps
traditional/offline businesses move online — "we turn businesses into
digital businesses." This repository is the design workspace for the
TECHBISS marketing site: 14 fully-built home page concepts were explored
across four rounds of iteration, and one — **Before / After Story**
(`concept-14-before-after/`) — has been selected and is being built out
into the complete 16-page site.

Open `index.html` at the repo root to browse the current build and the
full concept archive.

## Current build: Before / After Story

`concept-14-before-after/` is the active, complete site. Its hero puts a
draggable before/after comparison slider front and center, making the
"we turn businesses into digital businesses" promise literal rather than
just a tagline — visualizing a real business's transformation from an
analog storefront to a fully digital one. Every other section (services,
digitization journey, work, process) carries the same before/after thread
through in copy and layout.

### Pages

1. `index.html` — Home
2. `about.html` — About TECHBISS
3. `services.html` — Services (hub)
4. `websites.html` — Websites
5. `web-applications.html` — Web Applications
6. `business-digitization.html` — Business Digitization
7. `domain-hosting.html` — Domain & Hosting
8. `ssl-email.html` — SSL & Business Email
9. `solutions.html` — Solutions / Industries
10. `work.html` — Work / Projects
11. `case-study.html` — Case Study
12. `pricing.html` — Pricing / Packages
13. `process.html` — Process
14. `technology.html` — Technology
15. `contact.html` — Contact
16. `get-started.html` — Get Started

## Concept archive

13 earlier concepts remain in the repo as full, running home pages —
nothing was deleted. They're grouped below by the exploration round that
produced them.

| # | Directory | Direction |
|---|-----------|-----------|
| 01 | `concept-01-app-motion/` | **App Motion** — the validated base template: dark/vivid gradients, glow pill UI, animated app-dashboard mockup. Reference-validated against real product landing pages the user supplied. |
| 02 | `concept-02-fintech-signal/` | **Fintech Signal** — SignalMasterAi-inspired data-signal visual language. |
| 03 | `concept-03-storefront-gradient/` | **Storefront Gradient** — Code2Door-inspired commerce-forward gradient hero. |
| 04 | `concept-04-portfolio-pulse/` | **Portfolio Pulse** — iamtomley-inspired portfolio-style pulse motion. |
| 05 | `concept-05-app-shell/` | **App Shell** — bento grid + tab navigation, structurally distinct from the App Motion family. |
| 06 | `concept-06-split-scroll/` | **Split Scroll** — sticky visual synced to a scrolling narrative column. |
| 07 | `concept-07-command-palette/` | **Command Palette** — search-bar-led hero, developer-tool aesthetic. |
| 08 | `concept-08-journey-timeline/` | **Journey Timeline** — horizontal snap-scroll process spine. |
| 09 | `concept-09-light-premium/` | **Light Premium** — crisp white SaaS aesthetic, warm-white canvas. |
| 10 | `concept-10-monochrome-bold/` | **Monochrome Bold** — grayscale with one disciplined acid-lime accent. |
| 11 | `concept-11-warm-editorial/` | **Warm Editorial** — sunset gradient warmth, serif display type. |
| 12 | `concept-12-big-type/` | **Big Type** — kinetic typography hero, no dashboard mockup. |
| 13 | `concept-13-numbers-first/` | **Numbers First** — results-led hero, metrics as the visual. |
| 14 | `concept-14-before-after/` | **Before / After Story** ★ — draggable transformation slider hero. **Selected direction, full site in progress.** |

Each concept directory is self-contained: its own `assets/css/style.css`
(design tokens + components) and `assets/js/main.js` (motion system).
Only `concept-14-before-after/` has all 16 pages; every other concept is a
home page only, as originally scoped for comparison.

## Stack & principles

- Plain static HTML/CSS/JS — no build step, deployable anywhere as-is.
- Motion: [GSAP](https://gsap.com/) (+ScrollTrigger) and
  [Lenis](https://lenis.darkroom.engineering/) smooth scroll, loaded from
  CDN, initialized defensively behind `prefers-reduced-motion` checks and
  `typeof window.gsap/Lenis !== 'undefined'` guards — content never
  depends on the CDN succeeding to be visible or usable.
- No stock photography — visual richness comes from typography, gradients,
  inline SVG and layered composition, matching the "premium, not template"
  brief.
- Every animation is purposeful: reveals communicate hierarchy, hover states
  confirm interactivity, nothing spins or floats without reason.
