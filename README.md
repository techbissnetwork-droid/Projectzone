# TECHBISS — Premium Website Concepts

TECHBISS builds websites, web applications, and digital systems, and helps
traditional/offline businesses move online. This repository contains three
completely independent, fully-built design directions for the TECHBISS
marketing website — each one a real, navigable, multi-page static site.

Open `index.html` in a browser (or serve the repo root with any static file
server) to reach the concept chooser and jump into any of the three.

## Concepts

| # | Directory | Direction |
|---|-----------|-----------|
| 01 | `concept-01-futuristic-luxury/` | **Futuristic Luxury Tech** — cinematic, dark, gold + electric-cyan accents, Space Grotesk display type, slow deliberate motion. |
| 02 | `concept-02-minimal-executive/` | **Minimal Executive** — light editorial, serif display type, huge whitespace, restrained micro-interactions, magazine-grade grid. |
| 03 | `concept-03-immersive-digital/` | **Immersive Digital** — deep-space gradients, layered 3D depth, pinned scroll storytelling, tilting cards, bold Space Grotesk type. |

Each concept is fully self-contained: its own `assets/css/style.css` (design
tokens + components) and `assets/js/main.js` (motion system), and its own 16
pages, none of which are shared across concepts. Nothing is copy-pasted
between directions — each has its own visual identity, layout system,
typography and interaction language, per the brief.

## Pages (identical structure across all 3 concepts)

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

## Stack & principles

- Plain static HTML/CSS/JS — no build step, deployable anywhere as-is.
- Motion: [GSAP](https://gsap.com/) (+ScrollTrigger) and
  [Lenis](https://lenis.darkroom.engineering/) smooth scroll, loaded from
  CDN, initialized defensively behind `prefers-reduced-motion` checks.
- No stock photography — visual richness comes from typography, gradients,
  inline SVG and layered composition, matching the "premium, not template"
  brief.
- Every animation is purposeful: reveals communicate hierarchy, hover states
  confirm interactivity, nothing spins or floats without reason.
