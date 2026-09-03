# TECHBISS

A premium, multi-page digital transformation platform: marketing site, a digital marketplace for ready-made websites/themes, an Advanced Installer for deploying them, and role-based Admin/Client/Staff portals.

Built with Next.js 16 (App Router), TypeScript, Tailwind CSS v4 and Framer Motion.

## Getting started

```bash
npm install
npm run dev
```

Open [http://localhost:3000](http://localhost:3000).

## What's here

- **Marketing pages** — Home, Services, Solutions, Work (case studies), Process, About, Resources, Contact — all real routes under `src/app`, several with dynamic sub-pages (`/work/[slug]`, `/resources/[slug]`).
- **Marketplace** — `/marketplace` browse/search/filter, `/marketplace/[slug]` product detail with a live device-preview mock, `/marketplace/cart`, and a multi-step `/marketplace/checkout`.
- **Advanced Installer** — `/installer`: URL/environment auto-detection, fresh install vs. migrate-existing-site vs. import-from-backup, guided configuration, and a live deploy log.
- **Auth & dashboards** — mock, localStorage-backed role auth at `/login/{admin,client,staff}`, each with a protected dashboard under `/dashboard/{role}` (multiple real sub-routes: products, billing, support, clients, marketplace orders, staff directory, settings).

## Structure

```
src/
  app/            routes (App Router)
  components/     ui/, layout/, and per-feature component folders
  lib/            data/ (mock content), contexts (auth, cart), utils, types
```

Content (products, case studies, team, articles, etc.) lives in `src/lib/data/*` as typed arrays — edit there to change what's shown across the site.
