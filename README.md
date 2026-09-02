# TECHBISS — five design directions (2026)

Five complete, working homepage designs for TECHBISS. Not mockups — real pages
with real motion. Open `index.html` to see all five side by side, then pick one.

| # | Direction | Feel | Pick it if |
| --- | --- | --- | --- |
| 01 | **Aurora Glass** | Dark, calm, expensive | You want to look like the agency other agencies hire |
| 02 | **Kinetic Editorial** | Light, bold, magazine | Your buyers are business owners, not developers |
| 03 | **Signal Terminal** | Live control room | Your best argument is that you keep things running |
| 04 | **Liquid Chrome** | Loud, metallic, modern | You want maximum impact, especially on a phone |
| 05 | **Blueprint Stack** | Navy, technical, explanatory | Explaining "full web setup" is the hard part of your sale |

## Files

```
index.html                       chooser — previews all five
designs/01-aurora-glass/         each folder is one complete homepage
designs/02-kinetic-editorial/
designs/03-signal-terminal/
designs/04-liquid-chrome/
designs/05-blueprint-stack/
```

## Running it

No build step, no dependencies. Open `index.html` in a browser, or:

```sh
php -S localhost:8000     # or: python3 -m http.server 8000
```

Uploads to cPanel by FTP exactly as it is.

## What's in every one of them

Same content, five treatments: hero, the ten services, the four-step process,
proof and numbers, client quotes, pricing, closing call to action, footer.

All five are responsive down to phone width, work with the keyboard, and stop
animating for anyone whose system asks for reduced motion.

## Notes

- Prices (`$1,400` / `$3,900`) and stats (`99.98%`, `140+`) are placeholders.
  Replace them with your real numbers before this goes near a customer.
- Quotes are illustrative — swap in real ones, with permission, before launch.
- Fonts load from Google Fonts. Each design also names fallback fonts, so the
  page still looks right if that request is slow or blocked.
- Once you pick one, the next step is wiring it to the existing PHP admin so
  the text is editable without touching HTML.
