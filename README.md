# TECHBISS Website Concepts

Plain HTML/CSS/JS — no install, no build step, no dependencies. Requires only [Python 3](https://www.python.org/downloads/) (already installed on most Mac/Linux systems).

## Run it

- **Mac/Linux:** double-click `start-mac-linux.command` (first time, you may need to right-click → Open, or run `chmod +x start-mac-linux.command` in Terminal once).
- **Windows:** double-click `start-windows.bat`.
- **Manual (any OS):** `python3 serve.py`, then open the URL it prints (usually `http://localhost:8000`).

You can also just open `index.html` directly in a browser with no server at all — everything works except one thing: the Concept 04 admin/client demo (see below).

## What's here

- `index.html` — a selector page linking to all four design concepts.
- `concept-1-futuristic-luxury/`, `concept-2-executive-digital-empire/`, `concept-3-next-gen-digital-world/` — three full 15-page static site designs.
- `concept-4-soft-ui-control-panel/` — the selected direction, built out to 22 pages: the 15-page marketing site, a Marketplace page, a login hub, and a working **demo** of an admin panel that manages projects and a passwordless client portal.

## About the Concept 04 admin/client demo

This is an interactive **prototype**, not a real backend: no server, no database, no real email is involved. Everything (projects, sessions, login codes) is stored in your browser's `localStorage`/`sessionStorage` only — nothing leaves your machine.

Because of that, use the local server (above) rather than opening the files directly — some browsers (Firefox especially) don't share storage between pages opened straight from disk, which would make the admin → portfolio → client-login flow look broken even though it isn't. Chrome tends to work either way, but the server is the reliable option and is also how the finished site will behave once it's actually hosted.

To try it:
1. Open `concept-4-soft-ui-control-panel/admin-login.html`, sign in with any email/password/code (this mockup doesn't check credentials for real), and go to the **Projects** tab.
2. Create a project, filling in the client's email — turn on "Show on Portfolio" if you want it to also appear publicly.
3. Check `portfolio.html` — the project now appears there if you made it public.
4. Go to `client-login.html`, enter that same client email, and sign in with the on-screen demo code (or the "magic link" shortcut) — no password.
5. You'll land on `client-dashboard.html`, showing only that client's project details.

If you want this turned into a real, production system later (real accounts, a real database, real email delivery), that's a separate backend project — let me know and we can scope it.
