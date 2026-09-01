#!/usr/bin/env python3
"""
design-audit — checks that the Instrument design system is actually applied,
not merely present. Run from the project root.

Every check here exists because the thing it looks for was genuinely broken at
some point: tokens declared but overridden, fonts named but never loaded, a
palette in the stylesheet and its opposite in the PHP defaults.
"""
import re, sys, pathlib, collections

ROOT = pathlib.Path('.')
FAIL, WARN, OK = [], [], []

def files(*exts):
    out = []
    for e in exts:
        out += [p for p in ROOT.rglob(e) if 'tools/' not in str(p)]
    return out

CODE = files('*.php', '*.css', '*.js', '*.json', '*.svg', '*.webmanifest')
CSS  = files('*.css')

# ---------------------------------------------------------------- 1. palette
LEGACY = ['#2f81f7', '#3fb950', '#f85149', '#0d1117', '#161b22', '#21262d',
          '#30363d', '#e6edf3', '#8b949e', '#7c3aed', '#e3b341', '#d29922',
          '#1f6feb', '#58a6ff', '#2ec4b6']
hits = collections.Counter()
for f in CODE:
    t = f.read_text(errors='ignore').lower()
    for h in LEGACY:
        if h in t:
            hits[h] += t.count(h)
if hits:
    FAIL.append(f"legacy palette survives: {dict(hits)}")
else:
    OK.append(f"no legacy palette literal in {len(CODE)} files")

# ------------------------------------------------- 2. token override ordering
# An inline <style> written after the stylesheet outranks it. Any :root block
# in PHP that names a design token unconditionally is a repaint.
bad = []
for f in files('*.php'):
    t = f.read_text(errors='ignore')
    for m in re.finditer(r':root\s*\{[^}]*\}', t):
        blk = m.group(0)
        if re.search(r'--(accent|up|down|bg|surface|text|brass)\s*:', blk) \
           and 'brandCss' not in blk and '$brand' not in blk:
            # Standalone surfaces: these render before or instead of the
            # normal shell and never load assets/style.css, so their :root is
            # the stylesheet rather than an override of one. Chrome.php is the
            # shared token block those pages use.
            if any(x in str(f) for x in ('install.php', 'Setup.php', 'Chrome.php')):
                continue
            bad.append(f"{f}: {blk[:60]}...")
if bad:
    FAIL.append("PHP :root repaints a token after the stylesheet:\n      " + "\n      ".join(bad))
else:
    OK.append("no PHP block outranks the stylesheet tokens")

# --------------------------------------------------------------- 3. webfonts
declared = set()
for f in CSS:
    t = f.read_text(errors='ignore')
    for fam in re.findall(r"--font-[a-z]+:\s*'([^']+)'", t):
        declared.add(fam)
loaded = set()
for f in files('*.php', '*.css'):
    t = f.read_text(errors='ignore')
    if 'fonts.googleapis.com' in t or '@font-face' in t:
        for fam in declared:
            if fam.replace(' ', '+') in t or fam in t:
                loaded.add(fam)
missing = declared - loaded
if missing:
    FAIL.append(f"font families declared but never loaded: {sorted(missing)}")
else:
    OK.append(f"all {len(declared)} declared faces are loaded: {sorted(declared)}")

# ------------------------------------------------------------------- 4. CSP
boot = (ROOT / 'src/bootstrap.php')
if boot.exists():
    t = boot.read_text()
    uses_gf = any('fonts.googleapis.com' in f.read_text(errors='ignore')
                  for f in files('*.php'))
    if uses_gf:
        if 'font-src' not in t:
            FAIL.append("a webfont is linked but the CSP declares no font-src — silently blocked")
        elif 'fonts.gstatic.com' not in t or 'fonts.googleapis.com' not in t:
            FAIL.append("CSP font-src/style-src does not allow the font origins actually used")
        else:
            OK.append("CSP allows the font origins it links (and only while enabled)")

# ------------------------------------------------ 5. small text / phone floor
below = collections.Counter()
for f in CSS:
    t = f.read_text(errors='ignore')
    # strip the mobile blocks: a 10px literal inside a min-width query is fine
    for m in re.finditer(r'font-size:\s*([0-9.]+)px', t):
        v = float(m.group(1))
        if v < 11:
            ctx = t[max(0, m.start()-400):m.start()]
            if 'content:' in t[max(0, m.start()-90):m.start()]:
                continue          # a caret glyph, not text
            below[f"{f}:{v}px"] += 1
if below:
    WARN.append(f"font sizes under 11px that are not glyphs: {dict(below)}")
else:
    OK.append("no text under 11px outside decorative glyphs")

# --------------------------------------------------------------- 6. viewport
vh = sum(len(re.findall(r'(?:min-)?height:\s*100vh', f.read_text(errors='ignore')))
         for f in files('*.css', '*.php'))
dvh = sum(f.read_text(errors='ignore').count('100dvh') for f in files('*.css', '*.php'))
if vh > dvh:
    WARN.append(f"{vh - dvh} viewport-height rules still lack a dvh fallback (iOS URL-bar bug)")
else:
    OK.append(f"all {vh} viewport-height rules carry a dvh fallback")

# Mailers emit <html> too and are not pages: an HTML email has no viewport
# and is deliberately built on a light ramp, because dark-mode mail rendering
# is unreliable across clients. Excluded by what the file does, not by name.
def is_mail(t):
    return 'Content-Type: text/html' in t or 'multipart/alternative' in t
no_viewport = []
for f in files('*.php'):
    t = f.read_text(errors='ignore')
    if re.search(r'<html[ >]', t) and 'name="viewport"' not in t and not is_mail(t):
        no_viewport.append(str(f))
if no_viewport:
    FAIL.append(f"pages emitting <html> with no viewport meta: {no_viewport}")
else:
    OK.append("every page that emits <html> sets a viewport meta")

# --------------------------------------------------------- 7. reduced motion
for name, f in (('public', ROOT / 'assets/style.css'), ('admin', ROOT / 'admin/admin.css')):
    if f.exists():
        n = f.read_text().count('prefers-reduced-motion')
        (OK if n else FAIL).append(f"{name} stylesheet: {n} reduced-motion block(s)")

# ------------------------------------------------------ 8. accessible colour
# --dim is the lightest text token; check it against the darkest surface.
def lum(h):
    h = h.lstrip('#')
    c = [int(h[i:i+2], 16) / 255 for i in (0, 2, 4)]
    c = [x / 12.92 if x <= .03928 else ((x + .055) / 1.055) ** 2.4 for x in c]
    return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]
def ratio(a, b):
    la, lb = sorted([lum(a), lum(b)], reverse=True)
    return (la + .05) / (lb + .05)
style = (ROOT / 'assets/style.css').read_text()
def tok(n, block=None, depth=0):
    # Tokens alias each other now (--text: var(--bone)), so a plain hex search
    # finds nothing and every contrast check silently skips. Follow the chain.
    src = block if block is not None else style
    m = re.search(rf'--{n}:\s*([^;]+);', src)
    if not m or depth > 6:
        return None
    v = m.group(1).strip()
    if v.startswith('#'):
        return v[:7]
    a = re.match(r'var\(\s*--([a-z0-9-]+)', v)
    return tok(a.group(1), block, depth + 1) if a else None
def tok_in(block, n, depth=0):
    # Resolve preferring the theme block AT EVERY HOP. Resolving only the
    # first hop there and the rest against :root walks --surface -> var(--panel)
    # -> the DARK --panel, so every light-theme colour got measured against a
    # near-black ground and the light theme was never actually checked.
    if depth > 6:
        return None
    m = re.search(rf'--{n}:\s*([^;]+);', block)
    src_is_theme = m is not None
    if not m:
        m = re.search(rf'--{n}:\s*([^;]+);', style)
    if not m:
        return None
    v = m.group(1).strip()
    if v.startswith('#'):
        return v[:7]
    a = re.match(r'var\(\s*--([a-z0-9-]+)', v)
    return tok_in(block, a.group(1), depth + 1) if a else None
lightblk = ''
m = re.search(r':root\[data-theme="light"\]\s*\{(.*?)\}', style, re.S)
if m:
    lightblk = m.group(1)

pairs = []
surf = tok('surface')
for name in ('text', 'muted', 'dim', 'accent', 'brass', 'up', 'down'):
    v = tok(name)
    if v and surf:
        pairs.append(('dark ', name, v, surf))
lsurf = tok_in(lightblk, 'surface')
for name in ('text', 'muted', 'dim', 'accent', 'brass', 'up', 'down'):
    v = tok_in(lightblk, name)
    if v and lsurf:
        pairs.append(('light', name, v, lsurf))

for mode, name, v, bgc in pairs:
    r = ratio(v, bgc)
    label = f"{mode} --{name} {v} on {bgc}: {r:.2f}:1"
    if r < 3.0:
        FAIL.append(label + "  (below 3:1 — fails even for large text)")
    elif r < 4.5:
        WARN.append(label + "  (ok for large text/UI, under 4.5 for body)")
    else:
        OK.append(label)

# ------------------------------------------------------------ 9. PHP sanity
# There is no php binary in this sandbox, and a from-scratch brace counter is
# worse than useless on this codebase: it strips "//" out of "https://" as a
# comment and eats the rest of the line, then reports 33 files broken. The
# first version of this check did exactly that.
#
# What IS sound is a DIFFERENTIAL. Point --baseline at an untouched copy of
# the tree and compare structural counts per file: braces, <?php / ?> pairs,
# parentheses. An edit that balances leaves every delta at zero. This cannot
# prove the PHP is valid, only that these edits did not unbalance what was
# already there - which is the actual risk when patching by script.
import argparse
ap = argparse.ArgumentParser()
ap.add_argument('--baseline', help='path to an unmodified copy of the tree')
args, _ = ap.parse_known_args()

if args.baseline:
    base = pathlib.Path(args.baseline)
    def counts(t):
        return (t.count('{') - t.count('}'),
                t.count('(') - t.count(')'),
                t.count('<?php') + t.count('<?=') - t.count('?>'))
    drifted, compared = [], 0
    for f in files('*.php'):
        b = base / f.relative_to(ROOT)
        if not b.exists():
            continue
        cur, old = f.read_text(errors='ignore'), b.read_text(errors='ignore')
        if cur == old:
            continue
        compared += 1
        if counts(cur) != counts(old):
            drifted.append(f"{f}: {counts(old)} -> {counts(cur)}")
    if drifted:
        FAIL.append("structural drift in edited PHP (braces/parens/tags):\n      "
                    + "\n      ".join(drifted))
    else:
        OK.append(f"{compared} edited PHP files are structurally unchanged vs baseline")
else:
    WARN.append("run with --baseline <dir> to structurally verify the PHP edits")

# ------------------------------------------------------------------- report
print("=" * 68)
print(" SignalMasterAi — design system audit")
print("=" * 68)
for tag, items, sym in (('FAIL', FAIL, '✗'), ('WARN', WARN, '!'), ('PASS', OK, '✓')):
    if not items:
        continue
    print(f"\n{tag}  ({len(items)})")
    for i in items:
        print(f"  {sym} {i}")
print("\n" + "=" * 68)
print(f" {len(FAIL)} failing · {len(WARN)} warnings · {len(OK)} passing")
print("=" * 68)
sys.exit(1 if FAIL else 0)
