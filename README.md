# SignalMasterAi 📈

**signalmasterai.com**

A self-hosted PHP web app that fetches live charts from a **free market-data API**
(Binance public REST, no API key needed), analyses them with a server-stored
**technical-analysis knowledge base** — momentum, trend, volatility, candlestick
patterns and market structure — and publishes **BUY / SELL / NEUTRAL signals**
with the exact reasons each signal fired.

> ⚠️ **Not financial advice.** All output is automated, educational chart
> commentary. The disclaimer is shown on every page and attached to every signal.

## What changed in this revision

A review of the whole codebase produced a set of accuracy, control and
interface changes. The ones that alter behaviour most:

- **Correlated rules no longer count several times.** RSI, Stochastic,
  Williams %R, CCI and MFI are the same oscillator information in five
  costumes; in an oversold market all five fired and contributed five times
  over for what a person would call one observation, and the trend family did
  the same by construction. Each evidence category's net vote is now capped
  before the categories are summed, so agreement *across* independent evidence
  is what moves the score. On a live BTC reading this took a score of −9.45 to
  −5.6, with the trend family clipped from −8.0 to −3.0.
- **Backtests test the engine that actually runs.** The dry engine was built
  without a market-data source, so higher-timeframe confluence and the Bitcoin
  regime filter — two of the highest-weighted rules — could never fire in a
  backtest, were never calibrated, and every published backtest described a
  rule set the site does not use. They now participate, served *as of* each
  simulated bar so they cannot read the future.
- **Measurement is honest about costs and uncertainty.** Every published R was
  gross; a configurable round-trip cost is now subtracted. Calibration buckets
  accepted eight samples and printed the raw proportion as "measured"; the
  floor is thirty and the figure shown is the Wilson lower bound. Candles that
  span both stop and target are resolved against finer candles where available
  instead of always assuming the loss.
- **Self-tuning stopped compounding.** Weights were multiplied into their own
  previous output, daily and weekly, so rules ratcheted into the clamps with no
  way back and each run measured data the previous run had shaped. Tuning is now
  relative to a stored baseline and validated on a held-out slice.
- **A learned model.** Every stored signal already carried its fired-rule vector
  and its outcome — a labelled dataset only hand-set weights were consuming. A
  pure-PHP logistic regression now fits it nightly and supplies a calibrated
  P(win) as confidence, staying silent until it beats the base rate out of sample.
- **Members control the engine.** Strategy profiles, personal rule weights,
  position sizing from account size and risk percent, alert filters (grade,
  confidence, direction, quiet hours, daily cap) and outbound webhooks.
- **Watchlists follow the member, not the browser.** They lived in localStorage
  and vanished on a second device, while the server kept mailing about pairs the
  member could no longer see.
- **New pages**: a market scanner over data the background scan was already
  producing and never showing, a paper portfolio with an equity curve, and a
  public status page.
- **The chart shows its work**: RSI and MACD sub-panes, VWAP, log scale, a
  candle-close countdown, past signals with their outcomes, and a per-category
  evidence meter that marks where the correlation cap bit.
- **Infrastructure**: settings are cached instead of re-queried per read, a real
  cache table replaces volatile rows in `settings`, the alert dispatcher takes an
  atomic lock (two concurrent visitors could both run the full sweep), the public
  API is rate limited, and candle history is pruned.
- **The chart is readable without sight.** A `<canvas>` is opaque to a screen
  reader; the visible window is now mirrored into a real table.
- **Non-crypto instruments.** The engine reads OHLCV and knows nothing about
  what produced it, so equities, FX and indices needed only a data adapter.
  Symbols are namespaced (`stooq:aapl.us`) and the crypto-only rules - funding,
  open interest, the Bitcoin regime filter - sit out automatically.
- **Stops are placed around liquidation clusters, not into them.** Leveraged
  positions are force-closed at an arithmetically fixed distance from their
  entry, so every busy candle seeds liquidation prices at knowable levels, and
  where the projections from many candles land together a cascade is waiting.
  A stop resting just short of one is the worst place to keep it: the cascade
  fills through it on the way to the level, and the reversal happens without
  you. The engine now estimates those pools from candles and volume, discards
  any level price has already traded through - a liquidation level is spent
  once it is reached, and without that filter the output is a list of levels
  consumed days ago - steps a stop past a strong cluster sitting just beyond
  it, and publishes the nearest pool ahead of the trade as the magnet. Drawn
  on the chart as bands rather than lines, because it is an estimate of what a
  paid heatmap feed sells, not a substitute for one.
- **Members can backtest.** The engine already replayed history to calibrate
  itself, but only the admin could see it; someone deciding whether to pay had
  the published record and nothing else.

Optional and off by default: an AI layer that describes a signal in plain
language (it never influences the decision), public broadcast channels, and
referral credit paid in premium days.

### Two bugs the testing found

Both were in work committed earlier in this same revision, and both were
invisible until a test was written specifically to provoke them:

- **The suppression gates were being silently overridden.** Chop, liquidity and
  confluence filters ran *before* hysteresis, which then restored the previous
  BUY/SELL whenever the score was still inside the exit band - so on any pair
  with an active verdict, every one of those filters did nothing. The original
  test passed only because that database had no prior verdict for the pair.
- **The level optimiser could leave production half-configured.** It applied
  each candidate by writing it into the settings table and restoring in a
  `finally`, which does not survive SIGTERM - as a timed-out test run
  demonstrated. Candidates now travel on the engine instance, so nothing global
  is written and there is nothing to restore.

## Features

- **Step-by-step installer** (`install.php`): requirements check → database
  (SQLite zero-config or MySQL) → admin account → site settings → email/SMTP
  (optional) → done. Locks itself after installation. Existing installs
  migrate automatically on the next request - new tables, rules and settings
  appear without reinstalling.
- **Email alerts** — members can opt in ("Also email me") and receive an
  email whenever a watched coin flips bullish/bearish, sent by the cron job.
  Dependency-free mailer with SMTP (STARTTLS 587 / SSL 465 / AUTH LOGIN) or
  PHP mail() fallback, configured in the installer or Admin → Settings, with
  a test-send button.
- **Live chart price** — while the site is open, a micro-cached ticker
  endpoint updates the forming candle and a live price chip every few
  seconds, independent of the candle cache.
- **Everything stored on your server**: OHLCV candles, every generated signal
  (full audit trail with indicator snapshots), the TA knowledge base, and all
  settings live in your own database.
- **Signal engine driven by an editable knowledge base** — 54 seeded rules
  covering the full standard indicator arsenal: RSI, MACD, EMA 20/50/200,
  Bollinger Bands, Stochastic, ADX (+DI/−DI), Ichimoku Cloud (position + TK
  cross), SuperTrend, Parabolic SAR, Williams %R, CCI, MFI, OBV, Donchian
  breakouts, candlestick patterns (engulfing/hammer/shooting star), volume
  confirmation, swing structure, support & resistance, RSI divergence,
  Fibonacci golden zone, multi-timeframe confluence, BTC regime for altcoins —
  plus **news sentiment** (per-coin and market-wide, scored from stored
  headlines), **funding-rate extremes** (contrarian, free Binance futures
  data) and the **Fear & Greed index** (contrarian at ≤20 / ≥80). Each rule
  has a weight (0–5) and can be toggled. Rules are grouped into evidence
  categories whose net contribution is **capped**, so five correlated
  oscillators agreeing counts once rather than five times, and a verdict
  requires agreement across independent categories. New rules can be built from
  the admin without touching code.
- **Accuracy engine** — analyses **closed candles only** (no repaint from the
  forming bar); ADX regime filter dampens counter-regime rules; **flip
  hysteresis** holds an active BUY/SELL through borderline score wobbles;
  **stop-out cooldown** stands down after a fresh stop-loss hit; a **caution
  window** before High-impact calendar events raises thresholds and caps the
  setup grade at B; setup grades (A+/A/B/C) from multi-category confluence.
- **Self-learning + backtesting** — every BUY/SELL is verified against the
  price action that followed (TP1 before stop, break-even after TP1, TP2,
  time stop) and stores its achieved **R-multiple**; a daily auto-tuner
  nudges rule weights toward measured expectancy and learns **per-timeframe
  multipliers** (a rule can be strong on 1d and muted on 15m). The built-in
  **backtester** (Admin → Knowledge base) replays stored candle history to
  calibrate everything on day one instead of waiting weeks for live outcomes.
- **Signals are fully automatic** — analysed on page load, on every symbol or
  timeframe change, and re-analysed live on an admin-configurable interval
  (default 60 s, paused when the tab is hidden). `cron.php` also re-analyses
  every recently-viewed pair in the background. History only records a row
  when the verdict changes, keeping the audit trail meaningful.
- **Member tiers & coin access** — every coin is assigned a tier by the admin:
  *public* (any visitor), *free* (registered members) or *paid* (premium).
  Visitors register/log in on the site (`account.php`); locked coins show in
  the picker with a lock and an upgrade hint. Admin → Members lists everyone
  and flips free/paid with one click (hook up any payment flow you like, then
  upgrade the member here or automate it against the members table).
- **Interactive candlestick chart** rendered locally on `<canvas>` (no external
  JS libraries): EMA 20/50 overlays, volume, crosshair + OHLCV tooltip,
  5 timeframes (15m / 1h / 4h / 1d / 1w).
- **Admin panel** (pure PHP, session auth, CSRF-protected):
  - Dashboard: stored candles, generated signals, cache freshness
  - Symbols: add/enable/disable/delete watchlist pairs
  - Knowledge base: edit rule weights, descriptions, on/off
  - Signal history: every signal ever generated, with fired rules + indicator snapshot
  - Settings: site name/tagline, legal notice, API base URL, cache TTL,
    candle count, default symbol/timeframe, engine thresholds, admin password,
    cache maintenance
- **Resilient data layer**: candles are cached in the DB with a TTL; if the
  configured API host is unreachable (e.g. geo-blocked), known free mirrors are
  tried automatically, and stale cache is served as a last resort.
- **Mobile & desktop friendly**: fully responsive public site and admin panel
  (stacked layouts, scrollable tables/nav, adaptive chart with touch crosshair
  and tooltip on phones).
- **30 top pairs seeded, unlimited importable**: Admin > Symbols can import
  every actively trading pair from the exchange in one click (~470 USDT pairs,
  or USDC/FDUSD/BTC/ETH quotes), with bulk enable/disable. Candles are only
  fetched for pairs people actually view, so a huge watchlist costs nothing.
- **Complete branding kit**: scalable SVG logo, favicons (32/192/512 +
  apple-touch icon), web manifest, and a 1200x630 social/OG image wired into
  Open Graph and Twitter Card meta tags on every page.
- **Everything customizable from the admin panel**: site name/tagline/footer,
  legal notice, accent & candle colors, visible timeframes, live-refresh
  interval, data source, cache, engine thresholds, TA rule weights, symbols
  and news sources - no code edits needed.
- **Payments & subscriptions** — admin-customizable plans (any name, duration
  in days, USD price; 1 Week / 1 Month / 1 Year seeded). Five automatic
  gateways, each with hosted checkout, verified webhooks and status
  re-checks: **Cryptomus** (5 seeded currency options), **CoinGate**,
  **NOWPayments**, **Coinbase Commerce** and **BTCPay Server** (self-hosted) -
  all configured in Admin → Plans & gateways (each gateway a collapsible
  section with its webhook URL shown). Plus *manual* methods the admin defines
  freely (wallet address, bank account, UPI number, payment link, optional
  QR-code image) with **configurable buyer fields** per method: require a
  proof screenshot, a custom question (e.g. "Transaction ID"), or both —
  one-click admin approval. Premium access auto-expires per plan and renewals
  extend the current expiry. Proof images are stored outside web access and
  viewable by the admin only.
- **Instant signal alerts** — each user picks coins and one *or many*
  timeframes to watch; a background poll keeps running even when the tab is
  hidden and fires a browser notification the moment a watched pair flips
  bullish (BUY) or bearish (SELL). Tier rules apply to alerts too. A **bulk
  "Watch ALL my coins"** button (tier + pair cap chosen by the admin) watches
  every accessible coin on the ticked timeframes in one tap.
- **Marketing landing page** (hero, live stats, feature/pricing sections, all
  admin-editable) shown to guests; logged-in members go straight to the live
  charts. 2026-style animated UI: ambient gradients, glassmorphism, scroll
  reveals, count-up stats — with a full `prefers-reduced-motion` fallback.
  On phones, navigation collapses into a ☰ menu on both the public site and
  the admin panel.
- **Web Push: alerts with the browser closed** — pure-PHP VAPID (ES256) and
  RFC 8291 aes128gcm payload encryption, no libraries. A service worker
  (`sw.js`) receives pushes; `cron.php` keeps analysing every pair watched by
  a push subscriber and sends a signed push on each BUY/SELL flip, so alerts
  arrive on desktop with the site closed and on Android even with the browser
  closed (iPhone: install the site to the home screen, iOS 16.4+). Dead
  subscriptions are pruned automatically. Requires HTTPS in production
  (localhost is exempt) and a cron schedule as frequent as you want alert
  latency to be (e.g. every minute:
  `* * * * * php /path/to/signalmasterai/cron.php`).
- **Fully automated live news & upcoming events** — headlines aggregated from
  trusted free sources (CoinDesk, Cointelegraph, Decrypt, Bitcoin Magazine RSS
  by default; add/remove any RSS/Atom feed in the admin) plus an upcoming
  economic-events calendar (ForexFactory free XML) with impact levels,
  forecasts and countdowns. Sources refresh themselves on a TTL, old headlines
  are pruned automatically, and the public page live-updates every 90 s — zero
  manual work. Copyright-clean by design: only headline, link and a short
  excerpt are stored, always attributed and linked to the original publisher;
  full articles are never copied. An optional `cron.php` (CLI or
  token-protected URL) keeps caches warm in the background on busy sites.

## Launch checklist

1. Upload all files to your web root and open the site — the 4-step installer
   runs (database → admin account → done). It locks itself afterwards.
2. Serve over **HTTPS** (required for Web Push; Let's Encrypt is free).
3. Block web access to `data/` (Apache: included `.htaccess`; nginx:
   `location ^~ /data/ { deny all; }`).
4. Add the cron job (dashboard → Automation shows both ready-to-copy forms):
   `* * * * * php /path/to/cron.php` or the token web-cron URL. Without cron,
   alerts still flow via the traffic fallback.
5. Admin → Settings: set email/SMTP (test button included) and review the
   landing page, appearance, trade levels and alert settings.
6. Admin → Plans & gateways: paste your payment gateway credentials and set
   each gateway's webhook URL at the provider (shown next to each form).
7. Admin → Coins: import exchange pairs and assign tiers (public/free/paid).
8. Admin → Knowledge base → **Backtest & calibrate**: run "All enabled coins"
   on your main timeframe once, with *apply* ticked — this calibrates rule
   weights and per-timeframe multipliers from historical outcomes on day one.
   The daily auto-tuner keeps refining them from live verified signals.

## Requirements

- PHP 8.0+ with `pdo_sqlite` (or `pdo_mysql`), `curl`
- Any web server (Apache/nginx/PHP built-in server)

## Quick start (development)

```bash
php -S localhost:8000 -t signalmasterai
```

Open <http://localhost:8000> — you'll be redirected to the installer.
After installation, the admin panel is at `/admin/login.php`.

## Deployment notes

- Point your web server's document root at the `signalmasterai/` directory.
- The `data/` directory (SQLite DB + local config + install lock) must be
  writable by PHP and **must not be web-accessible**. A `.htaccess` is included
  for Apache; on nginx add: `location ^~ /data/ { deny all; }`.
- To reinstall, delete `data/installed.lock`.
- Default data source is `https://data-api.binance.vision` (Binance's public
  market-data host, not geo-restricted). Any Binance-compatible
  `/api/v3/klines` endpoint can be configured in Admin → Settings.

## Project layout

```
signalmasterai/
├── install.php          # step-by-step installation wizard
├── index.php            # public chart + signal page
├── api.php              # JSON API: candles, signal, symbols
├── config.php           # defaults (installer writes data/config.local.php)
├── assets/              # canvas chart renderer + styles
├── src/
│   ├── bootstrap.php    # config + autoload + install guard
│   ├── Database.php     # PDO wrapper, schema, seed (knowledge base)
│   ├── MarketData.php   # API fetch + server-side candle store
│   ├── Indicators.php   # RSI, MACD, EMA, Bollinger, Stochastic, ATR, swings
│   ├── SignalEngine.php # knowledge-base scoring → BUY/SELL/NEUTRAL
│   └── Auth.php         # admin sessions + CSRF
├── admin/               # admin panel (login, dashboard, symbols,
│                        #   knowledge, signals, settings)
└── data/                # runtime: SQLite DB, config.local.php, install lock
```

## Legal

This software produces automated technical-analysis commentary for educational
purposes only. It is **not** investment advice, and past chart patterns do not
predict future results. Trading involves substantial risk of loss.
