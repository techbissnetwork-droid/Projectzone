# TECHBISS — Premium 2026 Frontend Concepts (Phase 1)

Five distinct, frontend-only concepts for the TECHBISS platform. Each is a self-contained
single-page app (client-side hash routing, no backend, no build step) implementing the same
14 views — Home, Services, Solutions, Marketplace, Product Detail, Work/Case Studies, Process,
About, Resources, Pricing, Contact, Login, Installer, and an Admin/Client/Staff Dashboard
preview — with its own visual language and motion system. Open any `index.html` directly in a
browser, or use the live preview links below.

| # | Concept | Identity | Live Preview | Source |
|---|---------|----------|---------------|--------|
| 1 | **Obsidian Aerospace** | Mission-control HUD aesthetic — amber-on-obsidian, telemetry readouts, a scroll-pinned "flight path" through the company's operations | [Preview](https://claude.ai/code/artifact/ef13d412-ad4a-419d-a03b-87adf12e6fb8) | [`concepts/01-obsidian-aerospace/`](concepts/01-obsidian-aerospace/index.html) |
| 2 | **Ivory Editorial** | A luxury print journal reimagined as a living surface — warm ivory/bronze, kinetic serif typography, page-turn style transitions | [Preview](https://claude.ai/code/artifact/f191e8e0-7d93-4d64-9c31-4d4080ceeb31) | [`concepts/02-ivory-editorial/`](concepts/02-ivory-editorial/index.html) |
| 3 | **Quantum Grid** | A machined, isometric instrument on a living coordinate grid — cyan-on-navy, hard edges, quantized snap-and-overshoot motion | [Preview](https://claude.ai/code/artifact/ea9b9217-3e1f-44d7-990c-e7e5ca1e520f) | [`concepts/03-quantum-grid/`](concepts/03-quantum-grid/index.html) |
| 4 | **Terra Monolith** | Brutalist-luxury architecture — bronze-on-basalt, monumental type, slow scroll-pinned "walk" through a landmark building | [Preview](https://claude.ai/code/artifact/d446bbed-c33f-4432-97e7-6cb25decc113) | [`concepts/04-terra-monolith/`](concepts/04-terra-monolith/index.html) |
| 5 | **Nova Fluid** | Calm, organic fluid motion — emerald-on-near-black gradient-mesh backgrounds, momentum-driven interaction, liquid cursor | [Preview](https://claude.ai/code/artifact/a8946ae9-ba9f-47a5-9880-8d8a3b862339) | [`concepts/05-nova-fluid/`](concepts/05-nova-fluid/index.html) |

## What each concept includes

- All 14 views, wired with a client-side hash router and animated route transitions
- A once-per-session cinematic intro, respecting `prefers-reduced-motion`
- Dark/light theme toggle (persisted, cross-fading via CSS custom properties)
- Animated marketplace with category filtering, tilt/hover cards, and a mocked
  Preview → Customize → Purchase → Deploy flow
- A 7-step Installer wizard (Select → Detect → Configure → Import/Migrate → Install → Verify → Launch)
  with simulated progress and live log output
- A three-role (Admin/Client/Staff) dashboard preview with animated KPIs and charts
- Magnetic buttons, custom cursor interactions, and tilt cards on desktop pointers
  (cleanly disabled on touch devices)
- Fully responsive from 375px to large desktop, with an animated mobile nav

## Technical notes

- Pure HTML/CSS/JS — no framework, no build tooling
- Only external dependencies: a Google Fonts stylesheet per concept, and GSAP 3.12.5 +
  ScrollTrigger from cdnjs (pinned version)
- All visuals (textures, grids, charts, blobs, particles) are generated procedurally with
  CSS, inline SVG, or `<canvas>` — no external images
- Ambient/background animation loops pause via the Page Visibility API

## Phase 2

Per the original brief, no backend, database, auth, payments, or infrastructure has been
built yet. Once a concept is selected, Phase 2 covers the full backend, marketplace and
payments, installer/migration engine, Admin/Client/Staff dashboards, and production
infrastructure.
