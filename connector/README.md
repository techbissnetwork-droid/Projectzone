# Auto-trading SignalMasterAi signals

The site publishes signals. This script turns them into orders on your exchange.

**It runs on your computer, not on the site.** Your exchange API keys never reach
the website — that is the point. The site holds no key that can move money, so a
break-in there cannot touch your exchange account. Nobody should ever ask you to
paste exchange keys into a web form, this site included.

---

## The two ways to receive signals

**Pull (what this script does by default).** Ask the site every minute:

```
GET /api.php?action=feed
Authorization: Bearer <your API token>
```

Works from a laptop, a home PC, a Raspberry Pi — anywhere, including behind a
router, because nothing has to reach *you*. Generate the token on your account
page; it is shown once.

**Push (the webhook).** Set a URL on your account page and the site POSTs each
flip to it as it happens. Lower latency, but needs a public HTTPS address. Every
delivery is signed:

```
X-SMA-Signature: sha256=<hex HMAC-SHA256 of the raw body, with your webhook secret>
X-SMA-Event: signal.flip
```

Verify **before** parsing — re-encoding the JSON changes the bytes and a genuine
payload then fails the check. `verify_webhook()` in `signalmaster_bot.py` is the
whole of it:

```python
expected = hmac.new(secret.encode(), raw_body, hashlib.sha256).hexdigest()
ok = hmac.compare_digest("sha256=" + expected, header)
```

A webhook is delivered *at least* once: if your endpoint acts and then times
out, the site never sees a 2xx and sends it again. Key on `id` — see below.

## What a signal looks like

```json
{
  "id": "SM-JY68HW",
  "ref": "SM-JY68HW",
  "symbol": "ZECUSDT",
  "timeframe": "15m",
  "signal": "BUY",
  "grade": "A",
  "confidence": 71.2,
  "score": 4.8,
  "rr_type": "1:2",
  "rr_tier": 2,
  "open": true,
  "price": 791.84,
  "levels": {
    "entry": 791.84, "stop_loss": 753.61,
    "tp1": 830.07, "tp2": 868.30, "tp3": 906.53,
    "rr": 2, "rr_type": "1:2",
    "expires_at": 1787453100, "expires_in": "13 hours"
  },
  "at": 1787409900
}
```

- **`id` is the idempotency key.** One string per published call, the same in the
  feed, the webhook, the email and on the chart. Store the ones you have acted
  on; skip anything you have seen. Do not key on symbol + time.
- **`open`** — `false` means the call has already settled. The newest row for a
  pair is not the same thing as a live one.
- **`expires_at`** is the plan's own deadline, in Unix seconds. Past it, the
  setup is not the setup any more.
- **`levels.stop_loss` is not optional.** It is what sizes the trade.

## Running it

```bash
pip install requests ccxt
cp env.example .env          # then edit .env
python3 signalmaster_bot.py --once      # one pass, prints every decision
python3 signalmaster_bot.py             # keep running
```

`BACKEND=paper` (the default) fills against the signal's own prices and writes
`trades.log`. It runs the identical decision path as live trading, so it is how
you find out whether your filters and your sizing do what you meant before any
of it costs anything. Leave it there for a while.

### Going live needs three things, not one

```
BACKEND=ccxt
DRY_RUN=0
I_UNDERSTAND_THE_RISK=yes
```

Deliberately awkward. One variable is too easy to set by accident, and this
places real orders. Before that, try `SANDBOX=1` against your exchange's testnet.

## How a trade is sized

By what it can lose, never by a fixed quantity:

```
risk_cash = free_balance × RISK_PCT / 100
amount    = risk_cash / |entry − stop|
```

So the stop costs the same percentage of your balance whichever coin it is. A
fixed size does the opposite — it risks most on the coins that move most.
`MAX_NOTIONAL` caps the trade on top of that, and a plan needing more margin
than you have free is refused rather than shrunk.

## What it places

1. The entry — market, or a limit at the plan's entry with `ENTRY=limit`.
2. A reduce-only stop at `stop_loss`.
3. Take-profit limits at TP1/TP2/TP3, split by `TP_SPLIT`.

**If the stop is refused, the position is closed again immediately.** An entry
with no stop is not the trade the signal described, and leaving it running
because the entry succeeded is how one refused order becomes the whole account.

## Telegram: watching it, and stopping it, from your phone

**No exchange takes orders from Telegram.** Anything advertising "trade from
Telegram" is a program somewhere else holding the keys — and if that program is
not yours, neither are the keys. So Telegram is not where the trading happens
here. It is where you *watch* it happen and where you *stop* it, while the thing
holding your keys stays on your own machine. That is the honest version of the
idea and it is the useful one: a bot on a home PC with no screen, reporting every
decision to your pocket and taking `/pause` the second you don't like what you
see.

Create your own bot with **@BotFather** (`/newbot`, one minute), put the token
and your chat id in `.env`, and pick a mode:

| `TG_MODE` | what happens |
|---|---|
| `off` | nothing sent. The default when no token is set. |
| `notify` | the bot trades on its own and tells you what it did. |
| `confirm` | **nothing is placed until you tap Take.** The bot still watches, filters and sizes; you keep the decision. |

In `confirm` mode each signal arrives as a message with **✅ Take** / **✖ Skip**:

```
📈 BUY ZECUSDT  15m
SM-JY68HW  A  1:2
size 2.61575 @ 791.84
stop 753.61   targets 830.07, 868.30, 906.53
risk 1% at 3x, good for 13 hours

Take it? Expires in 15 min.
```

Nobody answers, nothing happens — the question dies at `CONFIRM_MINUTES` or at
the plan's own deadline, whichever comes first, because taking a setup after its
time stop is taking a different trade from the one on the screen. A question
still waiting for an answer holds its slot against `MAX_OPEN_TRADES`, so two
unanswered messages plus a filled position cannot come to three.

Commands, any time:

```
/status      where it stands: balance, open, waiting, current filters
/pause       stop opening anything new (positions and stops stay put)
/resume      carry on
/positions   what is open right now
/risk 0.5    change risk per trade until the next restart
```

**Only your chat is listened to.** A bot token is a URL anyone can talk to once
they know the bot's name, and this one answers `/pause` and, in confirm mode,
Take. Messages from any other chat are dropped without a reply — a reply would
confirm the bot exists.

Between passes the bot checks Telegram every three seconds, so a `/pause` lands
in seconds rather than at the end of `POLL_SECONDS`, and a Take button does not
feel broken.

What this does **not** do: `/pause` stops new entries, it does not close what is
open. Use `/positions` and your exchange app for that — a chat command that
liquidates a book is one fat thumb away from a bad afternoon.

## Exchanges

Anything `ccxt` supports — `EXCHANGE=binanceusdm`, `bybit`, `okx`,
`kucoinfutures`, and so on. Balances, markets, precision and entry orders are
uniform through ccxt. Stops are not: the trigger parameter is `triggerPrice` on
current builds and `stopPrice` on older ones, so both are sent. `set_leverage`
and `set_margin_mode` are attempted and a refusal is logged rather than fatal —
plenty of venues reject them on an account that already has the setting.

Pairs are matched on the exchange's own raw name (`BTCUSDT`), which is what
ccxt calls `market['id']`, so no guessing about `BTC/USDT` versus
`BTC/USDT:USDT`.

Verified here end to end against the signal feed on the paper backend: fetch,
filter, size, fill, idempotency, expiry and the type filter. The live ccxt path
has not been run against a real venue from this repository — start on testnet.

## RISK

Trading loses money, and automating it loses money faster and while you are
asleep. This script is provided as-is with no warranty. Nothing here is
financial advice. You are responsible for every order placed under your keys.

Start on `paper`. Then testnet. Then one coin, minimum size, `RISK_PCT=0.25`.
Read `trades.log` every day for a week before you widen anything.

Keep `.env` out of version control — the shipped `.gitignore` already does that.
