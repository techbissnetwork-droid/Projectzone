# Self-hosted typefaces

Latin-subset variable `woff2` files, served from this origin so the platform
makes no third-party font request and nothing blocks first paint.

| File | Family | Axes | Role | Size |
| --- | --- | --- | --- | --- |
| `sora-var-latin.woff2` | Sora | `wght 400–700` | Display and headings | ~25 KB |
| `manrope-var-latin.woff2` | Manrope | `wght 400–700` | Body, UI and controls | ~24 KB |

Both are licensed under the SIL Open Font License 1.1, which permits
self-hosting and redistribution with the software.

- Sora — https://github.com/jonathanpierce/sora (Jonathan Pierce, Soft Foundry)
- Manrope — https://github.com/sharanda/manrope (Mikhail Sharanda)

Only the `latin` unicode range is included. To add another range, pull the
matching subset from the Google Fonts CSS API and add a `@font-face` block with
the correct `unicode-range` so browsers download only what a page needs.
