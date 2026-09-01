<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Chrome for the pages that cannot use View::head().
 *
 * Four pages render their own <html> because they run when the normal shell
 * is not available: the installer (no database yet), the "not installed"
 * screen, the admin file-integrity failure (503, deliberately dependency
 * free), and the member verify notice. Each of them had grown its own
 * miniature design system - its own token names (--card, --good, --bad), its
 * own type stack, its own idea of what a button looks like.
 *
 * That is how the first screen anyone ever sees ends up being the one screen
 * the design never reached. These are not unimportant pages; the installer is
 * the entire first impression, and a 503 is read at the worst possible moment.
 *
 * So the tokens and the base components live here once, as a string, with no
 * dependency on the database, the config, or the stylesheet on disk. Pages
 * that can reach assets/style.css should use that instead - this exists for
 * the ones that genuinely cannot.
 */
final class Chrome
{
    /** Google Fonts links. $enabled is false for offline/air-gapped installs. */
    public static function fonts(bool $enabled = true): string
    {
        if (!$enabled) {
            return '';
        }
        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
            . 'family=Instrument+Serif&amp;family=Inter+Tight:wght@400;500&amp;'
            . 'family=JetBrains+Mono:wght@400;500&amp;display=swap">';
    }

    /**
     * Tokens plus the base components these pages actually use: a centred
     * card, headings, a table of checks, buttons, inputs, and the three
     * status colours. Deliberately small - this is a floor, not a framework.
     */
    public static function css(): string
    {
        return <<<'CSS'
:root{
  --bg:#07090E; --surface:#0E1219; --surface2:#141A25; --surface3:#1A2130;
  --border:rgba(255,255,255,.07); --border-2:rgba(255,255,255,.13);
  --text:#EDF0F6; --muted:#8A94A8; --dim:#727D95;
  --accent:#6E7BFF; --accent-soft:rgba(110,123,255,.14);
  --brass:#D8B26A; --up:#2ED3A0; --down:#FF6A5F;
  --bg-up:color-mix(in srgb,var(--up) 13%,var(--surface));
  --bg-down:color-mix(in srgb,var(--down) 13%,var(--surface));
  --bg-warn:color-mix(in srgb,var(--brass) 13%,var(--surface));
  --line-up:color-mix(in srgb,var(--up) 40%,transparent);
  --line-down:color-mix(in srgb,var(--down) 40%,transparent);
  --bg-accent:color-mix(in srgb,var(--accent) 13%,var(--surface));
  --line-accent:color-mix(in srgb,var(--accent) 40%,transparent);
  --on-down:color-mix(in srgb,var(--down) 62%,#FFFFFF);
  --font-display:'Instrument Serif','Iowan Old Style',Palatino,Georgia,serif;
  --font-sans:'Inter Tight',Inter,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
  --font-mono:'JetBrains Mono',ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
  --ease-out:cubic-bezier(.16,1,.3,1);
  color-scheme:dark;
}
@media (prefers-color-scheme:light){
  :root{
    --bg:#FAFAF8; --surface:#FFFFFF; --surface2:#F4F5F7; --surface3:#ECEEF2;
    --border:rgba(0,0,0,.09); --border-2:rgba(0,0,0,.16);
    --text:#12161F; --muted:#5C6578; --dim:#6E7686;
    --accent:#4F5CE8; --accent-soft:rgba(79,92,232,.10);
    --brass:#94702E; --up:#0D8762; --down:#D73E35;
    --on-down:color-mix(in srgb,var(--down) 30%,#000000);
    color-scheme:light;
  }
}
*{box-sizing:border-box;margin:0;padding:0}
strong,b{font-weight:500}
body{
  background:var(--bg); color:var(--text); font-family:var(--font-sans);
  font-size:15px; line-height:1.6; -webkit-font-smoothing:antialiased;
  min-height:100vh; min-height:100dvh; padding:24px;
  display:flex; align-items:center; justify-content:center;
}
/* The ruled ground, same as the site. Off on phones - see style.css. */
body::before{
  content:""; position:fixed; inset:-50%; z-index:-1; pointer-events:none;
  background-image:linear-gradient(var(--border) 1px,transparent 1px),
                   linear-gradient(90deg,var(--border) 1px,transparent 1px);
  background-size:64px 64px; opacity:.55;
  -webkit-mask-image:radial-gradient(circle at 50% 30%,#000 0%,transparent 62%);
  mask-image:radial-gradient(circle at 50% 30%,#000 0%,transparent 62%);
}
@media (max-width:720px){ body::before{display:none} body{padding:16px} }

.sheet{width:100%;max-width:640px}
.card{
  background:var(--surface); border:1px solid var(--border); border-radius:14px;
  padding:26px; box-shadow:inset 0 1px 0 rgba(255,255,255,.06);
}
/* Sans, matching every h1 the main stylesheet now sets (see style.css's
   .sec > h2 rule) - this was the last place still opening with Instrument
   Serif: the installer, the admin's file-integrity 503, and the "not
   installed" holding page all draw their heading from this one rule. */
h1{font-family:var(--font-sans);font-weight:600;font-size:30px;line-height:1.12;letter-spacing:-.01em}
h2{font-size:17px;font-weight:500;letter-spacing:-.01em;margin-bottom:14px}
.mark-logo{display:block;margin:0 auto 18px;border-radius:10px}
/* THE SAME MARK EVERY OTHER SCREEN SHOWS, NOW ACTUALLY THE SAME MARK.
   These pages used to open with assets/brand/logo.svg - a rounded-square
   candlestick badge with a gradient fill and a drop-shadow, left over from
   before the redesign. The real site has not shown that mark anywhere
   since: the header (View::brandMark()) draws three plain animated bars
   instead, no gradient, no glow. A visitor who saw the installer or the
   admin login first met a different app than the one they landed in a
   moment later. .logo-mark is that same bar mark, styleless enough to need
   nothing from the database - Chrome::mark() below draws it as a string,
   the same three <rect> elements View::brandMark() emits on every other
   page, so a page that cannot query custom_logo still shows the one mark
   the rest of the product agrees on. */
.logo-mark rect{transform-box:fill-box;transform-origin:50% 100%;animation:logoBar 2.4s var(--ease-out) infinite}
.logo-mark rect:nth-child(2){animation-delay:.2s}
.logo-mark rect:nth-child(3){animation-delay:.4s}
@keyframes logoBar{0%,100%{transform:scaleY(1)}50%{transform:scaleY(.45)}}
/* A centred mark over left-aligned text reads as unfinished, not
   intentional - confirmed by rendering it. These three pages are a
   single message on a card, not a document with a body of running
   text, so the centred treatment used elsewhere in the system for
   short single-message moments is right here too. */
body{text-align:center}
.card :is(ul,table,pre,textarea,.left){text-align:left}
.eyebrow{
  font-family:var(--font-mono); font-size:11px; letter-spacing:.18em;
  text-transform:uppercase; color:var(--dim);
}
.tagline,.muted{color:var(--muted);font-size:14px}
code,kbd,.mono,.num{font-family:var(--font-mono);font-variant-numeric:tabular-nums}
code{background:var(--surface2);border:1px solid var(--border);border-radius:5px;padding:2px 6px;font-size:13px;word-break:break-all}

table{width:100%;border-collapse:collapse;font-size:14px}
td,th{padding:9px 6px;border-bottom:1px solid var(--border);text-align:left}
tr:last-child td{border-bottom:0}
td small{color:var(--dim);display:block;font-size:12.5px}

.ok{color:var(--up);font-weight:500}
.fail{color:var(--down);font-weight:500}
.warn{color:var(--brass);font-weight:500}

.btn,button,input[type=submit]{
  font-family:inherit; font-size:14px; font-weight:500; cursor:pointer;
  background:var(--accent); border:1px solid var(--accent); color:#fff;
  padding:11px 20px; border-radius:10px; text-decoration:none; display:inline-block;
  min-height:44px; transition:background .18s var(--ease-out),transform .18s var(--ease-out);
}
.btn:hover,button:hover{background:color-mix(in srgb,var(--accent) 85%,#FFFFFF);transform:translateY(-1px)}
.btn.ghost{background:transparent;border-color:var(--border-2);color:var(--text)}
.btn.ghost:hover{border-color:var(--accent);background:var(--accent-soft)}

input,select,textarea{
  font-family:inherit; font-size:14px; width:100%; min-height:44px;
  background:var(--surface2); color:var(--text);
  border:1px solid var(--border); border-radius:9px; padding:10px 12px;
}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--accent)}
label{display:block;font-size:13px;color:var(--muted);margin:14px 0 6px}

.note{
  background:var(--surface); border:1px solid var(--border);
  border-left:2px solid var(--brass); border-radius:0 10px 10px 0;
  padding:12px 14px; font-size:13.5px; color:var(--muted); margin:14px 0;
}
.note.bad{border-left-color:var(--down)}
.note strong{color:var(--brass);font-weight:500}
.note.bad strong{color:var(--on-down)}

a{color:color-mix(in srgb,var(--accent) 78%,#FFFFFF)}
@media (prefers-color-scheme:light){a{color:var(--accent)}}
:focus-visible{outline:2px solid var(--accent);outline-offset:3px;border-radius:4px}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}}
CSS;
    }

    /** Everything between <head> and the page's own extra rules. */
    public static function head(string $title, bool $fonts = true, string $extra = ''): string
    {
        return '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES) . '</title>'
            . self::fonts($fonts)
            . '<style>' . self::css() . $extra . '</style>';
    }

    /**
     * The three-bar mark, as a string - no Database call, so a page that
     * cannot reach one yet (the installer, before step 4) or should not
     * depend on one (a 503, a "not installed" notice) can still open with
     * the actual brand mark instead of the pre-redesign badge these pages
     * used to fall back to. Identical output to View::brandMark()'s own
     * fallback branch (the one it draws when no custom_logo is set) - kept
     * as two copies rather than one shared source because unifying them
     * would mean this DB-free class taking a dependency on View, which
     * takes one on Database, which is the exact coupling this class exists
     * to not have.
     */
    public static function mark(int $size = 30, string $style = '', string $extraClass = ''): string
    {
        return '<svg class="logo-mark' . ($extraClass !== '' ? ' ' . htmlspecialchars($extraClass, ENT_QUOTES) : '')
            . '" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '"'
            . ($style !== '' ? ' style="' . htmlspecialchars($style, ENT_QUOTES) . '"' : '')
            . ' aria-hidden="true">'
            . '<rect x="2" y="9" width="4" height="10" rx="1.5" fill="var(--indigo, var(--accent))"/>'
            . '<rect x="10" y="4" width="4" height="16" rx="1.5" fill="var(--long, var(--up))"/>'
            . '<rect x="18" y="12" width="4" height="8" rx="1.5" fill="var(--brass)"/>'
            . '</svg>';
    }
}
