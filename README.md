# TECHBISS — Premium Frontend Concepts (Phase 1)

Five frontend-only design concepts for TECHBISS, a global digital transformation
platform. Each is a single self-contained HTML file — no build step, no backend —
that simulates a full multi-page site via a hash router, so navigating between
"pages" is itself part of the motion design.

Every concept implements the same 14-view site (Home, Services, Solutions,
Marketplace, Product Details, Work, Process, About, Resources, Pricing, Contact,
Login, Installer, Dashboard preview) with a real interactive Marketplace
(browse → preview → customize → purchase → deploy, simulated client-side) and
Installer (select → detect → configure → import/migrate → install → verify →
launch, simulated client-side), plus an on-brand dark/light toggle. Only the
visual identity, typography, layout system, and motion language differ.

| # | Concept | Identity | File |
|---|---------|----------|------|
| 1 | **Obsidian** | Brutal luxury — warm near-black + brass, Fraunces/Archivo, slow cinematic mask-reveals | `concepts/01-obsidian/index.html` |
| 2 | **Aurora** | Fluid glass — porcelain + amber/teal/periwinkle, Bricolage Grotesque/Manrope, morphing glass & liquid transitions | `concepts/02-aurora/index.html` |
| 3 | **Monolith** | Kinetic editorial — black/white + a highlighter-yellow accent, Big Shoulders Display/IBM Plex, scroll-scrubbed Swiss grid | `concepts/03-monolith/index.html` |
| 4 | **Orbital** | Deep-space telemetry — ink blue + phosphor amber, Unbounded/Hanken Grotesk/JetBrains Mono, canvas starfield + HUD panels | `concepts/04-orbital/index.html` |
| 5 | **Prism** | Radiant enterprise — paper white + precise ink, Instrument Serif/Plus Jakarta Sans, spectrum used only as a signature edge-light detail | `concepts/05-prism/index.html` |

Open any `index.html` directly in a browser, or serve the folder statically.
All content (services, marketplace modules, pricing, case studies, etc.) is
shared, illustrative TECHBISS marketing copy — consistent across all five
concepts so they're a fair comparison of execution, not five different
companies.

## Technical notes

- No frameworks, no build tools — hand-written HTML/CSS/JS per file.
- The only external requests are a Google Fonts stylesheet and, in some
  concepts, GSAP + ScrollTrigger from cdnjs (pinned versions). All icons,
  illustrations, and product mockups are inline SVG, CSS, or canvas — no
  external images.
- Each concept respects `prefers-reduced-motion`, is keyboard-operable
  (skip link, focus states, `aria-current`, focus management on route
  change, modal focus trapping), and targets WCAG AA contrast.
- Marketplace, Installer, Login, and Contact flows are entirely simulated
  client-side (`setTimeout`/`requestAnimationFrame`) — nothing calls a real
  backend.

## Next step

Phase 2 (backend, database, auth, marketplace/payments, installer/migration,
and live dashboards) begins once a concept is selected.
