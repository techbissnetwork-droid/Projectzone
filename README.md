# TECHBISS — company website

Static marketing site for TECHBISS: we build websites and apps, and take offline
businesses online — domain, hosting, SSL, business email, SEO and support, all
handled by one partner.

Built on the **"Pulse"** design system: dark ground, lime/cyan accents, Unbounded
display type, animated ambient field, magnetic buttons, tilt cards and scroll-driven
process rail.

## Pages

| File | Purpose |
| --- | --- |
| `index.html` | Home — hero, 10-service bento grid, offline→online story, process rail, industries, stats, testimonials |
| `services.html` | All ten services in detail, plus FAQ |
| `industries.html` | Eight sectors and what the setup looks like in each |
| `pricing.html` | Build packages, care plans, full comparison table, add-ons, pricing FAQ |
| `about.html` | Why the company exists, how it works, team, tech stack |
| `contact.html` | Enquiry form, contact routes, pre-contact FAQ |
| `404.html` | Not-found page |

## Structure

```
assets/
  css/style.css   design system + every component, one file
  js/main.js      all interactions, each block guards for missing elements
  favicon.svg
```

No build step and no dependencies. Fonts come from Google Fonts; everything else
is local.

## Running locally

```sh
python3 -m http.server 8080
# then open http://localhost:8080
```

## Deploying

Any static host works — Netlify, Vercel, GitHub Pages, Cloudflare Pages, or plain
shared hosting via FTP. Upload the repository root as-is.

## Things to change before going live

- **Contact details** — `+1 000 000 0000` and the social links in the footer of every
  page are placeholders.
- **Contact form** — the site is static, so `assets/js/main.js` hands the enquiry to
  the visitor's mail client via `mailto:`. Point it at a real endpoint (Formspree,
  Netlify Forms, or your own handler) when a backend exists.
- **Prices** — the figures on `pricing.html` are illustrative; set your real numbers.
- **Stats and testimonials** — placeholder figures and quotes; replace with real ones.
- **Domain** — `sitemap.xml` and `robots.txt` assume `techbiss.com`.
