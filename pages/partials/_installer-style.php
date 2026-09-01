<?php
/**
 * Styles for the setup wizard.
 *
 * Inlined rather than linked because the installer runs before configuration
 * exists, so asset() and the base path helpers are not available yet — and a
 * relative stylesheet link breaks when the site lives in a sub-directory.
 */
?>
<style>
:root {
  --bg: #06070c; --card: #0c0e17; --elev: #11141f;
  --line: rgba(255,255,255,.08); --line-strong: rgba(255,255,255,.14);
  --text: #e9ecf6; --soft: #b4bccd; --muted: #838da3; --faint: #5d667b;
  --accent: #4f8cff; --accent-soft: #7aabff; --tint: rgba(79,140,255,.12);
  --ok: #34d399; --warn: #fbbf24; --bad: #f87171;
  --r: 14px;
}
* { box-sizing: border-box; }
body {
  margin: 0; min-height: 100vh; padding: clamp(1rem, 4vw, 3rem) 1rem;
  background: var(--bg); color: var(--text);
  font: 16px/1.65 "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  background-image:
    radial-gradient(ellipse 70% 40% at 50% -8%, rgba(79,140,255,.16), transparent 70%),
    linear-gradient(to right, rgba(255,255,255,.028) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255,255,255,.028) 1px, transparent 1px);
  background-size: auto, 64px 64px, 64px 64px;
}
.wrap { display: grid; place-items: start center; min-height: 100%; }
.card {
  width: min(100%, 640px); background: var(--card);
  border: 1px solid var(--line-strong); border-radius: 24px;
  padding: clamp(1.5rem, 1.1rem + 1.6vw, 2.4rem);
  box-shadow: 0 36px 90px -30px rgba(0,0,0,.8);
}
.brand { display: flex; align-items: center; gap: .6rem; margin-bottom: 1.75rem; }
.glyph {
  width: 32px; height: 32px; flex: none; border-radius: 9px;
  box-shadow: 0 6px 18px -8px rgba(79,140,255,.85);
}
.name { font-weight: 650; letter-spacing: .14em; font-size: 1.02rem; }

h1 { font-size: clamp(1.35rem, 1.15rem + .7vw, 1.65rem); letter-spacing: -.025em; margin: 0 0 .6rem; }
p { margin: 0 0 1rem; }
.muted { color: var(--muted); font-size: .93rem; }
.small { font-size: .82rem; }
code {
  font-family: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: .84em; background: rgba(255,255,255,.05); border: 1px solid var(--line);
  padding: .1em .38em; border-radius: 5px;
}
hr { border: 0; border-top: 1px solid var(--line); margin: 1.6rem 0; }

/* Progress ---------------------------------------------------------- */
.steps { display: flex; gap: .4rem; list-style: none; padding: 0; margin: 0 0 1.75rem; flex-wrap: wrap; }
.steps li {
  display: inline-flex; align-items: center; gap: .45rem;
  font-size: .78rem; color: var(--faint); padding: .3rem .6rem .3rem .3rem;
  border-radius: 999px; border: 1px solid transparent;
}
.steps .pip {
  width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center;
  border: 1px solid currentColor; font-size: .68rem; font-variant-numeric: tabular-nums;
}
.steps .is-current { color: var(--accent); background: var(--tint); font-weight: 600; }
.steps .is-done { color: var(--ok); }
.steps .is-done .pip { border-color: transparent; background: rgba(52,211,153,.14); }

/* Requirement checks ------------------------------------------------ */
.check-list { display: grid; gap: .1rem; margin: 1.5rem 0; }
.check {
  display: flex; align-items: flex-start; gap: .7rem;
  padding: .55rem .2rem; border-bottom: 1px solid rgba(255,255,255,.045);
  font-size: .9rem; color: var(--soft);
}
.check:last-child { border-bottom: 0; }
.check .mark {
  width: 20px; height: 20px; flex: none; border-radius: 50%; display: grid; place-items: center;
  font-size: .72rem; font-weight: 700;
}
.check.ok .mark { background: rgba(52,211,153,.14); color: var(--ok); }
.check.bad .mark { background: rgba(248,113,113,.14); color: var(--bad); }
.check.warn .mark { background: rgba(255,255,255,.05); color: var(--faint); }

/* Detected panel ---------------------------------------------------- */
.detected {
  margin: 1.5rem 0; padding: 1.1rem 1.2rem;
  border: 1px solid var(--line); border-radius: var(--r); background: rgba(79,140,255,.05);
}
.detected__title {
  font-size: .7rem; letter-spacing: .12em; text-transform: uppercase;
  color: var(--accent); font-weight: 650; margin-bottom: .75rem;
}
.detected dl { display: grid; grid-template-columns: auto 1fr; gap: .4rem 1rem; margin: 0 0 .6rem; font-size: .88rem; }
.detected dt { color: var(--muted); }
.detected dd { margin: 0; color: var(--text); word-break: break-all; }

/* Forms ------------------------------------------------------------- */
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.row .field--sm { max-width: 130px; }
@media (max-width: 540px) { .row { grid-template-columns: 1fr; } .row .field--sm { max-width: none; } }
.field { display: block; margin-bottom: 1.1rem; }
.field > span { display: block; font-size: .88rem; font-weight: 550; color: var(--soft); margin-bottom: .4rem; }
.field input {
  width: 100%; min-height: 46px; padding: .7rem .9rem; font: inherit; font-size: .93rem;
  color: var(--text); background: var(--elev); border: 1px solid var(--line); border-radius: 11px;
  transition: border-color .15s, box-shadow .15s;
}
.field input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--tint); }
.field input.bad { border-color: var(--bad); box-shadow: 0 0 0 3px rgba(248,113,113,.14); }
.field em { display: block; font-style: normal; font-size: .8rem; color: var(--faint); margin-top: .35rem; line-height: 1.5; }
.field em.err { color: var(--bad); }
.tag {
  font-size: .64rem; letter-spacing: .08em; text-transform: uppercase; font-weight: 650;
  background: var(--tint); color: var(--accent); padding: .12rem .4rem; border-radius: 999px; margin-left: .4rem;
}

.check-inline {
  display: flex; gap: .7rem; align-items: flex-start; cursor: pointer;
  padding: .85rem 1rem; margin-bottom: 1.1rem;
  border: 1px solid var(--line); border-radius: 11px; background: rgba(255,255,255,.018);
  font-size: .9rem; color: var(--soft);
}
.check-inline:hover { border-color: var(--line-strong); }
.check-inline input { margin-top: .25rem; width: 17px; height: 17px; accent-color: var(--accent); flex: none; }
.check-inline em { display: block; font-style: normal; font-size: .8rem; color: var(--faint); margin-top: .3rem; line-height: 1.55; }

.advanced { margin: 1.25rem 0; border-top: 1px solid var(--line); padding-top: 1rem; }
.advanced summary { cursor: pointer; font-size: .87rem; color: var(--muted); }
.advanced summary:hover { color: var(--text); }
.advanced[open] summary { margin-bottom: .9rem; color: var(--text); }

/* Buttons ----------------------------------------------------------- */
.actions { display: flex; gap: .6rem; align-items: center; margin-top: 1.6rem; flex-wrap: wrap; }
.actions--wide { display: grid; grid-template-columns: 1fr 1fr; }
@media (max-width: 460px) { .actions--wide { grid-template-columns: 1fr; } }
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: .45rem;
  min-height: 46px; padding: .7rem 1.3rem; border: 1px solid var(--line-strong); border-radius: 999px;
  background: rgba(255,255,255,.03); color: var(--text);
  font: inherit; font-size: .92rem; font-weight: 550; text-decoration: none; cursor: pointer;
  transition: background-color .18s, border-color .18s, transform .12s;
}
.btn:hover { background: rgba(255,255,255,.06); }
.btn:active { transform: translateY(1px); }
.btn--primary {
  background: var(--accent); border-color: transparent; color: #fff; font-weight: 600;
  box-shadow: 0 10px 26px -12px rgba(79,140,255,.5);
}
.btn--primary:hover { background: var(--accent-soft); }
.btn--quiet { background: none; border-color: transparent; color: var(--muted); }
.btn--quiet:hover { color: var(--text); background: rgba(255,255,255,.04); }
.actions .btn--quiet { margin-right: auto; }
.btn--bad {
  background: rgba(248,113,113,.12); border-color: rgba(248,113,113,.35); color: var(--bad); font-weight: 600;
}
.btn--bad:hover { background: rgba(248,113,113,.2); }

/* Alerts ------------------------------------------------------------ */
.alert {
  padding: .95rem 1.1rem; border-radius: 11px; font-size: .88rem; line-height: 1.6;
  margin: 1.1rem 0; border: 1px solid var(--line); color: var(--soft);
}
.alert strong { color: var(--text); }
.alert--bad { border-color: rgba(248,113,113,.3); background: rgba(248,113,113,.07); }
.alert--warn { border-color: rgba(251,191,36,.28); background: rgba(251,191,36,.06); }

/* Done -------------------------------------------------------------- */
.done-mark {
  width: 54px; height: 54px; border-radius: 50%; display: grid; place-items: center;
  background: rgba(52,211,153,.14); color: var(--ok); font-size: 1.5rem; margin-bottom: 1.2rem;
}
.done-list { margin: 1rem 0; padding-left: 1.1rem; color: var(--soft); font-size: .88rem; }
.done-list li { margin-bottom: .45rem; }

@media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
</style>
