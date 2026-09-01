#!/usr/bin/env python3
"""
SignalMasterAi connector - turn published signals into orders on your exchange.

WHAT THIS IS, AND WHERE IT RUNS.

It runs on YOUR machine, not on the signal site. Your exchange API keys never
leave the computer you start this on: the site publishes signals, this reads
them, and only this holds a key that can move money. A break-in on the website
cannot reach your exchange account, because the website has nothing to reach it
with. That separation is the whole design and it is worth keeping - do not paste
exchange keys into a web form, anybody's.

WHAT IT DOES.

Polls the signal feed (or receives the site's signed webhook), filters the calls
to the ones you said you want, sizes each by the risk you allow rather than by a
fixed quantity, and places the entry, the stop and the take-profit ladder that
the signal already carries. Every call has a published reference - SM-XXXXXX -
and that reference is what stops the same signal being taken twice.

IT WILL NOT TRADE REAL MONEY UNTIL YOU SAY SO TWICE.

The default backend is `paper`, which fills against the signal's own prices and
writes a trade log. Going live needs BACKEND=ccxt, DRY_RUN=0, and
I_UNDERSTAND_THE_RISK=yes, all three. That is deliberate: one environment
variable is too easy to set by accident, and this places real orders.

    pip install requests ccxt
    cp env.example .env && edit it
    python3 signalmaster_bot.py --once        # one pass, paper, prints decisions
    python3 signalmaster_bot.py               # keep running

No warranty. Trading loses money. Read RISK in the README before BACKEND=ccxt.
"""

from __future__ import annotations

import argparse
import hashlib
import hmac
import json
import os
import sys
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, List, Optional

try:
    import requests
except ImportError:                                       # pragma: no cover
    sys.exit("This needs the requests library:  pip install requests")


# --------------------------------------------------------------------------
# Configuration. Environment variables only - a config file full of secrets is
# a config file somebody eventually commits.
# --------------------------------------------------------------------------

def _env(name: str, default: str = "") -> str:
    return os.environ.get(name, default).strip()


def _num(name: str, default: float) -> float:
    raw = _env(name)
    if raw == "":
        return default
    try:
        return float(raw)
    except ValueError:
        sys.exit(f"{name} must be a number, got {raw!r}")


def _int(name: str, default: int) -> int:
    return int(_num(name, default))


def _list(name: str) -> List[str]:
    raw = _env(name)
    return [x.strip().upper() for x in raw.split(",") if x.strip()]


GRADE_RANK = {"A+": 4, "A": 3, "B": 2, "C": 1, "D": 0}


@dataclass
class Config:
    site: str = ""
    token: str = ""
    webhook_secret: str = ""

    backend: str = "paper"
    exchange_id: str = "binanceusdm"
    market_type: str = "swap"          # swap | spot
    sandbox: bool = False
    api_key: str = ""
    api_secret: str = ""
    api_password: str = ""             # OKX and KuCoin want a passphrase too

    dry_run: bool = True
    confirmed: bool = False

    # what to take
    min_grade: str = "B"
    min_tier: int = 1                  # 1 = accept 1:1 and up, 3 = only 1:3
    symbols: List[str] = field(default_factory=list)
    timeframes: List[str] = field(default_factory=list)
    sides: List[str] = field(default_factory=lambda: ["BUY", "SELL"])
    max_age_min: int = 30
    max_open: int = 3

    # how much
    risk_pct: float = 1.0              # of free quote balance, per trade
    max_notional: float = 0.0          # 0 = no cap
    leverage: int = 3
    margin_mode: str = "isolated"
    entry: str = "market"              # market | limit
    tp_split: List[float] = field(default_factory=lambda: [0.5, 0.3, 0.2])

    poll_seconds: int = 60
    state_file: str = "state.json"
    log_file: str = "trades.log"

    # Telegram, as the control surface. YOUR bot and YOUR chat, created with
    # @BotFather in a minute - not the signal site's bot, which delivers alerts
    # and has no idea this connector exists.
    tg_token: str = ""
    tg_chat: str = ""
    tg_mode: str = "off"           # off | notify | confirm
    confirm_minutes: int = 15

    @classmethod
    def load(cls) -> "Config":
        c = cls()
        c.site = _env("SMA_SITE").rstrip("/")
        c.token = _env("SMA_TOKEN")
        c.webhook_secret = _env("SMA_WEBHOOK_SECRET")

        c.backend = (_env("BACKEND", "paper") or "paper").lower()
        c.exchange_id = _env("EXCHANGE", "binanceusdm")
        c.market_type = (_env("MARKET_TYPE", "swap") or "swap").lower()
        c.sandbox = _env("SANDBOX", "0") == "1"
        c.api_key = _env("API_KEY")
        c.api_secret = _env("API_SECRET")
        c.api_password = _env("API_PASSWORD")

        c.dry_run = _env("DRY_RUN", "1") != "0"
        c.confirmed = _env("I_UNDERSTAND_THE_RISK", "").lower() == "yes"

        c.min_grade = (_env("MIN_GRADE", "B") or "B").upper()
        c.min_tier = _int("MIN_RR_TIER", 1)
        c.symbols = _list("SYMBOLS")
        c.timeframes = [t.lower() for t in _list("TIMEFRAMES")]
        c.sides = _list("SIDES") or ["BUY", "SELL"]
        c.max_age_min = _int("MAX_AGE_MINUTES", 30)
        c.max_open = _int("MAX_OPEN_TRADES", 3)

        c.risk_pct = _num("RISK_PCT", 1.0)
        c.max_notional = _num("MAX_NOTIONAL", 0.0)
        c.leverage = _int("LEVERAGE", 3)
        c.margin_mode = (_env("MARGIN_MODE", "isolated") or "isolated").lower()
        c.entry = (_env("ENTRY", "market") or "market").lower()
        split = [float(x) for x in (_env("TP_SPLIT", "0.5,0.3,0.2").split(","))]
        c.tp_split = [x for x in split if x > 0] or [1.0]

        c.poll_seconds = max(10, _int("POLL_SECONDS", 60))
        c.state_file = _env("STATE_FILE", "state.json")
        c.log_file = _env("LOG_FILE", "trades.log")

        c.tg_token = _env("TG_BOT_TOKEN")
        c.tg_chat = _env("TG_CHAT_ID")
        c.tg_mode = (_env("TG_MODE", "notify") or "notify").lower()
        if not c.tg_token or not c.tg_chat:
            c.tg_mode = "off"
        if c.tg_mode not in ("off", "notify", "confirm"):
            sys.exit("TG_MODE must be off, notify or confirm.")
        c.confirm_minutes = max(1, _int("CONFIRM_MINUTES", 15))

        if not c.site:
            sys.exit("SMA_SITE is required, e.g. https://signals.example.com")
        if not c.token:
            sys.exit("SMA_TOKEN is required - generate one on your account page.")
        if c.min_grade not in GRADE_RANK:
            sys.exit(f"MIN_GRADE must be one of {', '.join(GRADE_RANK)}")
        if c.risk_pct <= 0 or c.risk_pct > 20:
            sys.exit("RISK_PCT must be above 0 and at most 20. It is a percent, not a fraction.")
        return c


# --------------------------------------------------------------------------
# State. One file, one job: remember which references have been acted on.
#
# Kept separately from the exchange's own records on purpose. "Do I have a
# position on BTCUSDT" and "have I already taken SM-MCSK6B" are different
# questions, and answering the second with the first is how a bot re-enters a
# trade it closed twenty minutes ago.
# --------------------------------------------------------------------------

class State:
    def __init__(self, path: str) -> None:
        self.path = Path(path)
        self.seen: Dict[str, Any] = {}
        if self.path.exists():
            try:
                self.seen = json.loads(self.path.read_text()) or {}
            except (ValueError, OSError) as exc:
                sys.exit(f"{self.path} exists but could not be read ({exc}). "
                         "Move it aside rather than deleting it - it is the record "
                         "of what has already been traded.")

    def took(self, ref: str) -> bool:
        return ref != "_pending" and ref in self.seen

    # Confirmations waiting for a tap. On disk with everything else, because a
    # restart in the middle of a decision must not silently drop the trade the
    # operator was about to approve - nor quietly take it later without them.
    def pending(self) -> Dict[str, Any]:
        return self.seen.setdefault("_pending", {}) if isinstance(
            self.seen.get("_pending", {}), dict) else {}

    def hold(self, ref: str, item: Dict[str, Any]) -> None:
        self.seen.setdefault("_pending", {})[ref] = item
        self._flush()

    def release(self, ref: str) -> None:
        self.seen.get("_pending", {}).pop(ref, None)
        self._flush()

    def record(self, ref: str, detail: Dict[str, Any]) -> None:
        self.seen[ref] = detail
        self.seen.get("_pending", {}).pop(ref, None)
        self._flush()

    def _flush(self) -> None:
        tmp = self.path.with_suffix(self.path.suffix + ".tmp")
        # Written to a temporary file and renamed: a half-written state file is
        # a bot that forgets what it has traded, and it forgets it at exactly
        # the moment something has gone wrong.
        tmp.write_text(json.dumps(self.seen, indent=1, sort_keys=True))
        tmp.replace(self.path)


# --------------------------------------------------------------------------
# Execution backends.
# --------------------------------------------------------------------------

class PaperBroker:
    """
    Fills at the signal's own entry, tracks positions in memory, and writes
    every decision to the log. This is not a toy - it is how you find out
    whether your filters and your sizing do what you meant before any of it
    costs anything, and it exercises the identical decision path.
    """

    name = "paper"

    def __init__(self, cfg: Config, log) -> None:
        self.cfg = cfg
        self.log = log
        self.balance = _num("PAPER_BALANCE", 10000.0)
        self.positions: Dict[str, Dict[str, Any]] = {}

    def free_quote(self) -> float:
        return self.balance

    def open_symbols(self) -> List[str]:
        return list(self.positions)

    def market_meta(self, symbol: str) -> Dict[str, Any]:
        return {"ok": True, "symbol": symbol, "min_amount": 0.0, "min_cost": 0.0}

    def place(self, plan: "Plan") -> Dict[str, Any]:
        self.positions[plan.symbol] = {
            "side": plan.side, "amount": plan.amount, "entry": plan.entry,
            "stop": plan.stop, "targets": plan.targets, "ref": plan.ref,
        }
        cost = plan.amount * plan.entry / max(1, plan.leverage)
        self.balance -= cost
        self.log(f"    paper fill  {plan.side} {plan.amount:g} {plan.symbol} @ {plan.entry:g}"
                 f"  margin {cost:.2f}  stop {plan.stop:g}  targets {plan.targets}")
        return {"ok": True, "orders": ["paper-entry", "paper-stop"] +
                                      [f"paper-tp{i+1}" for i in range(len(plan.targets))]}


class CcxtBroker:
    """
    A real exchange, through ccxt, so one script covers Binance, Bybit, OKX,
    KuCoin and the rest rather than one script per venue.

    WHAT IS UNIFORM AND WHAT IS NOT. Balances, markets, precision and plain
    entry orders are the same everywhere through ccxt. Stops are not: the
    parameter is triggerPrice on most modern builds and stopPrice on older
    ones, and reduce-only is spelled differently again on a few venues. Both
    spellings are sent, and a stop that is still refused is reported loudly
    rather than swallowed - an entry with no stop is the one outcome this must
    never leave behind quietly.
    """

    name = "ccxt"

    def __init__(self, cfg: Config, log) -> None:
        try:
            import ccxt                                     # noqa: WPS433
        except ImportError:
            sys.exit("BACKEND=ccxt needs the ccxt library:  pip install ccxt")
        self.cfg = cfg
        self.log = log
        if not cfg.api_key or not cfg.api_secret:
            sys.exit("BACKEND=ccxt needs API_KEY and API_SECRET.")
        if not hasattr(ccxt, cfg.exchange_id):
            sys.exit(f"ccxt has no exchange called {cfg.exchange_id!r}. "
                     f"Try one of: {', '.join(sorted(ccxt.exchanges)[:12])} ...")
        opts: Dict[str, Any] = {
            "apiKey": cfg.api_key,
            "secret": cfg.api_secret,
            "enableRateLimit": True,
            "options": {"defaultType": cfg.market_type},
        }
        if cfg.api_password:
            opts["password"] = cfg.api_password
        self.ex = getattr(ccxt, cfg.exchange_id)(opts)
        if cfg.sandbox:
            self.ex.set_sandbox_mode(True)
            self.log(f"    sandbox mode on for {cfg.exchange_id}")
        self.ex.load_markets()
        # The site names pairs the way the exchange's own API does - BTCUSDT -
        # and ccxt names them BTC/USDT:USDT. market['id'] is the raw name, so
        # matching on it needs no string surgery and no guessing about which
        # quote asset the tail belongs to.
        self.by_id = {
            m["id"]: m for m in self.ex.markets.values()
            if m.get("active", True) and (
                cfg.market_type != "swap" or m.get("swap") or m.get("contract"))
        }

    def free_quote(self) -> float:
        bal = self.ex.fetch_balance()
        quote = _env("QUOTE", "USDT")
        free = (bal.get("free") or {}).get(quote)
        return float(free or 0.0)

    def open_symbols(self) -> List[str]:
        try:
            poss = self.ex.fetch_positions()
        except Exception:                                   # noqa: BLE001
            return []
        out = []
        for p in poss or []:
            size = p.get("contracts") or p.get("contractSize") or 0
            if size and float(size) != 0.0:
                mkt = self.ex.markets.get(p.get("symbol")) or {}
                out.append(mkt.get("id") or p.get("symbol"))
        return out

    def market_meta(self, symbol: str) -> Dict[str, Any]:
        m = self.by_id.get(symbol)
        if not m:
            return {"ok": False, "why": f"{symbol} is not a {self.cfg.market_type} market on "
                                        f"{self.cfg.exchange_id}"}
        lim = m.get("limits") or {}
        return {
            "ok": True,
            "symbol": m["symbol"],
            "min_amount": float(((lim.get("amount") or {}).get("min")) or 0.0),
            "min_cost": float(((lim.get("cost") or {}).get("min")) or 0.0),
        }

    def place(self, plan: "Plan") -> Dict[str, Any]:
        ex, cfg = self.ex, self.cfg
        sym = plan.ccxt_symbol
        side = "buy" if plan.side == "BUY" else "sell"
        close = "sell" if side == "buy" else "buy"
        placed: List[str] = []

        if cfg.market_type == "swap":
            # Both are best-effort: plenty of venues reject one or the other on
            # an account that already has the setting, and that is not a reason
            # to abandon the trade.
            for fn, arg in ((ex.set_margin_mode, cfg.margin_mode), (ex.set_leverage, cfg.leverage)):
                try:
                    fn(arg, sym)
                except Exception as exc:                    # noqa: BLE001
                    self.log(f"    note: {fn.__name__}({arg}) refused - {exc}")

        amount = float(ex.amount_to_precision(sym, plan.amount))
        if amount <= 0:
            return {"ok": False, "why": "amount rounds to zero at this exchange's precision"}

        if cfg.entry == "limit":
            price = float(ex.price_to_precision(sym, plan.entry))
            o = ex.create_order(sym, "limit", side, amount, price)
        else:
            o = ex.create_order(sym, "market", side, amount)
        placed.append(str(o.get("id")))

        # THE STOP IS NOT OPTIONAL.
        #
        # If it will not go on, the position is closed again immediately rather
        # than left running naked. A trade whose risk is undefined is not the
        # trade the signal described, and leaving it open because the entry
        # succeeded is how one refused order becomes the whole account.
        stop_price = float(ex.price_to_precision(sym, plan.stop))
        stop_params = {"reduceOnly": True, "triggerPrice": stop_price, "stopPrice": stop_price}
        try:
            s = ex.create_order(sym, "market", close, amount, None, stop_params)
            placed.append(str(s.get("id")))
        except Exception as exc:                            # noqa: BLE001
            self.log(f"    STOP REFUSED ({exc}) - closing the position again")
            try:
                ex.create_order(sym, "market", close, amount, None, {"reduceOnly": True})
            except Exception as exc2:                       # noqa: BLE001
                self.log(f"    !! COULD NOT CLOSE: {exc2} - handle this by hand NOW")
            return {"ok": False, "why": f"stop refused: {exc}"}

        left = amount
        for i, (tp, share) in enumerate(zip(plan.targets, cfg.tp_split)):
            part = amount * share if i < len(plan.targets) - 1 else left
            part = float(ex.amount_to_precision(sym, min(part, left)))
            if part <= 0:
                continue
            try:
                t = ex.create_order(sym, "limit", close, part,
                                    float(ex.price_to_precision(sym, tp)), {"reduceOnly": True})
                placed.append(str(t.get("id")))
                left -= part
            except Exception as exc:                        # noqa: BLE001
                self.log(f"    take-profit {i + 1} refused ({exc}) - stop is on, position stands")
        return {"ok": True, "orders": placed}


# --------------------------------------------------------------------------
# Telegram, as the control surface.
#
# WHAT TELEGRAM CAN AND CANNOT DO.
#
# No exchange accepts orders from Telegram. Anything advertising "trade from
# Telegram" is a program somewhere else holding the keys - and if that program
# is not yours, neither are the keys. So Telegram is not where trading happens
# here. It is where you WATCH it happen and where you STOP it, from a phone,
# while the thing holding your keys stays on your own machine.
#
# That is the honest version of the idea, and it is the useful one: a bot on a
# home PC with no screen, reporting every decision to your pocket and taking
# /pause the moment you do not like what you see.
#
# THREE MODES.
#   off      - nothing is sent. The default when no token is configured.
#   notify   - every trade and every command answered; the bot decides.
#   confirm  - nothing is placed until you tap Take. The bot still does the
#              watching, the filtering and the sizing; you keep the decision.
#              A signal nobody answers expires on its own, either at
#              CONFIRM_MINUTES or at the plan's own deadline, whichever is
#              first - a stale approval is a worse outcome than a missed trade.
#
# YOUR bot, not the signal site's. Created with @BotFather in a minute. The
# site's Telegram alerts are a different thing entirely and know nothing about
# this.
# --------------------------------------------------------------------------

class Notifier:
    """Telegram in about a hundred lines, over the same requests dependency."""

    def __init__(self, cfg: Config, log) -> None:
        self.cfg = cfg
        self.log = log
        self.on = cfg.tg_mode != "off"
        self.offset = 0
        # The API base is a setting because plenty of networks reach Telegram
        # through a proxy, and because a fake one is how this gets tested
        # without a live bot. Defaults to the real thing.
        base = _env("TG_API_BASE", "https://api.telegram.org").rstrip("/")
        self.api = f"{base}/bot{cfg.tg_token}/" if self.on else ""

    def _call(self, method: str, **params: Any) -> Optional[Dict[str, Any]]:
        if not self.on:
            return None
        try:
            r = requests.post(self.api + method, json=params, timeout=15)
            data = r.json()
            if not data.get("ok"):
                self.log(f"    telegram {method}: {data.get('description')}")
                return None
            return data.get("result")
        except requests.RequestException as exc:
            self.log(f"    telegram {method}: {exc}")
            return None

    def say(self, text: str, buttons: Optional[List[List[Dict[str, str]]]] = None) -> None:
        params: Dict[str, Any] = {"chat_id": self.cfg.tg_chat, "text": text,
                                  "parse_mode": "HTML", "disable_web_page_preview": True}
        if buttons:
            params["reply_markup"] = {"inline_keyboard": buttons}
        self._call("sendMessage", **params)

    def ask(self, ref: str, text: str) -> None:
        self.say(text, [[{"text": "\u2705 Take", "callback_data": f"take:{ref}"},
                         {"text": "\u2716 Skip", "callback_data": f"skip:{ref}"}]])

    def poll(self) -> List[Dict[str, Any]]:
        """
        New messages and button taps, from OUR chat only.

        The chat filter is not politeness. A bot token is a URL anyone can talk
        to once they know the bot's name, and this one answers /pause and, in
        confirm mode, /take. Anything from another chat is dropped without a
        reply - a reply would confirm the bot exists.
        """
        res = self._call("getUpdates", offset=self.offset + 1, timeout=0, limit=20) or []
        out = []
        for u in res:
            self.offset = max(self.offset, int(u.get("update_id", 0)))
            cb = u.get("callback_query")
            msg = cb.get("message") if cb else u.get("message")
            chat = str(((msg or {}).get("chat") or {}).get("id", ""))
            if chat != str(self.cfg.tg_chat):
                continue
            if cb:
                out.append({"kind": "tap", "data": str(cb.get("data") or ""),
                            "id": cb.get("id")})
            elif msg and msg.get("text"):
                out.append({"kind": "text", "text": str(msg["text"]).strip()})
        return out

    def acknowledge(self, tap_id: str, text: str) -> None:
        """Clears the spinner on the button. Without it the tap looks ignored."""
        self._call("answerCallbackQuery", callback_query_id=tap_id, text=text)


def plan_message(sig: Dict[str, Any], plan: "Plan", cfg: Config) -> str:
    arrow = "\U0001F4C8" if plan.side == "BUY" else "\U0001F4C9"
    lv = sig.get("levels") or {}
    return (f"{arrow} <b>{plan.side} {plan.symbol}</b>  {sig.get('tf')}\n"
            f"<code>{plan.ref}</code>  {sig.get('grade')}  {sig.get('rr_type') or ''}\n"
            f"size <b>{plan.amount:g}</b> @ {plan.entry:g}\n"
            f"stop {plan.stop:g}   targets {', '.join(f'{t:g}' for t in plan.targets)}\n"
            f"risk {cfg.risk_pct}% at {cfg.leverage}x, good for {lv.get('expires_in', '?')}")


def handle_commands(cfg: Config, state: State, broker, tg: Notifier, log,
                    runtime: Dict[str, Any]) -> None:
    """
    Commands and button taps. Called often - between passes as well as during
    them - because a /pause that takes a minute to land is not a pause.
    """
    if not tg.on:
        return
    for ev in tg.poll():
        if ev["kind"] == "tap":
            action, _, ref = ev["data"].partition(":")
            item = state.pending().get(ref)
            if not item:
                tg.acknowledge(ev["id"], "That one is no longer waiting.")
                continue
            if action == "skip":
                state.record(ref, {"at": int(time.time()), "skipped": "declined in Telegram"})
                tg.acknowledge(ev["id"], "Skipped.")
                log(f"  {ref}: skipped by you in Telegram")
                continue
            if action == "take":
                # Re-checked, not replayed. The approval says yes to the trade;
                # it does not say the deadline has not passed while the message
                # sat unread, and taking a setup after its own time stop is
                # taking a different trade from the one on the screen.
                if int(time.time()) > int(item.get("deadline", 0)):
                    state.record(ref, {"at": int(time.time()), "skipped": "expired before approval"})
                    tg.acknowledge(ev["id"], "Too late - that setup has expired.")
                    continue
                runtime["approved"].append(ref)
                tg.acknowledge(ev["id"], "Placing it.")
            continue

        cmd = ev["text"].split()[0].lower().lstrip("/").split("@")[0]
        arg = ev["text"].split()[1] if len(ev["text"].split()) > 1 else ""
        if cmd in ("pause", "stop"):
            runtime["paused"] = True
            tg.say("\u23F8 Paused. Nothing new will be opened. /resume when you want it back.")
        elif cmd == "resume":
            runtime["paused"] = False
            tg.say("\u25B6 Running again.")
        elif cmd == "risk":
            try:
                new = float(arg)
            except ValueError:
                tg.say("Usage: /risk 0.5   (percent of balance per trade)")
                continue
            if not 0 < new <= 20:
                tg.say("Risk must be above 0 and at most 20 percent.")
                continue
            cfg.risk_pct = new
            tg.say(f"Risk per trade is now <b>{new}%</b>. This lasts until the bot restarts - "
                   f"put it in .env to keep it.")
        elif cmd in ("status", "start"):
            held = broker.open_symbols()
            waiting = len(state.pending())
            # Built before the f-string: an f-string expression cannot contain a
            # backslash, and these two are escape sequences.
            mark = "\u23F8 paused" if runtime["paused"] else "\u25B6 running"
            tg.say(f"{mark} on "
                   f"<b>{broker.name}</b>{' (dry run)' if cfg.dry_run else ''}\n"
                   f"free {broker.free_quote():.2f}\n"
                   f"open {len(held)}/{cfg.max_open}: {', '.join(held) or 'none'}\n"
                   f"awaiting your tap: {waiting}\n"
                   f"risk {cfg.risk_pct}%  grade {cfg.min_grade}+  type 1:{cfg.min_tier}+")
        elif cmd == "positions":
            held = broker.open_symbols()
            tg.say("Open: " + (", ".join(held) if held else "nothing"))
        else:
            tg.say("/status  /pause  /resume  /positions  /risk &lt;percent&gt;\n"
                   "In confirm mode, Take and Skip are the buttons on each signal.")


# --------------------------------------------------------------------------
# Turning a signal into a plan.
# --------------------------------------------------------------------------

@dataclass
class Plan:
    ref: str
    symbol: str
    ccxt_symbol: str
    side: str
    entry: float
    stop: float
    targets: List[float]
    amount: float
    leverage: int


def _f(v: Any) -> Optional[float]:
    try:
        f = float(v)
    except (TypeError, ValueError):
        return None
    return f if f == f and f not in (float("inf"), float("-inf")) else None


def wanted(sig: Dict[str, Any], cfg: Config, now: int) -> Optional[str]:
    """Why this signal is being skipped, or None to take it."""
    if sig.get("signal") not in ("BUY", "SELL"):
        return "not a setup"
    if sig.get("signal") not in cfg.sides:
        return f"{sig['signal']} is not in SIDES"
    if sig.get("open") is False:
        return "already settled"
    if not sig.get("id"):
        return "no reference - this site predates signal ids, upgrade it"
    grade = (sig.get("grade") or "D").upper()
    if GRADE_RANK.get(grade, 0) < GRADE_RANK[cfg.min_grade]:
        return f"grade {grade} below MIN_GRADE {cfg.min_grade}"
    if int(sig.get("rr_tier") or 0) < cfg.min_tier:
        return f"type {sig.get('rr_type') or '?'} below MIN_RR_TIER 1:{cfg.min_tier}"
    if cfg.symbols and sig.get("symbol", "").upper() not in cfg.symbols:
        return "symbol not in SYMBOLS"
    if cfg.timeframes and str(sig.get("tf", "")).lower() not in cfg.timeframes:
        return "timeframe not in TIMEFRAMES"
    age = (now - int(sig.get("at") or 0)) / 60.0
    if age > cfg.max_age_min:
        return f"{age:.0f} min old, past MAX_AGE_MINUTES {cfg.max_age_min}"
    lv = sig.get("levels") or {}
    exp = _f(lv.get("expires_at"))
    if exp and exp <= now:
        return "past its own time stop"
    if _f(lv.get("entry")) is None or _f(lv.get("stop_loss")) is None:
        return "no entry or stop in the plan"
    return None


def build_plan(sig: Dict[str, Any], cfg: Config, broker, free: float) -> Any:
    """A Plan, or a string saying why there is not one."""
    lv = sig["levels"]
    symbol = sig["symbol"].upper()
    meta = broker.market_meta(symbol)
    if not meta.get("ok"):
        return meta.get("why", "market unavailable")

    entry = _f(lv.get("entry"))
    stop = _f(lv.get("stop_loss"))
    side = sig["signal"]
    # A stop on the wrong side of entry is a broken plan, not a wide one, and
    # the risk-per-unit below would come out negative and size the trade by
    # dividing by it. Refused rather than corrected: guessing which of the two
    # numbers is wrong is not this script's decision to make.
    if side == "BUY" and stop >= entry:
        return f"stop {stop:g} is not below entry {entry:g}"
    if side == "SELL" and stop <= entry:
        return f"stop {stop:g} is not above entry {entry:g}"

    per_unit = abs(entry - stop)
    if per_unit <= 0:
        return "entry and stop are the same price"

    # SIZED BY WHAT IT CAN LOSE, NEVER BY A FIXED QUANTITY.
    #
    # risk_pct of the free balance is what the stop is allowed to cost, and the
    # distance to the stop decides how many units that buys. A fixed size does
    # the opposite: it risks whatever the chart happens to make it risk, which
    # is most on the coins that move most.
    risk_cash = free * (cfg.risk_pct / 100.0)
    amount = risk_cash / per_unit

    notional = amount * entry
    cap = cfg.max_notional
    if cap > 0 and notional > cap:
        amount = cap / entry
        notional = cap
    # Margin, not notional, is what has to be there. Leverage is what makes the
    # two different, and a plan that needs more margin than the balance has is
    # a plan the exchange will refuse - better to say so here.
    margin = notional / max(1, cfg.leverage)
    if margin > free:
        return (f"needs {margin:.2f} margin at {cfg.leverage}x but only {free:.2f} is free "
                f"- lower RISK_PCT or raise LEVERAGE")
    if meta["min_amount"] and amount < meta["min_amount"]:
        return f"size {amount:g} below the exchange minimum {meta['min_amount']:g}"
    if meta["min_cost"] and notional < meta["min_cost"]:
        return f"notional {notional:.2f} below the exchange minimum {meta['min_cost']:.2f}"

    targets = [t for t in (_f(lv.get("tp1")), _f(lv.get("tp2")), _f(lv.get("tp3"))) if t]
    return Plan(ref=sig["id"], symbol=symbol, ccxt_symbol=meta["symbol"], side=side,
                entry=entry, stop=stop, targets=targets, amount=amount, leverage=cfg.leverage)


# --------------------------------------------------------------------------
# The pass.
# --------------------------------------------------------------------------

def fetch(cfg: Config) -> List[Dict[str, Any]]:
    r = requests.get(cfg.site + "/api.php", params={"action": "feed"},
                     headers={"Authorization": "Bearer " + cfg.token,
                              "User-Agent": "SignalMasterAi-Connector/1.0"},
                     timeout=20)
    if r.status_code == 401:
        sys.exit("The site rejected the token. Generate a new one on your account page.")
    if r.status_code == 403:
        sys.exit(f"The site refused the feed: {r.text[:200]}")
    r.raise_for_status()
    data = r.json()
    if not data.get("ok"):
        raise RuntimeError(f"feed error: {data.get('error')}")
    return data.get("signals") or []


def verify_webhook(body: bytes, header: str, secret: str) -> bool:
    """
    Constant-time check of the site's X-SMA-Signature. Use this in whatever
    receives the webhook - the value is 'sha256=' followed by the hex digest of
    HMAC-SHA256 over the exact bytes of the body, so verify before parsing:
    re-encoding the JSON first changes the bytes and the check then fails on
    payloads that were perfectly genuine.
    """
    if not secret or not header:
        return False
    expected = hmac.new(secret.encode(), body, hashlib.sha256).hexdigest()
    return hmac.compare_digest("sha256=" + expected, header.strip())


def take_it(cfg: Config, state: State, broker, tg: "Notifier", log,
            sig: Dict[str, Any], plan: "Plan") -> bool:
    """Place a plan and record it. One place, so approval and automatic
    execution cannot drift into doing different things."""
    res = broker.place(plan)
    if not res.get("ok"):
        log(f"    NOT TAKEN: {res.get('why')}")
        if tg.on:
            tg.say(f"\u26A0 <code>{plan.ref}</code> {plan.symbol}: {res.get('why')}")
        return False
    # Recorded only after the orders are on. Recording first would mean a
    # failed placement is remembered as a trade and never retried.
    state.record(plan.ref, {"at": int(time.time()), "symbol": plan.symbol, "side": plan.side,
                            "amount": plan.amount, "entry": plan.entry, "stop": plan.stop,
                            "orders": res.get("orders", [])})
    if tg.on:
        tg.say("\u2705 <b>Placed</b>\n" + plan_message(sig, plan, cfg))
    return True


def run_once(cfg: Config, state: State, broker, log,
             tg: "Notifier", runtime: Dict[str, Any]) -> None:
    now = int(time.time())

    # Approvals first: somebody tapped Take while the bot was asleep, and that
    # trade should go on before the feed is read again - the setup they looked
    # at is the oldest thing in the queue.
    for ref in list(runtime.get("approved", [])):
        runtime["approved"].remove(ref)
        item = state.pending().get(ref)
        if not item:
            continue
        state.release(ref)
        plan = Plan(**item["plan"])
        log(f"  {ref}: approved in Telegram")
        take_it(cfg, state, broker, tg, log, item["sig"], plan)

    # Anything nobody answered in time is closed off, once, with a reason - so
    # it neither fires later nor comes back tomorrow as a fresh signal.
    for ref, item in list(state.pending().items()):
        if now > int(item.get("deadline", 0)):
            state.record(ref, {"at": now, "skipped": "no answer before it expired"})
            log(f"  {ref}: expired waiting for your answer")
            if tg.on:
                tg.say(f"\u23F1 <code>{ref}</code> expired with no answer. Not taken.")

    if runtime.get("paused"):
        log(f"{time.strftime('%Y-%m-%d %H:%M:%S')}  paused - not reading the feed")
        return

    signals = fetch(cfg)
    log(f"{time.strftime('%Y-%m-%d %H:%M:%S')}  {len(signals)} signal(s) from {cfg.site}")

    open_now = set(broker.open_symbols())
    # A CONFIRMATION WAITING FOR A TAP IS A SLOT ALREADY SPENT.
    #
    # Found in testing: the pass that created a pending confirmation reserved
    # its slot, and every pass after it did not - so two questions already on
    # the phone plus one filled position came to three against a MAX_OPEN of
    # two. Whoever answers them last would have opened a trade the limit was
    # there to prevent. Counted from the pending list, which is on disk, so a
    # restart does not lose the reservation either.
    waiting = {str((it.get("plan") or {}).get("symbol") or "")
               for it in state.pending().values()}
    open_now |= {w for w in waiting if w}
    free = broker.free_quote()
    log(f"  free balance {free:.2f}   open positions {len(open_now)}/{cfg.max_open}"
        + (f" ({len(waiting)} awaiting your answer)" if waiting else ""))

    for sig in signals:
        ref = sig.get("id") or "?"
        tag = f"  {ref} {sig.get('symbol')} {sig.get('tf')} {sig.get('signal')}"
        if state.took(ref):
            continue                                   # silent: it is the normal case
        why = wanted(sig, cfg, now)
        if why:
            log(f"{tag}: skipped - {why}")
            continue
        if sig["symbol"].upper() in open_now:
            log(f"{tag}: skipped - already holding this symbol")
            continue
        if len(open_now) >= cfg.max_open:
            log(f"{tag}: skipped - at MAX_OPEN_TRADES {cfg.max_open}")
            continue

        plan = build_plan(sig, cfg, broker, free)
        if isinstance(plan, str):
            log(f"{tag}: skipped - {plan}")
            continue

        # Already asked and still waiting: say nothing. Re-announcing it every
        # pass fills the log with a decision that has not changed, and the one
        # line that matters - the answer - gets lost in the repetition.
        if cfg.tg_mode == "confirm" and ref in state.pending():
            continue
        log(f"{tag}: TAKE  {plan.amount:g} @ {plan.entry:g}  stop {plan.stop:g}  "
            f"targets {plan.targets}  ({sig.get('grade')}, {sig.get('rr_type')})")
        if cfg.dry_run:
            log("    dry run - no order placed. Set DRY_RUN=0 to act on this.")
            if tg.on:
                tg.say("\U0001F9EA <b>Dry run</b>, nothing placed\n" + plan_message(sig, plan, cfg))
            continue

        if cfg.tg_mode == "confirm":
            lv = sig.get("levels") or {}
            # The earlier of the two deadlines. CONFIRM_MINUTES is how long you
            # are willing to leave a decision open; expires_at is how long the
            # setup is a setup. Neither can extend the other.
            deadline = now + cfg.confirm_minutes * 60
            plan_exp = int(_f(lv.get("expires_at")) or 0)
            if plan_exp:
                deadline = min(deadline, plan_exp)
            state.hold(ref, {"deadline": deadline, "sig": sig, "plan": plan.__dict__})
            tg.ask(ref, plan_message(sig, plan, cfg) +
                        f"\n\nTake it? Expires in {max(1, (deadline - now) // 60)} min.")
            log(f"    waiting for your answer in Telegram (until "
                f"{time.strftime('%H:%M', time.localtime(deadline))})")
            open_now.add(plan.symbol)             # hold the slot while it waits
            continue

        if not take_it(cfg, state, broker, tg, log, sig, plan):
            continue
        open_now.add(plan.symbol)
        free = broker.free_quote()


def main() -> None:
    ap = argparse.ArgumentParser(description="Trade SignalMasterAi signals on your own exchange.")
    ap.add_argument("--once", action="store_true", help="one pass and exit")
    ap.add_argument("--env", default=".env", help="file of KEY=value lines to load first")
    args = ap.parse_args()

    envfile = Path(args.env)
    if envfile.exists():
        for line in envfile.read_text().splitlines():
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, v = line.split("=", 1)
            os.environ.setdefault(k.strip(), v.strip().strip('"').strip("'"))

    cfg = Config.load()
    logf = open(cfg.log_file, "a", buffering=1)

    def log(msg: str) -> None:
        print(msg)
        logf.write(msg + "\n")

    if cfg.backend == "ccxt" and not cfg.dry_run and not cfg.confirmed:
        sys.exit("BACKEND=ccxt with DRY_RUN=0 also needs I_UNDERSTAND_THE_RISK=yes.\n"
                 "This places real orders with real money. Run it on the paper backend, "
                 "or on your exchange's testnet with SANDBOX=1, until the log shows it "
                 "doing what you meant.")

    broker = CcxtBroker(cfg, log) if cfg.backend == "ccxt" else PaperBroker(cfg, log)
    state = State(cfg.state_file)
    tg = Notifier(cfg, log)
    runtime: Dict[str, Any] = {"paused": False, "approved": []}
    mode = "DRY RUN" if cfg.dry_run else "LIVE"
    banner = (f"connector up: <b>{broker.name}</b>, {mode}, risk {cfg.risk_pct}%, "
              f"grade {cfg.min_grade}+, type 1:{cfg.min_tier}+"
              + ("\nConfirm mode: nothing is placed until you tap Take."
                 if cfg.tg_mode == "confirm" else ""))
    log(f"== connector starting: backend {broker.name}, {mode}, "
        f"risk {cfg.risk_pct}%, min grade {cfg.min_grade}, min type 1:{cfg.min_tier}"
        + (f", telegram {cfg.tg_mode}" if tg.on else ""))
    if tg.on:
        # The old queue is drained before anything is announced, so a bot that
        # was offline for a day does not open with a hundred stale taps.
        tg.poll()
        tg.say("\U0001F50C " + banner + "\n/status for where it stands.")

    while True:
        try:
            handle_commands(cfg, state, broker, tg, log, runtime)
            run_once(cfg, state, broker, log, tg, runtime)
        except KeyboardInterrupt:
            log("stopped")
            return
        except requests.RequestException as exc:
            log(f"  network: {exc} - trying again next pass")
        except Exception as exc:                            # noqa: BLE001
            log(f"  error: {exc!r} - trying again next pass")
        if args.once:
            return
        # THE WAIT IS NOT ONE SLEEP.
        #
        # A /pause that lands a minute late is not a pause, and a Take button
        # that takes a minute to register feels broken. So the gap between
        # passes is spent servicing Telegram every few seconds - which costs
        # one small request each time and is the difference between a control
        # surface and a status page.
        waited = 0
        while waited < cfg.poll_seconds:
            step = min(3 if tg.on else cfg.poll_seconds, cfg.poll_seconds - waited)
            try:
                time.sleep(step)
            except KeyboardInterrupt:
                log("stopped")
                return
            waited += step
            if tg.on:
                try:
                    handle_commands(cfg, state, broker, tg, log, runtime)
                    if runtime["approved"]:
                        break             # somebody said yes; do not make them wait
                except Exception as exc:                    # noqa: BLE001
                    log(f"  telegram: {exc!r}")


if __name__ == "__main__":
    main()
