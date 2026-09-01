/**
 * SignalMasterAi frontend: canvas candlestick chart + signal UI.
 * No external libraries - everything is rendered locally.
 */
(function () {
  'use strict';

  const canvas = document.getElementById('chart');
  const ctx = canvas.getContext('2d');
  const statusEl = document.getElementById('chartStatus');
  const wrap = canvas.parentElement;

  const COLORS = {
    up: window.SMA.upColor || '#2ED3A0',
    down: window.SMA.downColor || '#FF6A5F',
    grid: 'rgba(255,255,255,.07)',
    axis: '#8A94A8',
    ema20: '#7C89FF',   // categorical slot 1
    ema50: '#D8B26A',   // categorical slot 2
    volume: 'rgba(138,148,168,.28)',
    vwap: '#2ED3A0',    // teal: distinct from the amber EMA 50
    compare: '#B48CFF',
    rsi: '#8A7BE8',
    crosshair: '#8A94A8',
  };

  // What a verdict is CALLED on screen.
  //
  // The engine has three answers and only two of them are calls. Printing
  // NEUTRAL in the same slot, the same size and the same shape as BUY and
  // SELL presented "there is no trade here" as if it were a third kind of
  // trade - readers went looking for the entry it did not have. The verdict
  // itself is unchanged everywhere it does real work; this is the label.
  function verdictLabel(v) {
    return v === 'NEUTRAL' ? 'NO SETUP' : v;
  }

  // The up/down colours are admin-configurable hex, so anything that needs
  // them at partial opacity has to convert rather than hard-code an rgba().
  function hexToRgba(hex, alpha) {
    const m = /^#?([0-9a-f]{6})$/i.exec(String(hex || '').trim());
    if (!m) return 'rgba(139,148,158,' + alpha + ')';
    const n = parseInt(m[1], 16);
    return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + alpha + ')';
  }

  let state = {
    symbol: window.SMA.defaultSymbol,
    tf: window.SMA.defaultInterval,
    candles: [],
    ema20: [],
    ema50: [],
    bbU: [],
    bbL: [],
    hover: null,     // candle index under cursor
    hoverY: null,    // cursor y, for the price crosshair readout
    rsi: null,
    macd: null,
    vwap: null,
    markers: [],     // past signal flips plotted on the chart
    levels: null,    // trade plan of the latest signal (drawn on the chart)
    levelsFor: '',   // "SYMBOL:TF" the levels belong to
    // Open paper positions on the coin currently shown, drawn on the chart.
    // The reader's own trades, never the engine's, so they belong on both
    positions: [],
    view: { count: null, end: null },   // zoom window; nulls = auto + stick right
    yScale: 1,       // vertical zoom from dragging the price axis
    compare: null,   // second symbol overlaid, normalised to percent
    lastSignal: null,
    signal: null,    // current verdict, so the paper-trade panel can name the side
  };

  // Assigned by the paper-trade section further down; called whenever the
  // analysis changes so a stale confirmation cannot outlive its setup.
  let followReset = function () {};
  // Refreshes the paper-trade button without closing an open ticket - see the
  // note beside it for why the two had to come apart.
  let followSync = function () {};

  /**
   * The traded asset's ticker, for labelling quantities. "0.4217 units" says
   * nothing about what is being held; "0.4217 BTC" does. Mirrors
   * sma_base_asset() in bootstrap.php.
   */
  function baseAsset(symbol) {
    symbol = String(symbol || '').trim();
    if (!symbol) return '';
    if (symbol.indexOf(':') !== -1) {
      return symbol.slice(symbol.indexOf(':') + 1).split('.')[0].toUpperCase();
    }
    const up = symbol.toUpperCase();
    // Longest first, so USDT is stripped before USD.
    for (const q of ['USDT', 'FDUSD', 'TUSD', 'BUSD', 'USDC', 'USD', 'EUR', 'TRY', 'BTC', 'ETH', 'BNB']) {
      if (up.length > q.length && up.endsWith(q)) return up.slice(0, -q.length);
    }
    return up;
  }
  // Redraws the open position's unrealised P/L; the price ticker calls it.
  // Set by the order ticket once it exists; called when a line is labelled.
  let mtSyncFromLines = function () { return {}; };
  let livePosRender = function () {};
  let livePosLoad = function () {};

  // Chart preferences (type + overlay toggles) survive reloads.
  let prefs = {
    type: 'candles', ema: true, bb: false, vol: true, levels: true,
    vwap: false, rsi: false, macd: false, log: false, markers: true,
    sr: true,          // support/resistance bands from the engine
    plan: 'auto'       // whose entry/stop/targets the chart draws
  };
  try { prefs = Object.assign(prefs, JSON.parse(localStorage.getItem('sma_chart_v1') || '{}')); } catch (e) {}
  // THE DOOR YOU CAME THROUGH DECIDES THE MODE.
  //
  // ONE CHART, SO NOTHING DECIDES WHOSE PLAN IT IS.
  //
  // There was a second mode here - a manual chart where the reader drew their
  // own levels - and a ?plan= parameter that chose between them. Both are
  // gone. An old bookmark carrying ?plan=manual simply lands on the signal
  // chart, which is the only chart there is.
  function savePrefs() { try { localStorage.setItem('sma_chart_v1', JSON.stringify(prefs)); } catch (e) {} }

  // Per-symbol price precision from the exchange tick size. Formatting every
  // value with two decimals turned a $2.4567 level into "2.46" - a rounding
  // error larger than the spread on most pairs.
  let tickDigits = null;
  // Decimal places for a price.
  //
  // The old floor of 8 was fine until a coin traded below it: a price of
  // 0.00000491 has no digits inside eight places that are not zero, so every
  // level, every axis label and the price itself rendered as "0" - a chart
  // with a zero on every gridline and a trade plan reading entry 0, stop 0,
  // target 0. Below a ten-thousandth the count has to follow the magnitude,
  // not a constant.
  function magnitudeDigits(v) {
    const a = Math.abs(v);
    if (!isFinite(a) || a === 0) return 2;
    if (a >= 1000) return 2;
    if (a >= 1) return 4;
    if (a >= 0.01) return 6;
    if (a >= 0.0001) return 8;
    return Math.max(8, Math.min(12, 6 - Math.floor(Math.log10(a))));
  }

  function digitsFor(v) {
    // The exchange tick is authoritative - unless it is coarser than the
    // number being shown, which would round it away to nothing.
    if (tickDigits !== null && Math.abs(v) >= Math.pow(10, -tickDigits)) {
      return tickDigits;
    }
    return magnitudeDigits(v);
  }

  // ------------------------------------------------ tooltip + legend DOM
  const tip = document.createElement('div');
  tip.className = 'chart-tip';
  wrap.appendChild(tip);

  const legend = document.createElement('div');
  legend.className = 'chart-legend';
  wrap.appendChild(legend);
  function renderLegend() {
    let h = '';
    if (prefs.ema && prefs.type !== 'line' && prefs.type !== 'area') {
      // The swatch carries the colour, the label carries the words. Painting
      // the text in the series colour read 2.5:1 on a light panel - a line
      // colour is chosen to sit on the chart, not to be read as type. The
      // ::before swatch in the stylesheet still identifies which is which.
      h += '<span style="--sw:' + COLORS.ema20 + '">EMA 20</span>'
         + '<span style="--sw:' + COLORS.ema50 + '">EMA 50</span>';
    }
    if (prefs.bb) h += '<span style="color:#8b6cd9">BB 20\u00b72</span>';
    if (state.compare) h += '<span style="color:' + COLORS.compare + '">' +
      escapeHtml(state.compare.symbol) + ' (% change)</span>';
    if (prefs.vwap) h += '<span style="color:' + COLORS.vwap + '">VWAP 20</span>';
    if (prefs.type === 'heikin') h += '<span style="color:' + COLORS.axis + '">Heikin-Ashi</span>';
    legend.innerHTML = h;
  }

  // ------------------------------------------------ indicator math (display only)
  function ema(values, period) {
    const out = new Array(values.length).fill(null);
    if (values.length < period) return out;
    const k = 2 / (period + 1);
    let seed = 0;
    for (let i = 0; i < period; i++) seed += values[i];
    seed /= period;
    out[period - 1] = seed;
    for (let i = period; i < values.length; i++) {
      out[i] = values[i] * k + out[i - 1] * (1 - k);
    }
    return out;
  }

  function bollinger(values, period, mult) {
    const up = new Array(values.length).fill(null);
    const lo = new Array(values.length).fill(null);
    for (let i = period - 1; i < values.length; i++) {
      let sum = 0;
      for (let j = i - period + 1; j <= i; j++) sum += values[j];
      const mean = sum / period;
      let v = 0;
      for (let j = i - period + 1; j <= i; j++) v += (values[j] - mean) * (values[j] - mean);
      const sd = Math.sqrt(v / period);
      up[i] = mean + mult * sd;
      lo[i] = mean - mult * sd;
    }
    return { up: up, lo: lo };
  }

  function heikinAshi(candles) {
    const out = [];
    for (let i = 0; i < candles.length; i++) {
      const c = candles[i];
      const hc = (c.o + c.h + c.l + c.c) / 4;
      const ho = i === 0 ? (c.o + c.c) / 2 : (out[i - 1].o + out[i - 1].c) / 2;
      out.push({ t: c.t, o: ho, c: hc, h: Math.max(c.h, ho, hc), l: Math.min(c.l, ho, hc), v: c.v });
    }
    return out;
  }

  // RSI and MACD for the sub-panes. The engine computed both server-side and
  // the chart displayed neither, so a reader could see "RSI 28 - oversold" in
  // the reasons list with no way to see the shape that produced it.
  function rsiSeries(values, period) {
    const out = new Array(values.length).fill(null);
    if (values.length <= period) return out;
    let gain = 0, loss = 0;
    for (let i = 1; i <= period; i++) {
      const d = values[i] - values[i - 1];
      if (d >= 0) gain += d; else loss -= d;
    }
    gain /= period; loss /= period;
    out[period] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
    for (let i = period + 1; i < values.length; i++) {
      const d = values[i] - values[i - 1];
      gain = (gain * (period - 1) + (d > 0 ? d : 0)) / period;
      loss = (loss * (period - 1) + (d < 0 ? -d : 0)) / period;
      out[i] = loss === 0 ? 100 : 100 - 100 / (1 + gain / loss);
    }
    return out;
  }

  function macdSeries(values) {
    const f = ema(values, 12), s = ema(values, 26);
    const line = values.map((_, i) => (f[i] === null || s[i] === null) ? null : f[i] - s[i]);
    const start = 25;
    const sig = new Array(values.length).fill(null);
    const sub = line.slice(start).map(v => (v === null ? 0 : v));
    ema(sub, 9).forEach((v, i) => { sig[start + i] = v; });
    const hist = line.map((v, i) => (v === null || sig[i] === null) ? null : v - sig[i]);
    return { line, sig, hist };
  }

  function vwapSeries(candles, period) {
    const out = new Array(candles.length).fill(null);
    let pv = 0, vv = 0;
    const pvh = [], vh = [];
    for (let i = 0; i < candles.length; i++) {
      const c = candles[i];
      pvh[i] = ((c.h + c.l + c.c) / 3) * c.v;
      vh[i] = c.v;
      pv += pvh[i]; vv += vh[i];
      if (i >= period) { pv -= pvh[i - period]; vv -= vh[i - period]; }
      if (i >= period - 1 && vv > 0) out[i] = pv / vv;
    }
    return out;
  }

  function computeDerived() {
    const closes = state.candles.map(c => c.c);
    state.ema20 = ema(closes, 20);
    state.ema50 = ema(closes, 50);
    const bb = bollinger(closes, 20, 2);
    state.bbU = bb.up;
    state.bbL = bb.lo;
    state.rsi = prefs.rsi ? rsiSeries(closes, 14) : null;
    state.macd = prefs.macd ? macdSeries(closes) : null;
    state.vwap = prefs.vwap ? vwapSeries(state.candles, 20) : null;
  }

  // ------------------------------------------------ layout + drawing
  const PAD = { top: 14, right: 64, bottom: 42, left: 10 };
  const TF_MS = { '1m': 60000, '3m': 180000, '5m': 300000, '15m': 900000, '30m': 1800000, '1h': 3600000,
                  '4h': 14400000, '1d': 86400000, '1w': 604800000, '1mo': 2592000000 };
  const VOL_H = 0.16; // share of plot height reserved for volume

  function resize() {
    const dpr = window.devicePixelRatio || 1;
    canvas.width = wrap.clientWidth * dpr;
    canvas.height = wrap.clientHeight * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    draw();
  }

  function fmt(n) {
    if (n === null || n === undefined || isNaN(n)) return '—';
    const d = digitsFor(n);
    const s = n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: d });
    return s;
  }

  function fmtTime(ms, tf) {
    const d = new Date(ms);
    if (tf === '1mo') return d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
    const day = ['1d', '1w'].includes(tf);
    return day
      ? d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: '2-digit' })
      : d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  // Zoom window helpers: count = candles shown, end = index after the last
  // visible candle (null = stick to the newest data as it arrives).
  function viewBounds(plotW) {
    const len = state.candles.length;
    const autoMax = Math.max(20, Math.floor(plotW / 5));
    let count = state.view.count !== null ? state.view.count : Math.min(autoMax, len);
    count = Math.max(10, Math.min(len, count));
    let end = state.view.end !== null ? state.view.end : len;
    end = Math.max(count, Math.min(len, end));
    return { count: count, end: end };
  }

  function draw() {
    const W = wrap.clientWidth;
    const H = wrap.clientHeight;
    ctx.clearRect(0, 0, W, H);
    if (!state.candles.length) return;
    renderLegend();

    const raw = state.candles;
    const data = prefs.type === 'heikin' ? heikinAshi(raw) : raw;

    const plotW = W - PAD.left - PAD.right;
    const plotH = H - PAD.top - PAD.bottom;
    const showVol = prefs.vol;
    // Sub-panes stack below price: volume, then RSI, then MACD.
    const paneCount = (prefs.rsi ? 1 : 0) + (prefs.macd ? 1 : 0);
    const paneH = paneCount ? Math.max(46, plotH * 0.17) : 0;
    const paneGap = 8;
    const availH = plotH - paneCount * (paneH + paneGap);
    const priceH = showVol ? availH * (1 - VOL_H) - 8 : availH;
    const volTop = PAD.top + priceH + 8;
    const volH = showVol ? availH - priceH - 8 : 0;
    let paneTop = PAD.top + availH + paneGap;

    const vb = viewBounds(plotW);
    const offset = vb.end - vb.count;
    const view = data.slice(offset, vb.end);
    const bw = plotW / view.length;

    let lo = Infinity, hi = -Infinity, vMax = 0;
    for (const c of view) {
      if (c.l < lo) lo = c.l;
      if (c.h > hi) hi = c.h;
      if (c.v > vMax) vMax = c.v;
    }
    // Levels inside the view stretch the scale a little so they stay visible.
    // WHOSE PLAN IS ON THE CHART.
    //
    // Automatic is the engine's: entry, stop and targets from the signal.
    // Manual is the member's own, saved per pair and timeframe. One variable
    // decides, and everything below draws whatever it is handed - so the two
    // modes cannot drift into two different renderers that disagree about
    // what a stop line looks like.
    const autoLv = (state.levels && state.levelsFor === state.symbol + ':' + state.tf) ? state.levels : null;
    const lv = prefs.levels ? autoLv : null;
    if (lv) {
      for (const k of ['entry', 'stop_loss', 'tp1', 'tp2']) {
        const v = parseFloat(lv[k]);
        if (isFinite(v) && v > lo * 0.85 && v < hi * 1.15) { if (v < lo) lo = v; if (v > hi) hi = v; }
      }
    }
    const span = (hi - lo) || 1;
    lo -= span * 0.03; hi += span * 0.03;

    // Manual vertical zoom: dragging the price axis stretches or compresses
    // the scale around its midpoint. Without it the only way to see a tight
    // range was to zoom the time axis until the bars were unreadably wide.
    if (state.yScale !== 1) {
      const mid = (hi + lo) / 2;
      const half = ((hi - lo) / 2) / state.yScale;
      lo = mid - half;
      hi = mid + half;
    }

    // Logarithmic price axis: on a series that has doubled, a linear axis
    // makes early percentage moves invisible next to recent ones.
    const useLog = prefs.log && lo > 0;
    const lgLo = useLog ? Math.log(lo) : lo;
    const lgHi = useLog ? Math.log(hi) : hi;
    const y = p => {
      if (useLog) {
        if (p <= 0) return PAD.top + priceH;
        return PAD.top + priceH - ((Math.log(p) - lgLo) / (lgHi - lgLo)) * priceH;
      }
      return PAD.top + priceH - ((p - lo) / (hi - lo)) * priceH;
    };
    const x = i => PAD.left + (i - offset) * bw + bw / 2;

    // grid + y labels (recessive)
    ctx.strokeStyle = COLORS.grid;
    ctx.fillStyle = COLORS.axis;
    ctx.lineWidth = 1;
    ctx.font = '11px system-ui, sans-serif';
    ctx.textAlign = 'left';
    ctx.textBaseline = 'middle';
    const gridLines = 5;
    for (let g = 0; g <= gridLines; g++) {
      const p = useLog
        ? Math.exp(lgLo + ((lgHi - lgLo) * g) / gridLines)
        : lo + ((hi - lo) * g) / gridLines;
      const yy = y(p);
      ctx.beginPath();
      ctx.moveTo(PAD.left, yy);
      ctx.lineTo(W - PAD.right, yy);
      ctx.stroke();
      ctx.fillText(fmt(p), W - PAD.right + 6, yy);
    }

    // x labels
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    const maxLabels = Math.max(2, Math.floor(plotW / 110));
    const labelEvery = Math.ceil(view.length / maxLabels);
    let lastRight = -Infinity;
    for (let i = 0; i < view.length; i += labelEvery) {
      const label = fmtTime(view[i].t, state.tf);
      const half = ctx.measureText(label).width / 2;
      const lx = Math.min(Math.max(x(i + offset), PAD.left + half), W - PAD.right - half);
      if (lx - half < lastRight + 12) continue;
      ctx.fillText(label, lx, H - PAD.bottom + 8);
      lastRight = lx + half;
    }

    // volume bars
    if (showVol) {
      for (let i = 0; i < view.length; i++) {
        const c = view[i];
        const vh = vMax ? (c.v / vMax) * volH : 0;
        ctx.fillStyle = COLORS.volume;
        ctx.fillRect(x(i + offset) - bw * 0.32, volTop + volH - vh, bw * 0.64, vh);
      }
    }

    // Bollinger band fill + edges (behind price)
    if (prefs.bb) {
      ctx.beginPath();
      let started = false;
      for (let i = 0; i < view.length; i++) {
        const v = state.bbU[i + offset];
        if (v === null || v === undefined) continue;
        if (!started) { ctx.moveTo(x(i + offset), y(v)); started = true; } else ctx.lineTo(x(i + offset), y(v));
      }
      for (let i = view.length - 1; i >= 0; i--) {
        const v = state.bbL[i + offset];
        if (v === null || v === undefined) continue;
        ctx.lineTo(x(i + offset), y(v));
      }
      ctx.closePath();
      ctx.fillStyle = 'rgba(139,108,217,0.08)';
      ctx.fill();
      strokeSeries(state.bbU, 'rgba(139,108,217,0.55)', 1);
      strokeSeries(state.bbL, 'rgba(139,108,217,0.55)', 1);
    }

    // ---- price: 5 chart types -----------------------------------------
    if (prefs.type === 'line' || prefs.type === 'area') {
      const col = window.SMA.upColor || '#2ED3A0';
      if (prefs.type === 'area') {
        ctx.beginPath();
        ctx.moveTo(x(offset), y(view[0].c));
        for (let i = 1; i < view.length; i++) ctx.lineTo(x(i + offset), y(view[i].c));
        ctx.lineTo(x(vb.end - 1), PAD.top + priceH);
        ctx.lineTo(x(offset), PAD.top + priceH);
        ctx.closePath();
        const grad = ctx.createLinearGradient(0, PAD.top, 0, PAD.top + priceH);
        grad.addColorStop(0, col + '55');
        grad.addColorStop(1, col + '05');
        ctx.fillStyle = grad;
        ctx.fill();
      }
      ctx.strokeStyle = col;
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.moveTo(x(offset), y(view[0].c));
      for (let i = 1; i < view.length; i++) ctx.lineTo(x(i + offset), y(view[i].c));
      ctx.stroke();
    } else if (prefs.type === 'bars') {
      for (let i = 0; i < view.length; i++) {
        const c = view[i];
        const up = c.c >= c.o;
        const col = up ? COLORS.up : COLORS.down;
        const cx = x(i + offset);
        const tick = Math.max(2, bw * 0.3);
        ctx.strokeStyle = col;
        ctx.lineWidth = Math.max(1, Math.min(2, bw * 0.12));
        ctx.beginPath();
        ctx.moveTo(cx, y(c.h)); ctx.lineTo(cx, y(c.l));            // range
        ctx.moveTo(cx - tick, y(c.o)); ctx.lineTo(cx, y(c.o));     // open tick left
        ctx.moveTo(cx, y(c.c)); ctx.lineTo(cx + tick, y(c.c));     // close tick right
        ctx.stroke();
      }
    } else {   // candles / heikin
      for (let i = 0; i < view.length; i++) {
        const c = view[i];
        const up = c.c >= c.o;
        const col = up ? COLORS.up : COLORS.down;
        const cx = x(i + offset);
        ctx.strokeStyle = col;
        ctx.fillStyle = col;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(cx, y(c.h));
        ctx.lineTo(cx, y(c.l));
        ctx.stroke();
        const bodyTop = y(Math.max(c.o, c.c));
        const bodyH = Math.max(1, Math.abs(y(c.o) - y(c.c)));
        ctx.fillRect(cx - bw * 0.32, bodyTop, Math.max(1, bw * 0.64), bodyH);
      }
    }

    // EMA overlays (hidden on line/area - the close line IS the price there)
    if (prefs.ema && prefs.type !== 'line' && prefs.type !== 'area') {
      strokeSeries(state.ema20, COLORS.ema20, 2);
      strokeSeries(state.ema50, COLORS.ema50, 2);
    }

    function strokeSeries(series, color, width) {
      ctx.strokeStyle = color;
      ctx.lineWidth = width;
      ctx.beginPath();
      let started = false;
      for (let i = 0; i < view.length; i++) {
        const v = series[i + offset];
        if (v === null || v === undefined) continue;
        const xx = x(i + offset), yy = y(v);
        if (!started) { ctx.moveTo(xx, yy); started = true; }
        else ctx.lineTo(xx, yy);
      }
      ctx.stroke();
    }

    // ---- estimated liquidation clusters --------------------------------
    // Drawn first, and as translucent bands rather than lines, because they
    // are an estimate: a zone where forced orders are likely waiting, not a
    // price anyone promised. The trade-plan lines paint over them.
    // Liquidation clusters are the engine reading the market, so they belong
    // to its side of the site - see the note on support and resistance below.
    const liq = (prefs.levels && state.liq
                 && state.levelsFor === state.symbol + ':' + state.tf)
      ? state.liq : null;
    if (liq && liq.length) {
      for (const c of liq) {
        const v = parseFloat(c.price);
        if (!isFinite(v) || v < lo || v > hi) continue;
        const w = Math.max(0, Math.min(1, parseFloat(c.weight) || 0));
        const yy = y(v);
        // Thickness and opacity both track how much fuel is sitting there.
        const half = 1 + 3 * w;
        // Long liquidations are forced selling, so they carry the down colour.
        const col = c.side === 'long' ? COLORS.down : COLORS.up;
        ctx.fillStyle = hexToRgba(col, 0.07 + 0.16 * w);
        ctx.fillRect(PAD.left, yy - half, W - PAD.right - PAD.left, half * 2);
        // Name the strong ones, on the left so they never collide with the
        // SL/TP labels. An unexplained band on a price chart is just noise.
        if (w >= 0.6) {
          ctx.font = '9px system-ui, sans-serif';
          ctx.textBaseline = 'middle';
          ctx.textAlign = 'left';
          ctx.fillStyle = hexToRgba(col, 0.75);
          ctx.fillText('LIQ ' + fmt(v), PAD.left + 4, yy - half - 6);
        }
      }
    }

    // ---- support and resistance ----------------------------------------
    // Drawn faintly and behind the plan, because they are context rather than
    // instructions: a level the price has respected, not a level anybody is
    // telling you to trade. Labelled on the left so they never collide with
    // the SL/TP labels on the right.
    // These are the ENGINE'S levels - its read of
    // where price has been respected -
    // can mark their own. Reported: the engine's SUPPORT and RESISTANCE were
    // still drawn across a chart whose whole premise is that nobody asked the
    // engine, which is the same leak as the signal panel and just harder to
    // see, because a line on a chart does not look like an opinion until you
    // notice you did not draw it.
    if (prefs.sr && state.sr && state.levelsFor === state.symbol + ':' + state.tf) {
      ctx.font = '10px system-ui, sans-serif';
      ctx.textBaseline = 'middle';
      ctx.textAlign = 'left';
      for (const [key, label, col] of [['support', 'SUPPORT', COLORS.up], ['resistance', 'RESISTANCE', COLORS.down]]) {
        const v = state.sr[key];
        if (!isFinite(v) || v <= 0 || v < lo || v > hi) continue;
        const yy = y(v);
        ctx.strokeStyle = hexToRgba(col, 0.55);
        ctx.lineWidth = 1;
        ctx.setLineDash([2, 4]);
        ctx.beginPath();
        ctx.moveTo(PAD.left, yy);
        ctx.lineTo(W - PAD.right, yy);
        ctx.stroke();
        ctx.setLineDash([]);
        // On a chip, not bare on the canvas. The liquidity bands label
        // themselves on the same side, and two lines of small coloured text
        // over each other is unreadable in the exact place a reader is trying
        // to check a level. Whichever is drawn last now wins cleanly instead
        // of both losing.
        const srText = label + ' ' + fmt(v);
        const srW = ctx.measureText(srText).width;
        // Above the line normally, below it near the top of the pane - that
        // corner belongs to the EMA legend, which is HTML sitting over the
        // canvas, so a chip drawn there loses to it and both become
        // unreadable. Flipping costs nothing and the label stays attached to
        // its own line either way.
        const srUp = yy - PAD.top > 26;
        const srY = srUp ? yy - 16 : yy + 2;
        ctx.fillStyle = 'rgba(13,17,23,0.85)';
        ctx.fillRect(PAD.left + 2, srY, srW + 8, 15);
        ctx.fillStyle = hexToRgba(col, 0.95);
        ctx.fillText(srText, PAD.left + 6, srY + 8);
      }
    }

    // ---- trade-plan levels drawn on the chart -------------------------
    if (lv) {
      // A prefix on every label, so a screenshot of the chart can never be
      // mistaken for the site's own call when it is the reader's, or the
      // other way round. This is a trading site; whose stop that is matters.
      const items = [
        ['entry', 'ENTRY', COLORS.axis],
        ['stop_loss', 'SL', COLORS.down],
        ['tp1', 'TP1', COLORS.up],
        ['tp2', 'TP2', COLORS.up],
        ['tp3', 'TP3', COLORS.up],
      ];
      ctx.font = '10px system-ui, sans-serif';
      ctx.textBaseline = 'middle';
      for (const [key, label, col] of items) {
        const v = parseFloat(lv[key]);
        if (!isFinite(v) || v < lo || v > hi) continue;
        const yy = y(v);
        ctx.strokeStyle = col;
        ctx.setLineDash([6, 5]);
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(PAD.left, yy);
        ctx.lineTo(W - PAD.right, yy);
        ctx.stroke();
        ctx.setLineDash([]);
        const text = label + ' ' + fmt(v);
        const tw2 = ctx.measureText(text).width;
        ctx.fillStyle = 'rgba(13,17,23,0.85)';
        ctx.fillRect(W - PAD.right - tw2 - 12, yy - 8, tw2 + 10, 16);
        ctx.fillStyle = col;
        ctx.textAlign = 'left';
        ctx.fillText(text, W - PAD.right - tw2 - 7, yy);
      }
    }

    // ---- your open positions --------------------------------------------
    //
    // Drawn LAST of the price lines and solid rather than dashed, because a
    // trade you are actually in outranks a plan you might take: where a
    // position's entry sits on top of the plan's, the one with money on it is
    // the one that should be legible.
    //
    // Only the trades on the coin in front of you - the panel lists the rest.
    if (state.positions && state.positions.length) {
      ctx.font = '10px system-ui, sans-serif';
      ctx.textBaseline = 'middle';
      for (const t of state.positions) {
        const long = String(t.side || '').toUpperCase() === 'BUY';
        const marks = [
          [t.entry, 'IN', COLORS.axis],
          [t.stop_loss, 'SL', COLORS.down],
          [t.tp1, 'TP1', COLORS.up],
          [t.tp2, 'TP2', COLORS.up],
          [t.tp3, 'TP3', COLORS.up],
        ];
        for (const [raw, label, col] of marks) {
          const v = parseFloat(raw);
          if (!isFinite(v) || v < lo || v > hi) continue;
          const yy = y(v);
          ctx.strokeStyle = col;
          ctx.lineWidth = label === 'IN' ? 1.6 : 1;
          ctx.beginPath();
          ctx.moveTo(PAD.left, yy);
          ctx.lineTo(W - PAD.right, yy);
          ctx.stroke();
          // Left-hand labels: the right margin already carries the plan's, and
          // two chips at the same price on opposite sides read as two levels
          // rather than one line drawn twice.
          const text = (long ? '\u25b2 ' : '\u25bc ') + label + ' ' + fmt(v);
          const tw3 = ctx.measureText(text).width;
          ctx.fillStyle = 'rgba(13,17,23,0.85)';
          ctx.fillRect(PAD.left + 2, yy - 8, tw3 + 10, 16);
          ctx.fillStyle = col;
          ctx.textAlign = 'left';
          ctx.fillText(text, PAD.left + 6, yy);
        }
      }
    }

    // ---- comparison overlay ---------------------------------------------
    // A second symbol drawn as percent change from the left edge of the view.
    // Absolute prices are incomparable across pairs, so normalising is the
    // only way the shapes can be read against each other.
    if (state.compare && state.compare.candles.length) {
      const cmp = state.compare.candles;
      const byTime = new Map(cmp.map(c => [c.t, c.c]));
      const first = view.length ? byTime.get(view[0].t) : null;
      const baseSelf = view.length ? view[0].c : null;
      if (first && baseSelf) {
        // Map the comparison's percent change onto this chart's price scale.
        ctx.strokeStyle = COLORS.compare;
        ctx.lineWidth = 1.6;
        ctx.setLineDash([5, 4]);
        ctx.beginPath();
        let started = false;
        for (let i = 0; i < view.length; i++) {
          const v = byTime.get(view[i].t);
          if (v === undefined) continue;
          const pct = (v - first) / first;
          const mapped = baseSelf * (1 + pct);
          if (mapped < lo || mapped > hi) { started = false; continue; }
          const xx = x(i + offset), yy = y(mapped);
          if (!started) { ctx.moveTo(xx, yy); started = true; } else ctx.lineTo(xx, yy);
        }
        ctx.stroke();
        ctx.setLineDash([]);
      }
    }

    // ---- VWAP overlay --------------------------------------------------
    if (prefs.vwap && state.vwap) strokeSeries(state.vwap, COLORS.vwap, 1.5);

    // ---- historical signal markers -------------------------------------
    // The chart never showed where past signals fired or how they resolved,
    // even though the site stores exactly that. Plotting them turns the
    // chart into the evidence for the track record.
    // The engine's past calls are the engine's opinion, so they belong to
    if (prefs.markers && state.markers && state.markers.length) {
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = '10px system-ui, sans-serif';
      for (const m of state.markers) {
        let idx = -1;
        for (let i = offset; i < vb.end; i++) {
          if (state.candles[i] && state.candles[i].t >= m.at * 1000) { idx = i; break; }
        }
        if (idx < offset || idx >= vb.end) continue;
        const isBuy = m.signal === 'BUY';
        const cx = x(idx);
        const cy = y(m.price);
        const dy = isBuy ? 14 : -14;
        ctx.fillStyle = isBuy ? COLORS.up : COLORS.down;
        ctx.beginPath();
        ctx.moveTo(cx, cy + dy * 0.35);
        ctx.lineTo(cx - 5, cy + dy);
        ctx.lineTo(cx + 5, cy + dy);
        ctx.closePath();
        ctx.fill();
        if (m.outcome === 'confirmed' || m.outcome === 'invalid') {
          ctx.fillStyle = m.outcome === 'confirmed' ? COLORS.up : COLORS.down;
          ctx.fillText(m.outcome === 'confirmed' ? '✓' : '✕', cx, cy + dy * 1.9);
        }
      }
    }

    // ---- last price tag + candle countdown -----------------------------
    const lastC = state.candles[state.candles.length - 1];
    if (lastC && vb.end === state.candles.length) {
      const py = y(lastC.c);
      if (py > PAD.top - 2 && py < PAD.top + priceH + 2) {
        const txt = fmt(lastC.c);
        ctx.font = 'bold 11px system-ui, sans-serif';
        const tw = ctx.measureText(txt).width;
        const up = lastC.c >= lastC.o;
        ctx.fillStyle = up ? COLORS.up : COLORS.down;
        ctx.fillRect(W - PAD.right + 2, py - 9, tw + 10, 18);
        ctx.fillStyle = '#07090E';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(txt, W - PAD.right + 7, py);
        ctx.setLineDash([3, 3]);
        ctx.strokeStyle = up ? COLORS.up : COLORS.down;
        ctx.beginPath();
        ctx.moveTo(PAD.left, py);
        ctx.lineTo(W - PAD.right, py);
        ctx.stroke();
        ctx.setLineDash([]);
      }
      // Time remaining on the forming candle - the number traders watch most.
      const tfMs = TF_MS[state.tf] || 3600000;
      const left = lastC.t + tfMs - Date.now();
      if (left > 0 && left < tfMs) {
        const mm = Math.floor(left / 60000), ss = Math.floor((left % 60000) / 1000);
        const cd = mm >= 60 ? Math.floor(mm / 60) + 'h ' + (mm % 60) + 'm'
                 : (mm > 0 ? mm + 'm ' + String(ss).padStart(2, '0') + 's' : ss + 's');
        ctx.font = '10px system-ui, sans-serif';
        ctx.fillStyle = COLORS.axis;
        ctx.textAlign = 'right';
        ctx.textBaseline = 'top';
        ctx.fillText('closes in ' + cd, W - PAD.right - 4, PAD.top + 2);
      }
    }

    // ---- sub-panes: RSI and MACD ---------------------------------------
    function paneFrame(top, label) {
      ctx.strokeStyle = COLORS.grid;
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(PAD.left, top);
      ctx.lineTo(W - PAD.right, top);
      ctx.stroke();
      ctx.fillStyle = COLORS.axis;
      ctx.font = '10px system-ui, sans-serif';
      ctx.textAlign = 'left';
      ctx.textBaseline = 'top';
      ctx.fillText(label, PAD.left + 2, top + 2);
    }

    if (prefs.rsi && state.rsi) {
      const top = paneTop;
      paneFrame(top, 'RSI 14');
      const ry = v => top + paneH - (v / 100) * paneH;
      // 30/70 bands make the oversold and overbought zones legible at a glance.
      ctx.strokeStyle = COLORS.grid;
      ctx.setLineDash([3, 3]);
      for (const lvl of [30, 70]) {
        ctx.beginPath();
        ctx.moveTo(PAD.left, ry(lvl));
        ctx.lineTo(W - PAD.right, ry(lvl));
        ctx.stroke();
      }
      ctx.setLineDash([]);
      ctx.strokeStyle = COLORS.rsi;
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      let started = false;
      for (let i = 0; i < view.length; i++) {
        const v = state.rsi[i + offset];
        if (v === null || v === undefined) continue;
        const xx = x(i + offset), yy = ry(v);
        if (!started) { ctx.moveTo(xx, yy); started = true; } else ctx.lineTo(xx, yy);
      }
      ctx.stroke();
      const lastRsi = state.rsi[vb.end - 1];
      if (lastRsi !== null && lastRsi !== undefined) {
        ctx.fillStyle = COLORS.axis;
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(lastRsi.toFixed(1), W - PAD.right + 6, ry(lastRsi));
      }
      paneTop += paneH + paneGap;
    }

    if (prefs.macd && state.macd) {
      const top = paneTop;
      paneFrame(top, 'MACD 12/26/9');
      let mMax = 0;
      for (let i = offset; i < vb.end; i++) {
        for (const s of [state.macd.line[i], state.macd.sig[i], state.macd.hist[i]]) {
          if (s !== null && s !== undefined) mMax = Math.max(mMax, Math.abs(s));
        }
      }
      mMax = mMax || 1;
      const my = v => top + paneH / 2 - (v / mMax) * (paneH / 2 - 4);
      ctx.strokeStyle = COLORS.grid;
      ctx.beginPath();
      ctx.moveTo(PAD.left, my(0));
      ctx.lineTo(W - PAD.right, my(0));
      ctx.stroke();
      for (let i = 0; i < view.length; i++) {
        const hv = state.macd.hist[i + offset];
        if (hv === null || hv === undefined) continue;
        ctx.fillStyle = hv >= 0
          ? 'color-mix(in srgb, ' + COLORS.up + ' 55%, transparent)'
          : 'color-mix(in srgb, ' + COLORS.down + ' 55%, transparent)';
        ctx.globalAlpha = 0.55;
        ctx.fillStyle = hv >= 0 ? COLORS.up : COLORS.down;
        const yTop = Math.min(my(0), my(hv));
        ctx.fillRect(x(i + offset) - bw * 0.28, yTop, Math.max(1, bw * 0.56), Math.abs(my(hv) - my(0)));
        ctx.globalAlpha = 1;
      }
      const strokePane = (series, color) => {
        ctx.strokeStyle = color;
        ctx.lineWidth = 1.4;
        ctx.beginPath();
        let st = false;
        for (let i = 0; i < view.length; i++) {
          const v = series[i + offset];
          if (v === null || v === undefined) continue;
          const xx = x(i + offset), yy = my(v);
          if (!st) { ctx.moveTo(xx, yy); st = true; } else ctx.lineTo(xx, yy);
        }
        ctx.stroke();
      };
      strokePane(state.macd.line, COLORS.ema20);
      strokePane(state.macd.sig, COLORS.ema50);
      paneTop += paneH + paneGap;
    }

    // crosshair with axis labels on both edges
    if (state.hover !== null && state.hover >= offset && state.hover < vb.end) {
      const cx = x(state.hover);
      ctx.strokeStyle = COLORS.crosshair;
      ctx.setLineDash([4, 4]);
      ctx.beginPath();
      ctx.moveTo(cx, PAD.top);
      ctx.lineTo(cx, H - PAD.bottom);
      ctx.stroke();
      if (state.hoverY !== null && state.hoverY > PAD.top && state.hoverY < PAD.top + priceH) {
        ctx.beginPath();
        ctx.moveTo(PAD.left, state.hoverY);
        ctx.lineTo(W - PAD.right, state.hoverY);
        ctx.stroke();
        // Price at the cursor: the chart had a vertical crosshair with no
        // readout, so "what price is my cursor on" was unanswerable.
        const frac = 1 - (state.hoverY - PAD.top) / priceH;
        const pAt = useLog ? Math.exp(lgLo + frac * (lgHi - lgLo)) : lo + frac * (hi - lo);
        const label = fmt(pAt);
        ctx.font = '11px system-ui, sans-serif';
        const lw = ctx.measureText(label).width;
        ctx.setLineDash([]);
        ctx.fillStyle = COLORS.crosshair;
        ctx.fillRect(W - PAD.right + 2, state.hoverY - 9, lw + 10, 18);
        ctx.fillStyle = '#07090E';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(label, W - PAD.right + 7, state.hoverY);
      }
      ctx.setLineDash([]);
      // Time label under the crosshair.
      const hc = state.candles[state.hover];
      if (hc) {
        const tl = fmtTime(hc.t, state.tf);
        ctx.font = '10px system-ui, sans-serif';
        const tw3 = ctx.measureText(tl).width;
        ctx.fillStyle = COLORS.crosshair;
        ctx.fillRect(cx - tw3 / 2 - 5, H - PAD.bottom + 4, tw3 + 10, 16);
        ctx.fillStyle = '#07090E';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(tl, cx, H - PAD.bottom + 12);
      }
    }

    state._layout = { offset: offset, bw: bw, viewLen: view.length, end: vb.end, count: vb.count,
                      plotW: plotW, priceH: priceH,
                      // Everything a second renderer needs to put a point back
                      // where the user put it. The price scale is rebuilt on
                      // every frame from the visible window, the log toggle and
                      // the manual y-zoom; a drawing layer that guessed at any
                      // of those would drift away from the candles the moment
                      // one changed, which is the one thing a trendline must
                      // never do.
                      lo: lo, hi: hi, useLog: useLog, top: PAD.top, left: PAD.left,
                      right: W - PAD.right, bottom: PAD.top + priceH };

  }


  /* ==========================================================================
   * The drawing tools stood here - trendlines, levels, boxes, fibs and notes a
   * visitor owned, with their own storage, their own sync endpoint and their
   * own hit-testing. They belonged to the manual chart, which has been removed:
   * this is a signal service, and a second chart that published nothing was a
   * drawing program bolted to the side of it.
   * ====================================================================== */


  /* ==========================================================================
   * THE PLAN ON THE CHART, WHICH IS THE ENGINE'S.
   *
   * This module used to be twice this size and half of it was a second answer:
   * a manual plan the reader typed, saved setups kept as percentage distances
   * so they could be reapplied, and a mode switch deciding which of the two
   * every other panel on the page was talking about. The manual chart has been
   * removed, so there is one plan, it comes from the signal, and nothing on the
   * page has to ask whose it is.
   * ====================================================================== */

  /**
   * The plan the order ticket is about to open, or null when there is none.
   * One shape, read from the signal's levels.
   */
  function ticketPlan() {
    const L = state.levels;
    if (!L || !(parseFloat(L.stop_loss) > 0)) {
      return null;
    }
    return {
      entry: parseFloat(L.entry) || 0,
      stop_loss: parseFloat(L.stop_loss) || 0,
      tp1: parseFloat(L.tp1) || 0,
      tp2: parseFloat(L.tp2) || 0,
      tp3: parseFloat(L.tp3) || 0,
      side: (state.signal || 'BUY').toUpperCase(),
      mine: false,
    };
  }

  /* The plan card in the side panel. The chart and this card must never show
   * two different sets of numbers under the same three words, so both read
   * state.levels and neither computes its own. */
  function renderPlanCard() {
    const set = function (id, v) { const el = document.getElementById(id); if (el) { el.textContent = v; } };
    const note = document.getElementById('tpNote');
    const L = (state.levels && state.levelsFor === state.symbol + ':' + state.tf) ? state.levels : null;
    set('tpEntry', L ? fmt(L.entry) : '—');
    set('tpSl', L ? fmt(L.stop_loss) : '—');
    set('tpTp1', L && L.tp1 > 0 ? fmt(L.tp1) : '—');
    set('tpTp2', L && L.tp2 > 0 ? fmt(L.tp2) : '—');
    set('tpTp3', L && L.tp3 > 0 ? fmt(L.tp3) : '—');
    if (note) { note.textContent = 'Levels appear when the signal is BUY or SELL.'; }
  }

  function planRender() {
    // No early return on the ticket: it is optional - an operator can switch
    // paper trading off - and the bar above the chart is not. Every block below
    // checks for its own element.
    renderPlanCard();
    const lead = document.querySelector('.follow-lead');
    if (lead) {
      lead.innerHTML = 'Opens with the <strong>signal’s</strong> plan. '
        + 'Simulated — no real money, no broker. Settles itself at the stop, a target or the '
        + 'time stop. Funds come from your <a href="portfolio.php">portfolio wallet</a>.';
    }
    const say = document.getElementById('planSay');
    if (say) {
      // Named for what it gives you, not for how it was produced. "Chart
      // signal" is the engine's read of the coin in front of you; the Scanner
      // beside it is the same engine's read of every other coin.
      say.textContent = 'Chart signal: everything here is our engine’s read of this coin. '
        + 'Scanner has the same for every other coin, ranked.';
    }
    // The ticket's own state is recomputed here rather than only when a new
    // signal arrives, so it never sits on a stale answer.
    if (typeof followSync === 'function') { followSync(); }
  }

  function initPlan() {
    planRender();
  }

  // ------------------------------------------------ zoom + pan
  function zoomAt(factor, anchorFrac) {
    const L = state._layout;
    if (!L) return;
    const len = state.candles.length;
    const newCount = Math.max(10, Math.min(len, Math.round(L.count * factor)));
    if (newCount === L.count) return;
    const anchorIdx = L.offset + anchorFrac * L.count;
    let end = Math.round(anchorIdx + (1 - anchorFrac) * newCount);
    end = Math.max(newCount, Math.min(len, end));
    state.view.count = newCount >= len ? null : newCount;
    state.view.end = end >= len ? null : end;
    draw();
  }

  function panBy(candleDelta) {
    const L = state._layout;
    if (!L) return;
    const len = state.candles.length;
    let end = Math.round((state.view.end === null ? len : state.view.end) + candleDelta);
    end = Math.max(L.count, Math.min(len, end));
    state.view.end = end >= len ? null : end;
    draw();
  }

  canvas.addEventListener('wheel', function (ev) {
    if (!state._layout) return;
    ev.preventDefault();
    const rect = canvas.getBoundingClientRect();
    const frac = Math.max(0, Math.min(1, (ev.clientX - rect.left - PAD.left) / (state._layout.plotW || 1)));
    zoomAt(ev.deltaY > 0 ? 1.18 : 1 / 1.18, frac);
  }, { passive: false });

  let drag = null;
  let yDrag = null;
  canvas.addEventListener('mousedown', function (ev) {
    if (!state._layout) return;
    const rect = canvas.getBoundingClientRect();
    // The right-hand gutter is the price axis: dragging there rescales the
    // vertical axis instead of panning time.
    if (ev.clientX - rect.left > rect.width - PAD.right) {
      yDrag = { y: ev.clientY, scale: state.yScale };
      canvas.style.cursor = 'ns-resize';
      return;
    }
    drag = { x: ev.clientX, end: state.view.end === null ? state.candles.length : state.view.end, moved: false };
  });
  window.addEventListener('mousemove', function (ev) {
    if (yDrag) {
      const dy = yDrag.y - ev.clientY;
      state.yScale = Math.max(0.2, Math.min(8, yDrag.scale * (1 + dy / 220)));
      draw();
      return;
    }
    if (!drag || !state._layout) return;
    const dx = ev.clientX - drag.x;
    if (Math.abs(dx) > 3) drag.moved = true;
    if (!drag.moved) return;
    const len = state.candles.length;
    let end = Math.round(drag.end - dx / state._layout.bw);
    end = Math.max(state._layout.count, Math.min(len, end));
    state.view.end = end >= len ? null : end;
    state.hover = null;
    state.hoverY = null;
    tip.style.display = 'none';
    draw();
  });
  window.addEventListener('mouseup', function () {
    drag = null;
    if (yDrag) { yDrag = null; canvas.style.cursor = ''; }
  });

  // ------------------------------------------------ hover tooltip
  function showTip(mxClient, myClient) {
    const L = state._layout;
    if (!L || !state.candles.length) return;
    const rect = canvas.getBoundingClientRect();
    const mx = mxClient - rect.left;
    const idx = L.offset + Math.floor((mx - PAD.left) / L.bw);
    if (idx < L.offset || idx >= L.end) {
      state.hover = null;
      state.hoverY = null;
      tip.style.display = 'none';
      draw();
      return;
    }
    state.hover = idx;
    state.hoverY = myClient - rect.top;
    const c = state.candles[idx];
    const up = c.c >= c.o;
    tip.innerHTML =
      '<span class="t">' + fmtTime(c.t, state.tf) + '</span><br>' +
      'O <span class="' + (up ? 'up' : 'down') + '">' + fmt(c.o) + '</span> ' +
      'H <span class="' + (up ? 'up' : 'down') + '">' + fmt(c.h) + '</span><br>' +
      'L <span class="' + (up ? 'up' : 'down') + '">' + fmt(c.l) + '</span> ' +
      'C <span class="' + (up ? 'up' : 'down') + '">' + fmt(c.c) + '</span><br>' +
      '<span class="t">Vol ' + fmt(c.v) + '</span>';
    tip.style.display = 'block';
    const tw = tip.offsetWidth;
    let left = mx + 14;
    if (left + tw > rect.width - 8) left = mx - tw - 14;
    tip.style.left = Math.max(4, left) + 'px';
    tip.style.top = Math.max(6, myClient - rect.top - 20) + 'px';
    draw();
  }

  canvas.addEventListener('mousemove', function (ev) {
    if (drag && drag.moved) return;   // panning, not hovering
    showTip(ev.clientX, ev.clientY);
  });
  canvas.addEventListener('mouseleave', function () {
    state.hover = null;
    state.hoverY = null;
    tip.style.display = 'none';
    draw();
  });

  // Touch: 1-finger tap = crosshair, 1-finger drag = pan, pinch = zoom.
  let touch = null;
  function pinchDist(ev) {
    const a = ev.touches[0], b = ev.touches[1];
    return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
  }
  canvas.addEventListener('touchstart', function (ev) {
    if (!state._layout) return;
    if (ev.touches.length === 2) {
      touch = { mode: 'pinch', dist: pinchDist(ev), count: state._layout.count };
    } else if (ev.touches.length === 1) {
      touch = { mode: 'tap', x: ev.touches[0].clientX, y: ev.touches[0].clientY,
                end: state.view.end === null ? state.candles.length : state.view.end };
    }
    if (ev.cancelable) ev.preventDefault();
  }, { passive: false });
  canvas.addEventListener('touchmove', function (ev) {
    if (!touch || !state._layout) return;
    if (touch.mode === 'pinch' && ev.touches.length === 2) {
      const ratio = touch.dist / Math.max(20, pinchDist(ev));
      const len = state.candles.length;
      const newCount = Math.max(10, Math.min(len, Math.round(touch.count * ratio)));
      state.view.count = newCount >= len ? null : newCount;
      draw();
    } else if (ev.touches.length === 1) {
      const dx = ev.touches[0].clientX - touch.x;
      if (touch.mode === 'tap' && Math.abs(dx) > 12) touch.mode = 'pan';
      if (touch.mode === 'pan') {
        const len = state.candles.length;
        let end = Math.round(touch.end - dx / state._layout.bw);
        end = Math.max(state._layout.count, Math.min(len, end));
        state.view.end = end >= len ? null : end;
        state.hover = null;
        tip.style.display = 'none';
        draw();
      }
    }
    if (ev.cancelable) ev.preventDefault();
  }, { passive: false });
  canvas.addEventListener('touchend', function (ev) {
    if (touch && touch.mode === 'tap') {
      showTip(touch.x, touch.y);
      tip.style.top = '10px';
      setTimeout(() => { state.hover = null; tip.style.display = 'none'; draw(); }, 1600);
    }
    if (!ev.touches.length) touch = null;
  });

  // ------------------------------------------------ chart toolbar
  const toolbar = document.getElementById('chartToolbar');
  if (toolbar) {
    const typeSel = document.getElementById('ctType');
    if (typeSel) {
      typeSel.value = prefs.type;
      typeSel.addEventListener('change', function () {
        prefs.type = this.value;
        savePrefs();
        draw();
      });
    }

    // The count on the dropdown is what the collapsed control still has to
    // tell you: how much is switched on behind it.
    const indCount = document.getElementById('ctIndCount');
    // Expression, not a declaration - see the note in ui.js for why a
    // block-nested `function name(){}` fails to parse at all under strict
    // mode in Safari/JavaScriptCore, silently accepted only by V8. Every
    // call site below runs after this line, so the lack of hoisting a var
    // assignment carries costs nothing here.
    var refreshIndCount = function() {
      if (!indCount) return;
      const n = Array.from(toolbar.querySelectorAll('[data-ov]'))
        .filter(el => prefs[el.dataset.ov]).length;
      indCount.textContent = n;
      indCount.hidden = n === 0;
    }

    toolbar.querySelectorAll('[data-ov]').forEach(function (b) {
      b.classList.toggle('on', !!prefs[b.dataset.ov]);
      b.setAttribute('aria-pressed', prefs[b.dataset.ov] ? 'true' : 'false');
      b.addEventListener('click', function () {
        prefs[b.dataset.ov] = !prefs[b.dataset.ov];
        b.classList.toggle('on', prefs[b.dataset.ov]);
        b.setAttribute('aria-pressed', prefs[b.dataset.ov] ? 'true' : 'false');
        savePrefs();
        refreshIndCount();
        // RSI, MACD and VWAP are only computed when their panel is on, so
        // toggling one has to recompute before the next paint.
        computeDerived();
        draw();
      });
    });
    refreshIndCount();

    // Indicators popover. Closes on outside click and on Escape, which a
    // panel that covers the chart has to do.
    (function () {
      const btn = document.getElementById('ctIndBtn');
      const panel = document.getElementById('ctIndPanel');
      if (!btn || !panel) return;
      function open(yes) {
        panel.hidden = !yes;
        btn.setAttribute('aria-expanded', yes ? 'true' : 'false');
        btn.classList.toggle('on', yes);
      }
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        open(panel.hidden);
      });
      panel.addEventListener('click', e => e.stopPropagation());
      document.addEventListener('click', () => open(false));
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) { open(false); btn.focus(); }
      });
    })();
    const zi = document.getElementById('ctZoomIn');
    const zo = document.getElementById('ctZoomOut');
    const zr = document.getElementById('ctReset');
    if (zi) zi.addEventListener('click', () => zoomAt(1 / 1.35, 0.5));
    if (zo) zo.addEventListener('click', () => zoomAt(1.35, 0.5));
    if (zr) zr.addEventListener('click', function () {
      state.view = { count: null, end: null };
      state.yScale = 1;
      draw();
    });
  }

  // ------------------------------------------------ compare / fullscreen
  (function () {
    const sel = document.getElementById('compareSel');
    if (sel) {
      sel.addEventListener('change', async function () {
        const sym = this.value;
        if (!sym) { state.compare = null; draw(); return; }
        try {
          const r = await fetch('api.php?action=candles&symbol=' + encodeURIComponent(sym) +
                                '&tf=' + encodeURIComponent(state.tf));
          const j = await r.json();
          if (!j.ok) throw new Error(j.error || 'unavailable');
          state.compare = { symbol: sym, candles: j.candles };
        } catch (e) {
          state.compare = null;
          this.value = '';
        }
        draw();
      });
    }
    const fs = document.getElementById('ctFull');
    if (fs) {
      const panel = document.querySelector('.chart-panel');
      // ONE CLASS FOR BOTH WAYS IN.
      //
      // Real fullscreen is matched by :fullscreen and the fallback by a class,
      // which meant the stylesheet carried the same eight rules twice, under
      // two selectors, kept in step by hand. The handler marks either state
      // with .is-full and the stylesheet has one copy.
      const markFull = function (on) {
        if (panel) { panel.classList.toggle('is-full', !!on); }
      };
      fs.addEventListener('click', function () {
        if (!panel) return;
        if (panel.classList.contains('pseudo-full')) {
          // Already faking it - the browser refused last time, so there is no
          // fullscreenElement to exit and asking again would only fail again.
          panel.classList.remove('pseudo-full');
          markFull(false);
          resize();
          return;
        }
        if (!document.fullscreenElement) {
          (panel.requestFullscreen ? panel.requestFullscreen() : Promise.reject()).catch(function () {
            // Fullscreen can be blocked by policy; the class fallback still
            // gives the chart the whole viewport.
            panel.classList.add('pseudo-full');
            markFull(true);
            resize();
          });
        } else {
          document.exitFullscreen();
        }
      });
      document.addEventListener('fullscreenchange', function () {
        markFull(panel && document.fullscreenElement === panel);
        setTimeout(resize, 60);
      });
    }
  })();

  /**
   * Text alternative for the canvas.
   *
   * A <canvas> is opaque to assistive technology: a screen-reader user got a
   * blank element where the price history is. This mirrors the visible window
   * as a real table, updated whenever the chart redraws.
   */
  function renderChartTable() {
    const host = document.getElementById('chartData');
    if (!host || !state.candles.length) return;
    const L = state._layout;
    const from = L ? L.offset : Math.max(0, state.candles.length - 20);
    const to = L ? L.end : state.candles.length;
    const rows = state.candles.slice(from, to);
    const step = Math.max(1, Math.ceil(rows.length / 40));   // cap at ~40 rows
    let h = '<caption>' + escapeHtml(state.symbol) + ' ' + escapeHtml(state.tf) +
            ' — ' + rows.length + ' candles shown, sampled every ' + step + '</caption>' +
            '<thead><tr><th scope="col">Time</th><th scope="col">Open</th><th scope="col">High</th>' +
            '<th scope="col">Low</th><th scope="col">Close</th><th scope="col">Volume</th></tr></thead><tbody>';
    for (let i = 0; i < rows.length; i += step) {
      const c = rows[i];
      h += '<tr><th scope="row">' + escapeHtml(fmtTime(c.t, state.tf)) + '</th>' +
           '<td>' + fmt(c.o) + '</td><td>' + fmt(c.h) + '</td>' +
           '<td>' + fmt(c.l) + '</td><td>' + fmt(c.c) + '</td><td>' + fmt(c.v) + '</td></tr>';
    }
    host.innerHTML = h + '</tbody>';
  }

  // ------------------------------------------------ data loading
  async function loadChart(quiet) {
    // Captured before the await, not read again after it - the reader can
    // switch coins again while this fetch is still in flight (the dropdown
    // fires one 'change' per keystroke, so rapid switching is ordinary use,
    // not an edge case), and a slower earlier request resolving AFTER a
    // faster later one would otherwise overwrite the chart that's actually
    // on screen with the coin the reader already moved on from. tickPrice()
    // already guards this way for the same reason; this brings loadChart()
    // in line with it.
    const reqSymbol = state.symbol;
    const reqTf = state.tf;
    if (!quiet) {
      statusEl.textContent = 'Loading chart…';
      statusEl.classList.remove('hidden');
      state.view = { count: null, end: null };   // new pair/tf -> reset zoom
      state.levels = null;
      state.liq = null;
    }
    try {
      const r = await fetch('api.php?action=candles&symbol=' + encodeURIComponent(reqSymbol) + '&tf=' + encodeURIComponent(reqTf));
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || 'Failed to load candles');
      if (reqSymbol !== state.symbol || reqTf !== state.tf) { return; }   // stale - a newer request already took over
      state.candles = j.candles;
      // Exchange-quoted precision beats guessing from magnitude.
      if (j.tick_size && j.tick_size > 0) {
        // From the NUMBER, not from String(tick). JSON hands back 1e-8 for a
        // sub-cent tick, String() of that is "1e-8", it has no decimal point,
        // and the old parse read that as zero decimal places - which is how a
        // memecoin chart ended up showing 0 on every price it drew.
        tickDigits = Math.max(0, Math.min(12, Math.round(-Math.log10(j.tick_size))));
      } else {
        tickDigits = null;
      }
      computeDerived();
      statusEl.classList.add('hidden');
      draw();
      canvas.classList.add('ready');   // see #chart.ready in style.css
      renderChartTable();
      tickPrice();
      // Re-price the open trades off the candles that have just arrived. Done
      // here rather than on a timer of its own, so the profit on screen is
      // always computed from the price the chart is currently drawing - and
      // called after draw(), never from inside it, because render() draws.
      if (window.SMA.positionsRepaint) { window.SMA.positionsRepaint(); }
    } catch (e) {
      statusEl.textContent = 'Chart error: ' + e.message;
      statusEl.classList.remove('hidden');
    }
  }

  // ------------------------------------------------ signal UI
  const badge = document.getElementById('signalBadge');
  const card = document.getElementById('signalCard');

  async function analyse(quiet) {
    // Same stale-response guard as loadChart() - captured before the await
    // so a symbol/tf switch made while this request is still in flight can't
    // have this slower response land on top of the newer one and revert the
    // signal panel to the coin the reader already left.
    const reqSymbol = state.symbol;
    const reqTf = state.tf;
    const btn = document.getElementById('analyseBtn');
    if (!quiet) {
      btn.disabled = true;
      btn.textContent = 'Analysing…';
    }
    try {
      const r = await fetch('api.php?action=signal&symbol=' + encodeURIComponent(reqSymbol) + '&tf=' + encodeURIComponent(reqTf));
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || 'Analysis failed');
      if (reqSymbol !== state.symbol || reqTf !== state.tf) { return; }   // stale - a newer request already took over

      const changed = badge.textContent !== verdictLabel(j.signal);
      card.className = 'signal-card ' + j.signal.toLowerCase();
      badge.textContent = verdictLabel(j.signal);
      if (changed) {
        badge.classList.remove('pop');
        void badge.offsetWidth;   // restart the pop animation
        badge.classList.add('pop');
        const live = document.getElementById('signalLive');
        if (live) {
          live.textContent = j.signal === 'NEUTRAL'
            ? state.symbol + ' on ' + state.tf + ' has no setup right now.'
            : state.symbol + ' on ' + state.tf + ' is now ' + j.signal +
              ', grade ' + (j.grade || 'unknown') + ', confidence ' + j.confidence + ' percent.';
        }
      }
      const confEl = document.getElementById('sigConfidence');
      confEl.textContent = j.confidence + '%';
      // The basis is the difference between a measured win rate and an
      // educated guess, and a reader deserves to be told which one this is.
      confEl.title = CONF_BASIS[j.confidence_basis]
        ? CONF_BASIS[j.confidence_basis](j)
        : 'Estimate from the strength of the setup.';
      confEl.style.borderBottom =
        (j.confidence_basis === 'measured' || j.confidence_basis === 'model_learned')
          ? '1px dotted var(--up)' : '';
      renderConfDims(j);
      document.getElementById('sigScore').textContent = (j.score > 0 ? '+' : '') + j.score;
      const gradeEl = document.getElementById('sigGrade');
      if (gradeEl) {
        gradeEl.textContent = j.grade || '—';
        gradeEl.style.color = j.grade === 'A+' || j.grade === 'A' ? 'var(--up)'
          : (j.grade === 'B' ? 'var(--warn-text)' : '');
      }
      document.getElementById('sigPrice').textContent = fmt(j.price);
      // The reference of the published call this reading agrees with. Absent
      // whenever the reading is not a published call - see api.php - and the
      // row goes away with it rather than showing a dash nobody can act on.
      const refBox = document.getElementById('sigRef');
      if (refBox) {
        const refTxt = document.getElementById('sigRefText');
        if (j.ref) {
          if (refTxt) { refTxt.textContent = j.ref; }
          refBox.hidden = false;
        } else {
          refBox.hidden = true;
        }
      }
      // The deadline this call was published with, handed to the ticker below.
      // Read from levels rather than recomputed: the engine chose the window
      // when it chose the type, and a second opinion about it here would be a
      // second answer to a question already settled.
      setSignalLife(j);
      renderRegime(j);
      renderMtf(j);
      renderSmc(j);

      // Recent signal changes (latest first -> render oldest to newest)
      const histEl = document.getElementById('sigHistory');
      if (histEl) {
        histEl.innerHTML = '';
        const hist = (j.history || []).slice().reverse();
        if (hist.length > 1) {
          histEl.appendChild(Object.assign(document.createElement('span'), { textContent: 'Recent flips:' }));
          hist.forEach(function (h, idx) {
            if (idx > 0) {
              const ar = document.createElement('span');
              ar.className = 'h-arrow';
              ar.textContent = '→';
              histEl.appendChild(ar);
            }
            const chip = document.createElement('span');
            chip.className = 'h-chip ' + h.signal.toLowerCase();
            // Outcome verification: ✓ confirmed (TP1 before SL), ✗ invalid,
            // ⏱ expired (time stop — excluded from the win rate).
            const mark = h.outcome === 'confirmed' ? ' ✓'
              : (h.outcome === 'invalid' ? ' ✗' : (h.outcome === 'expired' ? ' ⏱' : ''));
            chip.textContent = h.signal + mark;
            chip.title = new Date(h.at * 1000).toLocaleString() + (h.note ? ' — ' + h.note : '');
            histEl.appendChild(chip);
          });
        }
        // Verified track record for this pair
        if (j.stats && j.stats.evaluated > 0) {
          const s = document.createElement('span');
          s.style.cssText = 'flex-basis:100%;margin-top:2px';
          s.textContent = 'Track record: ' + j.stats.wins + '/' + j.stats.evaluated +
            ' signals closed in profit (' + j.stats.winrate + '%) — past results ≠ future performance.';
          histEl.appendChild(s);
        }
      }
      document.getElementById('lvlSupport').textContent = fmt(j.indicators.support);
      document.getElementById('lvlResistance').textContent = fmt(j.indicators.resistance);
      document.getElementById('lvlRsi').textContent = fmt(j.indicators.rsi);
      document.getElementById('lvlAtr').textContent = fmt(j.indicators.atr);

      // Trade plan (entry / SL / TPs) - present only for BUY/SELL signals
      const L = j.levels;
      state.levels = L || null;
      state.signal = j.signal || null;
      state.liq = (j.indicators && j.indicators.liq_clusters) || null;
      // Support and resistance were computed by the engine, printed as two
      // numbers in the side panel, and never drawn - so the one place they
      // mean something, against the candles that formed them, was the one
      // place they were missing. Kept here so the canvas can put them where
      // they belong.
      state.sr = j.indicators
        ? { support: parseFloat(j.indicators.support), resistance: parseFloat(j.indicators.resistance) }
        : null;
      state.levelsFor = state.symbol + ':' + state.tf;
      followReset();   // this setup replaced the last one
      livePosLoad();   // and the open position may belong to a different pair
      draw();   // repaint SL/TP lines on the chart
      const setL = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
      // entry, stop and targets while this card was still printing the
      // engine's - the same three numbers, different values, side by side,
      // with nothing saying which was which. That is the confusion the mode
      // switch exists to remove, so the card has to obey it too.
      renderPlanCard();
      // Net of trading costs, because that is the ratio that decides whether
      // the trade makes money. Shown gross, a tight-stop setup can read
      // "1 : 1.3" while fees are quietly eating most of the risk.
      // The signal's TYPE first, because that is what it is: every plan
      // carries targets at one, two and three times the risk, and the type
      // says which of them this pair actually reaches inside the deadline the
      // trade was given. The net-of-cost ratio stays in the tooltip, where a
      // second number belongs.
      setL('tpRr', L && (L.rr_type || L.rr)
        ? (L.rr_type ? L.rr_type
           : '1 : ' + (L.rr_net !== undefined && L.rr_net !== null ? L.rr_net : L.rr)) : '—');
      const rrEl = document.getElementById('tpRr');
      if (rrEl && L && L.cost_r !== undefined && L.cost_r !== null) {
        rrEl.title = (L.rr_reach !== undefined && L.rr_reach !== null
            ? 'This pair has reached ' + (L.rr_tier || 1) + 'R inside a window this long in '
              + Math.round(L.rr_reach * 100) + '% of its recent history. '
            : '')
          + 'After costs, 1 : ' + (L.rr_net !== undefined && L.rr_net !== null ? L.rr_net : L.rr)
          + ' - fees and spread take ' + Math.round(L.cost_r * 100) + '% of the risk'
          + (L.risk_pct ? ' (stop ' + L.risk_pct + '% away)' : '') + '.';
      } else if (rrEl) {
        rrEl.title = '';
      }
      // Only shown when there is one; an empty row would read as missing data.
      const magRow = document.getElementById('tpMagnetRow');
      if (magRow) {
        const hasMagnet = !!(L && L.liq_magnet);
        magRow.hidden = !hasMagnet;
        if (hasMagnet) {
          setL('tpMagnet', fmt(L.liq_magnet) +
            (L.liq_magnet_r ? '  (' + L.liq_magnet_r + 'R)' : ''));
        }
      }
      const exitEl = document.getElementById('exitRules');
      if (exitEl) {
        exitEl.innerHTML = '';
        for (const rule of (L && L.exit_rules) || []) {
          const li = document.createElement('li');
          li.textContent = rule;
          exitEl.appendChild(li);
        }
      }
      const note = document.getElementById('tpNote');
      if (note) note.textContent = L
        ? (j.signal === 'BUY' ? 'Long setup — ' : 'Short setup — ') + 'ATR-based levels, tune multipliers in admin.'
        : 'Levels appear when the signal is BUY or SELL.';
      // The footer already carries the disclaimer on every page, so the
      // sidebar no longer repeats it. Kept tolerant in case a theme
      // puts the element back.
      const dis = document.getElementById('cardNotice');
      if (dis && j.disclaimer) dis.textContent = j.disclaimer;

      // Historical flips plotted on the chart.
      state.markers = (j.history || []).filter(h => h.signal !== 'NEUTRAL').map(h => ({
        at: h.at, signal: h.signal, outcome: h.outcome, price: h.price || j.price
      }));

      // Optional plain-language summary, present only when the operator has
      // configured a provider.
      const aiBox = document.getElementById('aiExplain');
      const aiText = document.getElementById('aiText');
      if (aiBox && aiText) {
        if (j.explanation) {
          aiText.textContent = j.explanation;
          aiBox.hidden = false;
          const ask = document.getElementById('aiAsk');
          if (ask) ask.hidden = false;
        } else {
          aiBox.hidden = true;
        }
      }

      state.lastSignal = j;
      renderGauge(j);
      renderVoteMeter(j);

      // Reasons grouped by evidence category. A flat list of a dozen items
      // made the reader do the sorting; grouping shows at a glance which kinds
      // of evidence are behind the verdict.
      const ul = document.getElementById('reasons');
      ul.innerHTML = '';
      if (!j.reasons.length) {
        ul.innerHTML = '<li class="muted">No knowledge-base rules fired on the latest candle — market is giving no clear edge.</li>';
      } else {
        const groups = {};
        for (const reason of j.reasons) {
          const cat = reason.cat || 'other';
          (groups[cat] = groups[cat] || []).push(reason);
        }
        const order = Object.keys(groups).sort((a, b) => groups[b].length - groups[a].length);
        for (const cat of order) {
          const items = groups[cat];
          const det = document.createElement('li');
          det.className = 'reason-group';
          const bull = items.filter(r => r.side === 'bullish').length;
          const bear = items.length - bull;
          det.innerHTML =
            '<details open><summary>' + escapeHtml(CAT_LABELS[cat] || cat) +
            ' <em>' + (bull ? bull + ' bullish' : '') + (bull && bear ? ' · ' : '') +
            (bear ? bear + ' bearish' : '') + '</em></summary><ul class="reason-sub"></ul></details>';
          const sub = det.querySelector('.reason-sub');
          for (const reason of items) {
            const li = document.createElement('li');
            li.className = reason.side;
            li.innerHTML = '<span class="w">w=' + reason.weight + '</span><strong>' +
              escapeHtml(reason.rule) + ' · ' + reason.side + '</strong><em>' +
              escapeHtml(reason.detail) + '</em>';
            sub.appendChild(li);
          }
          ul.appendChild(det);
        }
        // Transparency: say how many rules were evaluated but stayed silent.
        if (j.rules_evaluated) {
          const foot = document.createElement('li');
          foot.className = 'muted reason-foot';
          foot.textContent = j.reasons.length + ' of ' + j.rules_evaluated +
            ' rules fired — the rest found nothing to say on this candle.';
          ul.appendChild(foot);
        }
      }
      // WHEN THE CALL IS FROM, NOT WHEN THE TAB LAST REFRESHED.
      //
      // This read "updated 14:32:05", which is the browser's poll clock: a
      // fact about the page, in the one line on the panel a reader checks to
      // find out how old the setup in front of them is. On a 4h chart those
      // two numbers are four hours apart. The signal's own time leads, its
      // age in plain words beside it, and the poll time keeps its place in
      // the tooltip because "is this page still live" is a real question,
      // just a different one.
      const su = document.getElementById('signalUpdated');
      if (su) {
        if (j.signal_at) {
          const when = new Date(j.signal_at * 1000);
          su.textContent = timeAgo(j.signal_at) + ' \u00b7 ' +
            when.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
          su.title = 'Read on the candle that closed at ' + when.toLocaleString() +
            '. This page last checked at ' + new Date().toLocaleTimeString() + '.';
        } else {
          su.textContent = 'updated ' + new Date().toLocaleTimeString();
          su.title = '';
        }
      }
    } catch (e) {
      card.className = 'signal-card idle';
      badge.textContent = 'ERROR';
      document.getElementById('reasons').innerHTML =
        '<li class="muted">' + escapeHtml(e.message) + '</li>';
    } finally {
      btn.disabled = false;
      btn.textContent = 'Refresh now';
    }
  }

  // ------------------------------------------------ evidence balance
  // The reasons list is honest but long: twelve items to read before you know
  // which way the weight actually falls. This shows the same information as a
  // per-category balance, with the correlation cap made visible - a clipped
  // category is exactly where the old scoring counted one idea five times.
  const CAT_LABELS = {
    trend: 'Trend', momentum: 'Momentum', volatility: 'Volatility', pattern: 'Patterns',
    volume: 'Volume', structure: 'Structure', sentiment: 'News', positioning: 'Positioning',
    meta: 'Confluence', other: 'Other'
  };

  function renderGauge(j) {
    const el = document.getElementById('scoreGauge');
    if (!el) return;
    // "+2.4" means nothing without knowing where the thresholds sit. The gauge
    // shows the score against the buy/sell lines it has to clear.
    const buy = j.buy_threshold, sell = j.sell_threshold;
    if (buy === undefined || sell === undefined) { el.innerHTML = ''; return; }
    const span = Math.max(Math.abs(buy), Math.abs(sell)) * 2.2 || 10;
    const pos = v => Math.max(2, Math.min(98, 50 + (v / span) * 100));
    const sc = pos(j.score);
    const cls = j.signal === 'BUY' ? 'up' : (j.signal === 'SELL' ? 'down' : 'mid');
    el.innerHTML =
      '<div class="gauge-track">' +
        '<i class="gauge-zone sell" style="width:' + pos(sell) + '%"></i>' +
        '<i class="gauge-zone buy" style="left:' + pos(buy) + '%;right:0"></i>' +
        '<i class="gauge-tick" style="left:' + pos(sell) + '%"></i>' +
        '<i class="gauge-tick" style="left:' + pos(buy) + '%"></i>' +
        '<i class="gauge-needle ' + cls + '" style="left:' + sc + '%"></i>' +
      '</div>' +
      '<div class="gauge-labels"><span>SELL ' + sell + '</span><span>0</span><span>BUY +' + buy + '</span></div>';
  }

  function renderVoteMeter(j) {
    const el = document.getElementById('voteMeter');
    if (!el) return;
    const cats = j.categories || {};
    const keys = Object.keys(cats);
    if (!keys.length) {
      el.innerHTML = '<p class="muted" style="font-size:12px">No rules fired on the last closed candle.</p>';
      return;
    }
    let maxAbs = 0;
    keys.forEach(k => { maxAbs = Math.max(maxAbs, Math.abs(cats[k].capped), Math.abs(cats[k].raw)); });
    maxAbs = maxAbs || 1;

    // One box per category rather than a stack of rows: the balance is read
    // by scanning which side the colours fall on, and a grid shows that in a
    // glance where a column of bars has to be read line by line.
    // THE EVIDENCE LEDGER.
    //
    // Was a grid of boxes, one per category, each with its own little bar
    // running left-to-right from its own left edge. That draws every category
    // as a separate object and gives bullish and bearish the same geometry,
    // so which way the evidence falls has to be read off the colours one box
    // at a time.
    //
    // A ledger shares one axis. Every bar starts at the same centre line and
    // grows right for bullish, left for bearish, so the balance is the shape
    // of the column - you see which side the mass is on before reading a
    // single label. Rows are sorted by magnitude, so the thing carrying the
    // verdict is at the top.
    //
    // The cap tick is the point of the whole component. Where a category was
    // clipped, a brass mark sits at the raw value's position with the bar
    // stopping short of it: the gap between bar and tick is exactly the
    // evidence the correlation cap refused to count.
    let h = '<div class="led">';
    keys.sort((a, b) => Math.abs(cats[b].capped) - Math.abs(cats[a].capped)).forEach(function (k) {
      const c = cats[k];
      const bull = c.capped > 0;
      // Bar and cap mark MUST share one scale, or the gap between them stops
      // meaning anything. Headroom of 12% keeps the largest raw value off the
      // track's edge, where overflow:hidden was eating the mark on precisely
      // the most heavily capped row - the one it exists for.
      const scale = maxAbs * 1.12;
      const pct = Math.min(50, Math.abs(c.capped) / scale * 50);
      const rawPct = Math.min(50, Math.abs(c.raw) / scale * 50);
      const capPos = bull ? (50 + rawPct) : (50 - rawPct);
      h += '<div class="led-row">' +
        '<span class="led-cat">' + escapeHtml(CAT_LABELS[k] || k) + '</span>' +
        '<span class="led-track">' +
          '<i class="led-axis"></i>' +
          '<i class="led-bar ' + (bull ? 'up' : 'down') + '" style="width:' + pct + '%"></i>' +
          // The clipped span, shown as ground rather than a line. The brass
          // area between where the bar stops and where the raw value would
          // have reached IS the evidence the correlation cap refused to
          // count - drawing it as a 2px tick made the component's whole
          // argument a detail you had to be told to look for.
          (c.clipped
            ? '<i class="led-ghost" style="left:' + (bull ? (50 + pct) : (50 - rawPct)) +
              '%;width:' + Math.max(0, rawPct - pct) + '%" title="Capped from ' + c.raw +
              ' — correlated rules inside one category cannot count separately"></i>' +
              '<i class="led-cap" style="left:' + capPos + '%"></i>'
            : '') +
        '</span>' +
        '<span class="led-val ' + (bull ? 'up' : 'down') + '">' +
          (c.capped > 0 ? '+' : '') + c.capped.toFixed(1) + '</span>' +
        '</div>';
    });
    h += '</div>';

    const bull = j.bull_categories || 0, bear = j.bear_categories || 0;
    const clipped = keys.filter(k => cats[k].clipped);
    h += '<div class="led-total">' +
         '<span>Net after caps</span>' +
         '<strong class="' + (j.score > 0 ? 'up' : (j.score < 0 ? 'down' : '')) + '">' +
           (j.score > 0 ? '+' : '') + Number(j.score).toFixed(1) + '</strong></div>';
    h += '<p class="led-note">' + bull + ' categor' + (bull === 1 ? 'y' : 'ies') + ' bullish · ' +
         bear + ' bearish';
    if (clipped.length && j.raw_score !== undefined && j.raw_score !== j.score) {
      h += ' · <b>capped</b> from an uncapped sum of ' +
           (j.raw_score > 0 ? '+' : '') + j.raw_score;
    }
    h += '.</p>';

    // A canvas is opaque to a screen reader and so is a row of divs whose
    // meaning is their width. The same figures, as text, for anyone who
    // cannot see the shape.
    el.setAttribute('aria-label',
      'Evidence by category. ' +
      keys.map(k => (CAT_LABELS[k] || k) + ' ' + (cats[k].capped > 0 ? 'plus ' : 'minus ') +
                     Math.abs(cats[k].capped) + (cats[k].clipped ? ', capped' : '')).join('. ') +
      '. Net score ' + j.score + '.');
    el.innerHTML = h;
  }

  // ------------------------------------------------ higher-timeframe ladder

  // Highest frame first, so it reads top-down the way a trader checks it:
  // daily, then 4h, then down to the one being traded. The net number is the
  // weighted verdict, but the rungs are what tell you whether it is a clean
  // trend or a disagreement that happens to average out.
  function renderMtf(j) {
    const wrap = document.getElementById('mtfLadder');
    if (!wrap) return;
    const ind = j.indicators || {};
    const frames = (ind.mtf && ind.mtf.frames) || {};
    const keys = Object.keys(frames);
    if (!keys.length) { wrap.hidden = true; return; }
    wrap.hidden = false;

    const bias = Number(ind.mtf_bias || 0);
    const biasEl = document.getElementById('mtfBias');
    biasEl.textContent = (bias > 0 ? '+' : '') + bias.toFixed(2);
    biasEl.className = bias > 0.1 ? 'up' : (bias < -0.1 ? 'down' : 'flat');
    biasEl.title = bias > 0.1 ? 'The frames above lean up on balance'
      : (bias < -0.1 ? 'The frames above lean down on balance'
                     : 'The frames above disagree — no usable bias');

    let h = '';
    keys.forEach(function (tf) {
      const f = frames[tf];
      const dir = Number(f.dir || 0);
      const cls = dir > 0 ? 'up' : (dir < 0 ? 'down' : 'flat');
      const arrow = dir > 0 ? '▲' : (dir < 0 ? '▼' : '—');
      const pct = Math.round(Number(f.strength || 0) * 100);
      h += '<div class="mtf-cell ' + cls + '" title="' + escapeHtml(f.why || '') + '">' +
        '<span class="mtf-tf">' + escapeHtml(tf) + '</span>' +
        '<span class="mtf-dir">' + arrow + '</span>' +
        '<span class="mtf-str">' + pct + '%</span>' +
        '</div>';
    });
    document.getElementById('mtfGrid').innerHTML = h;
  }

  // ------------------------------------------------ market regime

  const REGIME_LABELS = {
    trending_quiet: 'Trending, quiet', trending_volatile: 'Trending, volatile',
    ranging_quiet: 'Ranging, quiet', ranging_volatile: 'Ranging, volatile',
  };

  // The label alone is trivia. What earns the space is what it changed —
  // a stricter bar, a wider stop, a longer leash — so that is what is shown.
  function renderRegime(j) {
    const wrap = document.getElementById('regimePanel');
    if (!wrap) return;
    const r = (j.indicators || {}).regime;
    if (!r || !r.name || r.name === 'unknown') { wrap.hidden = true; return; }
    wrap.hidden = false;

    const trending = r.name.indexOf('trending') === 0;
    const volatile = r.name.indexOf('volatile') > -1;
    document.getElementById('rgDot').className = 'rg-dot ' + (trending ? 'trend' : 'range') +
      (volatile ? ' hot' : '');
    document.getElementById('rgName').textContent = REGIME_LABELS[r.name] || r.name;

    const p = r.policy || {};
    const bits = [];
    const pct = v => Math.round(Math.abs(v - 1) * 100);
    if (p.threshold && Math.abs(p.threshold - 1) >= 0.02) {
      bits.push((p.threshold > 1 ? 'bar +' : 'bar −') + pct(p.threshold) + '%');
    }
    if (p.stop && Math.abs(p.stop - 1) >= 0.02) {
      bits.push((p.stop > 1 ? 'stop +' : 'stop −') + pct(p.stop) + '%');
    }
    if (p.target && Math.abs(p.target - 1) >= 0.02) {
      bits.push((p.target > 1 ? 'targets +' : 'targets −') + pct(p.target) + '%');
    }
    if (p.categories > 0) bits.push('+' + p.categories + ' confirmation');
    if (p.time_stop && Math.abs(p.time_stop - 1) >= 0.03) {
      bits.push((p.time_stop > 1 ? 'longer' : 'shorter') + ' leash');
    }
    const eff = document.getElementById('rgEffect');
    eff.textContent = bits.length ? bits.join(' · ') : 'no adjustment';
    wrap.title = (p.why || '') +
      (r.certainty !== undefined ? '\n\nClassification certainty ' + Math.round(r.certainty * 100) +
        '% — the policy is scaled down towards no change when the reading is borderline.' : '');
  }

  // ------------------------------------------------ confidence breakdown

  const CONF_LABELS = {
    evidence: 'Evidence', breadth: 'Breadth', alignment: 'Trend alignment',
    structure: 'Structure', regime: 'Regime fit', cost: 'Cost of entry',
  };
  const CONF_HELP = {
    evidence: 'How one-sided the rules that actually fired were. Nine to one and nine to eight can add up to the same score.',
    breadth: 'How many independent evidence categories agreed. One category shouting is an opinion; four agreeing is a setup.',
    alignment: 'Whether the timeframes above this one point the same way. Neutral when there is no higher timeframe to read.',
    structure: 'Whether price is standing somewhere that supports the trade — the right half of the range, at a block, after a sweep.',
    regime: 'Whether this is the kind of market the evidence needs: trend-following rules want a trend, mean-reverting rules want a range.',
    cost: 'What the spread and fees take out of the distance to the stop. A setup whose costs eat a fifth of its risk is a worse trade than the same setup somewhere cheap.',
  };
  const CONF_BASIS = {
    measured: j => 'Measured: setups of this quality on this install have won ' +
      j.confidence + '% of the time across ' + j.confidence_n + ' verified trades.',
    model_learned: j => 'Fitted: a model trained on this install’s own verified outcomes, ' +
      'which only speaks once it beats the base rate out of sample.',
    quality: () => 'Estimated from six measured dimensions of the setup. It becomes a real ' +
      'win rate once enough signals here have been verified.',
    model: () => 'Fallback estimate from the raw score. Not a probability.',
  };

  // The percentage on its own asks to be trusted. The axes behind it can be
  // argued with, which is the point: a reader who disagrees with one of them
  // can see exactly which and discount accordingly.
  function renderConfDims(j) {
    const wrap = document.getElementById('confDims');
    if (!wrap) return;
    const dims = j.confidence_dims;
    if (!dims || !Object.keys(dims).length) { wrap.hidden = true; return; }
    wrap.hidden = false;

    const tag = document.getElementById('confBasisTag');
    if (tag) {
      const measured = j.confidence_basis === 'measured';
      tag.textContent = measured ? 'measured' : (j.confidence_basis === 'model_learned' ? 'fitted' : 'estimated');
      tag.className = 'cd-tag ' + (measured ? 'measured' : '');
    }

    // Weakest first: the axis dragging the number down is the one worth
    // reading, and a geometric mean means it really is dragging.
    let h = '';
    Object.keys(CONF_LABELS)
      .filter(k => dims[k] !== undefined)
      .sort((a, b) => dims[a] - dims[b])
      .forEach(function (k) {
        const v = Math.max(0, Math.min(1, Number(dims[k])));
        const pct = Math.round(v * 100);
        const cls = v >= 0.66 ? 'good' : (v >= 0.4 ? 'mid' : 'poor');
        h += '<div class="cd-row" title="' + escapeHtml(CONF_HELP[k] || '') + '">' +
          '<span class="cd-name">' + escapeHtml(CONF_LABELS[k]) + '</span>' +
          '<span class="cd-track"><i class="cd-bar ' + cls + '" style="width:' + pct + '%"></i></span>' +
          '<span class="cd-val">' + pct + '</span>' +
          '</div>';
      });
    document.getElementById('cdGrid').innerHTML = h;

    const note = document.getElementById('cdNote');
    if (note) note.textContent = (CONF_BASIS[j.confidence_basis] || CONF_BASIS.quality)(j);
  }

  // ------------------------------------------------ market structure

  // Where price is standing, not what to do about it. Each tile shows a
  // structure the engine found, its direction and how old it is; whether any
  // of it was actually voted on is in the reasons list, because the presence
  // of a structure and acting on one are different claims.
  const SMC_LABELS = {
    choch: 'Character change', bos: 'Structure break', sweep: 'Liquidity swept',
    order_block: 'Order block', breaker: 'Breaker', fvg: 'Fair value gap',
  };

  function renderSmc(j) {
    const wrap = document.getElementById('smcPanel');
    if (!wrap) return;
    const s = (j.indicators || {}).smc;
    if (!s) { wrap.hidden = true; return; }

    const zoneEl = document.getElementById('smcZone');
    const zone = s.zone || '';
    if (zone) {
      const pct = Math.round(Number(s.zone_pct || 0) * 100);
      zoneEl.textContent = zone + ' · ' + pct + '%';
      zoneEl.className = zone === 'discount' ? 'up' : (zone === 'premium' ? 'down' : 'flat');
      zoneEl.title = 'Price sits at ' + pct + '% of the current dealing range' +
        (s.range ? ' (' + fmt(s.range.low) + ' to ' + fmt(s.range.high) + ')' : '') +
        '. Below 30% is a discount, above 70% a premium.';
    } else {
      zoneEl.textContent = '—';
      zoneEl.className = 'flat';
      zoneEl.title = '';
    }

    let h = '';
    // Freshest first: a character change two bars ago and one from sixty bars
    // ago are not the same information.
    Object.keys(SMC_LABELS)
      .filter(k => s[k] && typeof s[k].dir === 'number')
      .sort((a, b) => (s[a].age || 0) - (s[b].age || 0))
      .forEach(function (k) {
        const v = s[k];
        const cls = v.dir > 0 ? 'up' : (v.dir < 0 ? 'down' : 'flat');
        const bits = [];
        if (v.age !== undefined) bits.push(v.age + ' bar' + (v.age === 1 ? '' : 's') + ' ago');
        if (v.distance !== undefined) {
          bits.push(v.distance === 0 ? 'price inside' : v.distance + ' ATR away');
        }
        if (v.depth !== undefined) bits.push(v.depth + ' ATR deep');
        h += '<div class="smc-cell ' + cls + '" title="' + escapeHtml(bits.join(' · ')) + '">' +
          '<span class="smc-name">' + escapeHtml(SMC_LABELS[k]) + '</span>' +
          '<span class="smc-dir">' + (v.dir > 0 ? '▲' : (v.dir < 0 ? '▼' : '—')) + '</span>' +
          '<span class="smc-meta">' + escapeHtml(bits[0] || '') + '</span>' +
          '</div>';
      });

    // Equal highs and lows are magnets rather than directional structures, so
    // they get their own line instead of a direction arrow.
    ['eq_highs', 'eq_lows'].forEach(function (k) {
      const v = s[k];
      if (!v) return;
      h += '<div class="smc-cell flat" title="Resting stops price tends to reach for">' +
        '<span class="smc-name">' + (k === 'eq_highs' ? 'Equal highs' : 'Equal lows') + '</span>' +
        '<span class="smc-dir">≡</span>' +
        '<span class="smc-meta">' + fmt(v.level) + '</span>' +
        '</div>';
    });

    if (!h && !zone) { wrap.hidden = true; return; }
    wrap.hidden = false;
    document.getElementById('smcGrid').innerHTML = h ||
      '<p class="muted" style="font-size:11.5px;margin:0">No structures within range right now.</p>';
  }

  // ------------------------------------------------ position sizing

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
  }

  // ------------------------------------------------ controls
  // Signals are fully automatic: analyse on load, on every symbol/timeframe
  // change, and on a live timer. The button is just a manual refresh.
  function refreshAll(quiet) {
    loadChart(quiet).then(() => analyse(quiet));
    // Context is per symbol and timeframe, so it has to follow a switch -
    // showing BTC's funding next to a SOL chart would be worse than showing
    // nothing. Guarded because the panel is admin-toggleable.
    if (window.smaLoadContext) window.smaLoadContext();
    if (window.smaLoadMtfStrip) window.smaLoadMtfStrip();
  }

  // ------------------------------------------------ live price ticker
  // Polls a lightweight micro-cached endpoint so the last candle and the
  // price chip tick in near-real-time while the chart is open.
  let lastTick = null;
  async function tickPrice() {
    if (document.visibilityState !== 'visible' || !state.candles.length) return;
    try {
      const r = await fetch('api.php?action=ticker&symbol=' + encodeURIComponent(state.symbol));
      const j = await r.json();
      if (!j.ok || j.symbol !== state.symbol) return;
      const el = document.getElementById('livePrice');
      const val = document.getElementById('livePriceVal');
      if (val) val.textContent = fmt(j.price);
      if (el) {
        el.classList.remove('up-tick', 'down-tick');
        if (lastTick !== null && j.price !== lastTick) {
          el.classList.add(j.price > lastTick ? 'up-tick' : 'down-tick');
        }
      }
      lastTick = j.price;
      livePosRender(j.price);   // mark the open position to the new price
      // Update the forming candle so the chart itself is live.
      const c = state.candles[state.candles.length - 1];
      c.c = j.price;
      if (j.price > c.h) c.h = j.price;
      if (j.price < c.l) c.l = j.price;
      computeDerived();
      draw();
    } catch (e) { /* transient - next tick */ }
  }
  setInterval(tickPrice, 8000);

  // The timeframe picker follows the coin.
  //
  // Timeframes are per coin now - a thin pair can be restricted to the frames
  // where its structure means anything - but the picker is one control shared
  // by every coin. Left alone it offered 5m for a daily-only pair, drew a
  // series the engine has no verdict for, and the API refused the analysis
  // behind it. Rebuilt on every change, keeping the current frame when the new
  // coin also has it so switching coin does not silently switch timeframe too.
  function syncTfOptions(sym) {
    var sel = document.getElementById('tfSelect');
    var map = (window.SMA && window.SMA.symbolTfs) || {};
    var list = map[sym] || (window.SMA && window.SMA.allTfs) || null;
    if (!sel || !list || !list.length) return;
    var keep = sel.value;
    sel.innerHTML = '';
    list.forEach(function (tf) {
      var o = document.createElement('option');
      o.value = tf;
      o.textContent = tf;
      sel.appendChild(o);
    });
    sel.value = list.indexOf(keep) !== -1 ? keep : list[0];
    if (sel.value !== keep) {
      state.tf = sel.value;
    }
  }

  document.getElementById('symbol').addEventListener('change', function () {
    state.symbol = this.value;
    syncTfOptions(this.value);
    refreshAll();
  });

  // ------------------------------------------------ coin search
  (function () {
    const input = document.getElementById('symbolSearch');
    const box = document.getElementById('symbolResults');
    const sel = document.getElementById('symbol');
    if (!input || !box || !sel) return;
    const coins = Array.from(sel.options).map(o => ({
      v: o.value, label: o.textContent.trim(), locked: o.disabled
    }));
    function hide() { box.hidden = true; box.innerHTML = ''; }
    function choose(v) {
      sel.value = v;
      sel.dispatchEvent(new Event('change'));
      input.value = '';
      hide();
    }
    input.addEventListener('input', function () {
      const q = this.value.trim().toLowerCase();
      if (q === '') { hide(); return; }
      const hits = coins.filter(c =>
        c.v.toLowerCase().includes(q) || c.label.toLowerCase().includes(q)).slice(0, 10);
      box.innerHTML = '';
      if (!hits.length) {
        const d = document.createElement('div');
        d.className = 'sym-none';
        d.textContent = 'No coin matches "' + this.value.trim() + '"';
        box.appendChild(d);
      } else {
        hits.forEach(function (c) {
          const b = document.createElement('button');
          b.type = 'button';
          b.className = 'sym-hit' + (c.locked ? ' locked' : '');
          b.textContent = c.label;
          if (c.locked) {
            b.title = 'Locked on your plan';
            b.addEventListener('click', function () { location.href = 'upgrade.php'; });
          } else {
            b.addEventListener('click', function () { choose(c.v); });
          }
          box.appendChild(b);
        });
      }
      box.hidden = false;
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const first = box.querySelector('.sym-hit:not(.locked)');
        if (first) first.click();
      } else if (e.key === 'Escape') {
        hide();
      }
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.sym-pick')) hide();
    });
  })();
  document.getElementById('tfSelect').addEventListener('change', function () {
    state.tf = this.value;
    refreshAll();
  });
  document.getElementById('analyseBtn').addEventListener('click', () => refreshAll());

  // ------------------------------------------------ live news + events
  function timeAgo(ts, now) {
    const s = Math.max(0, now - ts);
    if (s < 90) return 'just now';
    if (s < 3600) return Math.floor(s / 60) + 'm ago';
    if (s < 86400) return Math.floor(s / 3600) + 'h ago';
    return Math.floor(s / 86400) + 'd ago';
  }

  function timeUntil(ts, now) {
    const s = ts - now;
    if (s <= 0 && s > -3600) return 'NOW';
    if (s <= 0) return 'started';
    if (s < 3600) return 'in ' + Math.floor(s / 60) + 'm';
    if (s < 86400) return 'in ' + Math.floor(s / 3600) + 'h';
    return 'in ' + Math.floor(s / 86400) + 'd';
  }

  async function loadNews() {
    const list = document.getElementById('newsList');
    const evList = document.getElementById('eventsList');
    if (!list || !evList) return;
    try {
      const r = await fetch('api.php?action=news');
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || 'news unavailable');

      list.innerHTML = j.news.length ? '' : '<li class="muted">No headlines available right now.</li>';
      for (const item of j.news) {
        const li = document.createElement('li');
        // The link is checked here as well as on the server. Titles and
        // summaries go in as textContent and are safe by construction, but an
        // href is the one place third-party text becomes executable: set
        // `javascript:...` on it and clicking the headline runs script on this
        // origin. Anything that is not plain http(s) renders as text with no
        // link at all.
        const safeUrl = /^https?:\/\//i.test(String(item.url || '')) ? item.url : '';
        const a = document.createElement(safeUrl ? 'a' : 'span');
        if (safeUrl) {
          a.href = safeUrl;
          a.target = '_blank';
          a.rel = 'noopener nofollow';
        }
        a.textContent = item.title;
        const meta = document.createElement('div');
        meta.className = 'news-meta';
        meta.innerHTML = '<span class="news-src">' + escapeHtml(item.source_name) + '</span><span>' +
          timeAgo(item.published_at, j.now) + '</span>';
        li.appendChild(a);
        li.appendChild(meta);
        if (item.summary) {
          const p = document.createElement('div');
          p.className = 'news-sum';
          p.textContent = item.summary;
          li.appendChild(p);
        }
        list.appendChild(li);
      }

      evList.innerHTML = j.events.length ? '' : '<li class="muted">No upcoming events in the feed.</li>';
      for (const ev of j.events) {
        const li = document.createElement('li');
        const d = new Date(ev.event_time * 1000);
        const isNow = ev.event_time <= j.now && ev.event_time > j.now - 3600;
        if (isNow) li.classList.add('ev-now');
        const impact = (ev.impact || 'none').toLowerCase();
        li.innerHTML =
          '<div class="ev-time">' + escapeHtml(timeUntil(ev.event_time, j.now)) + '<br>' +
            d.toLocaleString(undefined, { weekday: 'short', hour: '2-digit', minute: '2-digit' }) + '</div>' +
          '<div class="ev-title">' + escapeHtml(ev.country + ' · ' + ev.title) +
            (ev.forecast ? '<small>forecast ' + escapeHtml(ev.forecast) +
              (ev.previous ? ' · prev ' + escapeHtml(ev.previous) : '') + '</small>' : '') + '</div>' +
          '<span class="impact ' + escapeHtml(impact) + '">' + escapeHtml(ev.impact || '—') + '</span>';
        evList.appendChild(li);
      }

      const upd = document.getElementById('newsUpdated');
      if (upd) upd.textContent = 'updated ' + new Date().toLocaleTimeString();
    } catch (e) {
      list.innerHTML = '<li class="muted">News temporarily unavailable: ' + escapeHtml(e.message) + '</li>';
    }
  }

  // HOW LONG AGO, IN WORDS, IN ONE PLACE.
  //
  // The scanner sheet counted in bars, the signal panel printed a clock time
  // and the rest said nothing, so the same fact arrived in three shapes on one
  // page. Bars are still the right unit where a setup's own lifetime is what
  // matters - twelve bars means something different on 15m and on 4h - but
  // "how long ago" is a question in minutes and hours, and it is asked here.
  //
  // Seconds are rounded up to "just now" rather than counted: a call is made
  // on a closing candle, and "3s ago" implies a precision the candle does not
  // have.
  /* ==========================================================================
   * HOW LONG A CALL IS STILL A CALL.
   *
   * Every signal carries expires_at: the bar it was read on plus the window
   * the engine chose for its type, so a 1:3 is given three times the room a
   * 1:1 gets. Past that the setup is not the setup any more - it is what the
   * settler calls a time stop - and a reader deciding whether to take it is
   * entitled to know whether they have twenty hours or twenty minutes.
   *
   * ONE INTERVAL, NOT ONE PER REFRESH. The chart re-reads the signal every
   * minute; hanging a new timer off each read would leave a dozen of them
   * running by lunchtime, all writing to the same element. The interval is
   * created once and reads whatever the latest signal left behind.
   * ====================================================================== */
  let sigLife = null;            // {at, expires, tf} of the call on screen
  let sigLifeTimer = null;

  function setSignalLife(j) {
    const L = (j && j.levels) || null;
    const exp = L ? parseInt(L.expires_at, 10) : 0;
    const from = j ? (parseInt(j.signal_at, 10) || parseInt(j.generated, 10) || 0) : 0;
    sigLife = (j && j.signal !== 'NEUTRAL' && exp > 0) ? { at: from, expires: exp } : null;
    if (!sigLifeTimer) {
      // Every second, because this is a clock and a clock that moves in steps
      // of a minute reads as broken. One element, one write, no layout work
      // when nothing changed.
      sigLifeTimer = setInterval(tickSignalLife, 1000);
    }
    tickSignalLife();
  }

  /** "18h 44m", "9m 12s", "under a minute" - the largest two units that matter. */
  function spanText(sec) {
    if (sec < 60) { return sec > 0 ? 'under a minute' : 'expired'; }
    const d = Math.floor(sec / 86400);
    const h = Math.floor((sec % 86400) / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    if (d > 0) { return d + 'd ' + h + 'h'; }
    if (h > 0) { return h + 'h ' + m + 'm'; }
    return m + 'm ' + s + 's';
  }

  function tickSignalLife() {
    const box = document.getElementById('sigLife');
    if (!box) { return; }
    if (!sigLife) { box.hidden = true; return; }
    const now = Math.floor(Date.now() / 1000);
    const left = sigLife.expires - now;
    const total = Math.max(1, sigLife.expires - (sigLife.at || (sigLife.expires - 3600)));
    const used = Math.max(0, Math.min(1, 1 - left / total));
    box.hidden = false;
    const txt = document.getElementById('sigLifeText');
    const fill = document.getElementById('sigLifeFill');
    if (txt) { txt.textContent = left > 0 ? spanText(left) : 'Expired'; }
    if (fill) { fill.style.width = (used * 100).toFixed(1) + '%'; }
    // Three states, and the last one is not a warning - it is a fact about a
    // setup that has finished. The label changes with it, because "Valid for
    // Expired" is not a sentence.
    const lbl = box.querySelector('.sl-lbl');
    box.classList.toggle('is-soon', left > 0 && left <= 3600);
    box.classList.toggle('is-done', left <= 0);
    if (lbl) { lbl.textContent = left > 0 ? 'Valid for' : 'Time stop'; }
    if (left <= 0 && txt) { txt.textContent = 'reached'; }
  }

  function timeAgo(unixSeconds) {
    const s = Math.max(0, Math.floor(Date.now() / 1000) - Number(unixSeconds || 0));
    if (s < 60) { return 'just now'; }
    const m = Math.floor(s / 60);
    if (m < 60) { return m + ' min ago'; }
    const h = Math.floor(m / 60);
    if (h < 24) { return h + (h === 1 ? ' hour ago' : ' hours ago'); }
    const d = Math.floor(h / 24);
    return d + (d === 1 ? ' day ago' : ' days ago');
  }
  window.SMA = window.SMA || {};
  window.SMA.timeAgo = timeAgo;
  // The scanner sheet runs in its own closure and needs the same wording for
  // "how long is left" as the signal card, or the two disagree about what an
  // hour looks like.
  window.SMA.spanText = spanText;

  // ------------------------------------------------ your open positions
  //
  // THE TRADES YOU ARE IN, BESIDE THE CHART YOU OPENED THEM FROM.
  //
  // Every charting application worth using shows your positions next to the
  // price. This one opened a trade and then sent you to another page to find
  // out whether you were still in it, and back again to close it - so the
  // chart could tell you what to do and never what you had done.
  //
  // Live profit is computed HERE from the last candle close, and it is the
  // same arithmetic the settler uses on the server, including the loss cap: a
  // number on this panel must never disagree with the one the portfolio books.
  (function () {
    const panel = document.getElementById('posPanel');
    const list = document.getElementById('posList');
    const note = document.getElementById('posNote');
    const wallet = document.getElementById('posWallet');
    if (!panel || !list || !(window.SMA && window.SMA.isMember)) { return; }

    let all = [];
    let busy = false;

    // "available" is the number that decides whether the next trade can be
    // opened - balance less the margin already committed - and it is the name
    // the endpoint uses. Written once because it is read on load and again
    // after every close.
    function showFunds(funds) {
      if (!wallet || !funds) { return; }
      const avail = Number(funds.available);
      wallet.textContent = isFinite(avail) ? 'Wallet ' + money(avail) + ' free to trade' : '';
    }

    function priceNow() {
      const c = state.candles;
      return c && c.length ? Number(c[c.length - 1].c) : null;
    }

    function money(n) {
      const s = Math.abs(n) >= 1000 ? Math.round(n).toLocaleString() : Math.abs(n).toFixed(2);
      return (n < 0 ? '-$' : '$') + (Math.abs(n) >= 1000 ? s : s);
    }

    function render() {
      const px = priceNow();
      const here = all.filter(t => t.symbol === state.symbol);
      const away = all.filter(t => t.symbol !== state.symbol);
      // The chart only ever draws the coin it is showing.
      state.positions = here;
      panel.hidden = all.length === 0;
      list.innerHTML = '';
      if (!all.length) { draw(); return; }
      [...here, ...away].forEach(function (t) {
        const li = document.createElement('li');
        li.className = 'pos-row' + (t.symbol === state.symbol ? ' pos-here' : '');
        const long = String(t.side || '').toUpperCase() === 'BUY';
        const pnl = t.symbol === state.symbol ? tradePnl(t, px) : null;
        const pnlHtml = pnl === null
          // A position on another coin has no live price on this page, and
          // guessing one from a stale figure would be worse than saying so.
          ? '<span class="pos-pnl muted">&mdash;</span>'
          : '<span class="pos-pnl ' + (pnl >= 0 ? 'up' : 'down') + '">'
            + (pnl >= 0 ? '+' : '') + money(pnl) + '</span>';
        // HOW LONG THIS ONE HAS LEFT.
        //
        // A position settles at the stop, a target or its time stop, and the
        // third of those was the one nothing on screen mentioned - so a trade
        // about to be closed by the clock looked exactly like one with two days
        // in it. The deadline comes from the server with the row (see
        // Paper::deadline) rather than being worked out here from bars and a
        // timeframe, so the panel and the settler cannot disagree.
        const leftS = (parseInt(t.expires_at, 10) || 0) - Math.floor(Date.now() / 1000);
        const clockHtml = t.expires_at
          ? '<span class="pos-left' + (leftS <= 3600 ? ' soon' : '') + '">'
            + (leftS > 0
                ? escapeHtml((window.SMA.spanText ? window.SMA.spanText(leftS) : '')) + ' left'
                : 'time stop due') + '</span>'
          : '';
        li.innerHTML =
          '<span class="pos-sym">' + escapeHtml(t.symbol) + '</span>' +
          '<span class="pos-tf">' + escapeHtml(t.tf || '') + '</span>' +
          '<span class="pos-side ' + (long ? 'buy' : 'sell') + '">' + (long ? 'LONG' : 'SHORT') + '</span>' +
          '<span class="pos-in">in ' + escapeHtml(String(t.entry)) + '</span>' +
          clockHtml +
          pnlHtml +
          '<button type="button" class="pos-close" data-id="' + Number(t.id) + '">Close</button>';
        list.appendChild(li);
      });
      note.textContent = away.length
        ? here.length + ' on this coin, ' + away.length + ' on others. Simulated - no real money.'
        : 'Simulated - no real money. Settles itself at the stop, a target or the time stop.';
      draw();
    }

    // The countdown has to move on its own. render() is cheap - a handful of
    // list rows - and once a minute is the right resolution for something
    // measured in hours; a per-second redraw of the whole panel to change
    // "3h 12m" to "3h 11m" fifty-nine times too often is not.
    setInterval(function () { if (all.length) { render(); } }, 60000);

    async function load() {
      try {
        const r = await fetch('api.php?action=paper');
        const j = await r.json();
        if (!j.ok) { return; }
        all = Array.isArray(j.open) ? j.open : [];
        showFunds(j.funds);
        render();
      } catch (e) { /* a failed poll is not worth a message */ }
    }

    list.addEventListener('click', async function (e) {
      const btn = e.target.closest('.pos-close');
      if (!btn || busy) { return; }
      // Closing a position is spending money, so it is confirmed and the
      // button is locked while the request is in flight - a double tap must
      // not send two closes for one trade.
      if (!confirm('Close this trade at the current market price?')) { return; }
      busy = true;
      btn.disabled = true;
      const was = btn.textContent;
      btn.textContent = 'Closing...';
      try {
        const r = await fetch('api.php?action=paper', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ op: 'close', id: Number(btn.dataset.id) })
        });
        const j = await r.json();
        if (!j.ok) { throw new Error(j.error || 'Could not close that trade'); }
        all = Array.isArray(j.open) ? j.open : [];
        showFunds(j.funds);
        render();
      } catch (err) {
        note.textContent = err.message;
        btn.disabled = false;
        btn.textContent = was;
      } finally {
        busy = false;
      }
    });

    // Re-price on every chart repaint rather than on a clock of its own: the
    // candles are already arriving, and a second timer would show a profit
    // computed off a price the chart is no longer displaying.
    window.SMA.positionsRepaint = render;
    window.SMA.positionsReload = load;
    load();
    setInterval(load, 60000);
  }());

  // ------------------------------------------------ signal alerts
  // User picks one or many timeframes per coin; a background poll keeps
  // running even when the tab is hidden and fires a browser notification
  // the moment a watched pair flips to BUY (bullish) or SELL (bearish).
  const alertsBox = document.getElementById('alertsBox');
  const ALERTS_KEY = 'sma_alerts_v1';

  // Watched pairs live on the SERVER for logged-in members and in
  // localStorage for guests.
  //
  // They used to be localStorage only, which meant a member who opened the
  // site on their phone found an empty watchlist and had to rebuild it - while
  // the server kept sending them alerts for the pairs they could no longer
  // see, because member_alerts.pairs and push_subs.pairs held the same data
  // and nothing ever read it back. A guest has no account to sync to, so
  // localStorage remains exactly right for them.
  let serverLists = [];        // [{id, name, active, pairs:[ "SYM:tf" ]}]
  let activeListId = 0;
  const useServer = () => !!window.SMA.isMember;

  function loadAlertState() {
    try {
      const s = JSON.parse(localStorage.getItem(ALERTS_KEY) || '{}');
      return { pairs: Array.isArray(s.pairs) ? s.pairs : [], last: s.last || {} };
    } catch (e) { return { pairs: [], last: {} }; }
  }
  function saveAlertState(s) {
    // `last` (the per-pair verdict used for change detection) stays local
    // either way: it is per-device notification state, not a preference.
    try { localStorage.setItem(ALERTS_KEY, JSON.stringify(s)); } catch (e) {}
  }

  async function fetchLists() {
    const r = await fetch('api.php?action=watchlists');
    const j = await r.json();
    if (!j.ok) throw new Error(j.error || 'watchlists unavailable');
    serverLists = j.lists || [];
    const active = serverLists.find(l => l.active) || serverLists[0];
    activeListId = active ? active.id : 0;
    return active ? active.pairs.slice() : [];
  }

  async function pushLists(pairs) {
    if (!useServer() || !activeListId) return;
    await fetch('api.php?action=watchlists', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ op: 'pairs', id: activeListId, pairs: pairs })
    });
  }

  // COPYING THE SIGNAL ID.
  //
  // The reference exists to be quoted somewhere else, and retyping nine
  // characters off a phone screen is exactly the transcription this alphabet
  // was designed to survive rather than the one it should have to.
  //
  // navigator.clipboard needs a secure context, which an install served over
  // plain http does not have, so the selection fallback is not defensive
  // padding - it is the path a fair number of these installs will take. Either
  // way the button says what happened rather than looking like it did nothing.
  (function () {
    const btn = document.getElementById('sigRefCopy');
    const txt = document.getElementById('sigRefText');
    if (!btn || !txt) return;
    let revert = null;
    function said(word) {
      btn.textContent = word;
      btn.classList.toggle('done', word === 'Copied');
      clearTimeout(revert);
      revert = setTimeout(function () {
        btn.textContent = 'Copy';
        btn.classList.remove('done');
      }, 1600);
    }
    btn.addEventListener('click', function () {
      const val = (txt.textContent || '').trim();
      if (!val || val === '\u2014') return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(val).then(function () { said('Copied'); },
                                                function () { select(); });
      } else {
        select();
      }
      function select() {
        try {
          const r = document.createRange();
          r.selectNodeContents(txt);
          const sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(r);
          said(document.execCommand && document.execCommand('copy') ? 'Copied' : 'Selected');
        } catch (e) {
          said('Selected');
        }
      }
    });
  })();

  // Bell popover. Same behaviour as the indicators menu: outside click and
  // Escape close it, and a click inside does not.
  (function () {
    const btn = document.getElementById('bellBtn');
    const panel = document.getElementById('bellPanel');
    if (!btn || !panel) return;
    function open(yes) {
      panel.hidden = !yes;
      btn.setAttribute('aria-expanded', yes ? 'true' : 'false');
      btn.classList.toggle('on', yes);
    }
    btn.addEventListener('click', function (e) { e.stopPropagation(); open(panel.hidden); });
    panel.addEventListener('click', e => e.stopPropagation());

    // A LINK TO SOMETHING INSIDE A CLOSED POPOVER HAS TO OPEN IT.
    //
    // The getting-started step "Set up alerts" sends people to
    // charts.php#alertsBox, and the alerts box lives in this panel, which
    // starts hidden. The browser cannot scroll to a target it cannot see, so
    // the link landed on the chart with nothing opened and nothing moved -
    // a checklist step that appeared to do nothing at all. Any hash aimed at
    // the panel or at its contents opens it and puts the bell in view.
    function openFromHash() {
      const h = (location.hash || '').slice(1);
      if (!h) return;
      const target = document.getElementById(h);
      if (!target || !panel.contains(target)) return;
      open(true);
      btn.scrollIntoView({ block: 'center' });
      btn.focus();
    }
    openFromHash();
    window.addEventListener('hashchange', openFromHash);
    document.addEventListener('click', () => open(false));
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !panel.hidden) { open(false); btn.focus(); }
    });
  })();

  if (alertsBox) {
    // ELEVEN NAMES, HOISTED ON PURPOSE.
    // Every one of these was `function name(){}` sitting directly inside
    // this if-block - legal only because V8 accepts a spec violation the
    // rest of the file's `'use strict'` should have forbidden. Safari's
    // JavaScriptCore enforces the real rule and refuses to parse the whole
    // file over it, which is why nothing in app.js has ever run on an
    // iPhone. Fixed by making each one an expression at its original
    // line - but five of them are CALLED earlier in this block than they
    // are defined, relying on the hoisting a declaration gives for free.
    // Declared here, assigned in place below, so every call site still
    // resolves exactly as it did before - only the syntax changed.
    var persist, hydrate, renderListPicker, permissionOk, b64ToU8, registerPush, syncPush, syncUi, notify, pollAlerts, syncEmail;
    const enableBtn = document.getElementById('alertsEnableBtn');
    const configEl = document.getElementById('alertsConfig');
    const watchBtn = document.getElementById('alertWatchBtn');
    const listEl = document.getElementById('watchList');
    const PAIR_CAP = Math.max(1, window.SMA.alertMaxPairs || 12);
    let alertState = loadAlertState();

    // Single place that persists a change: local for guests, server for
    // members (and local too, so the `last` verdict map survives a reload).
    persist = function() {
      saveAlertState(alertState);
      if (useServer()) {
        pushLists(alertState.pairs.map(p => p.s + ':' + p.tf)).catch(function () {
          const n = document.getElementById('watchSyncNote');
          if (n) n.textContent = 'Could not sync to your account — retrying on the next change.';
        });
      }
    }

    /**
     * Hydrate from the server on load. If the account has no pairs yet but
     * this browser does, adopt the local list once so migrating away from
     * localStorage costs the member nothing.
     */
    hydrate = async function() {
      if (!useServer()) return;
      try {
        const serverPairs = await fetchLists();
        const localPairs = alertState.pairs.map(p => p.s + ':' + p.tf);
        if (!serverPairs.length && localPairs.length) {
          await fetch('api.php?action=watchlists', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ op: 'import', pairs: localPairs })
          });
          await fetchLists();
        } else {
          alertState.pairs = serverPairs.map(function (p) {
            const i = p.lastIndexOf(':');
            return { s: p.slice(0, i), tf: p.slice(i + 1) };
          });
          saveAlertState(alertState);
        }
        renderListPicker();
        syncUi();
      } catch (e) {
        // Offline or logged out mid-session: the local copy still works.
      }
    }

    // Named lists: a scalper's 15m set and a swing trader's daily set are
    // different jobs and should not share one bucket.
    renderListPicker = function() {
      const box = document.getElementById('watchListPicker');
      if (!box || !useServer()) return;
      if (serverLists.length <= 0) { box.innerHTML = ''; return; }
      let h = '<label class="wl-label">Watchlist <select id="watchListSel">';
      serverLists.forEach(function (l) {
        h += '<option value="' + l.id + '"' + (l.id === activeListId ? ' selected' : '') + '>' +
             escapeHtml(l.name) + ' (' + l.pairs.length + ')</option>';
      });
      h += '</select></label>' +
           '<button type="button" class="wl-btn" id="watchListNew" title="New watchlist">+</button>' +
           (serverLists.length > 1
             ? '<button type="button" class="wl-btn" id="watchListDel" title="Delete this watchlist">\u2715</button>' : '');
      box.innerHTML = h;

      const sel = document.getElementById('watchListSel');
      if (sel) sel.addEventListener('change', async function () {
        await fetch('api.php?action=watchlists', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ op: 'activate', id: +this.value })
        });
        const pairs = await fetchLists();
        alertState.pairs = pairs.map(function (p) {
          const i = p.lastIndexOf(':');
          return { s: p.slice(0, i), tf: p.slice(i + 1) };
        });
        saveAlertState(alertState);
        renderListPicker();
        syncUi();
        syncPush();
      });
      const add = document.getElementById('watchListNew');
      if (add) add.addEventListener('click', async function () {
        const name = prompt('Name this watchlist', 'Swing setups');
        if (!name) return;
        await fetch('api.php?action=watchlists', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ op: 'create', name: name, pairs: [] })
        });
        await fetchLists();
        renderListPicker();
      });
      const del = document.getElementById('watchListDel');
      if (del) del.addEventListener('click', async function () {
        if (!confirm('Delete this watchlist and everything in it?')) return;
        await fetch('api.php?action=watchlists', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ op: 'delete', id: activeListId })
        });
        const pairs = await fetchLists();
        alertState.pairs = pairs.map(function (p) {
          const i = p.lastIndexOf(':');
          return { s: p.slice(0, i), tf: p.slice(i + 1) };
        });
        saveAlertState(alertState);
        renderListPicker();
        syncUi();
      });
    }

    permissionOk = function() {
      return 'Notification' in window && Notification.permission === 'granted';
    }

    // ---- Web Push: notifications with the browser closed ----
    let pushSub = null;

    b64ToU8 = function(b64) {
      const pad = '='.repeat((4 - b64.length % 4) % 4);
      const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
      return Uint8Array.from(raw, c => c.charCodeAt(0));
    }

    registerPush = async function() {
      if (!window.SMA.vapidKey || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return false;
      }
      try {
        const reg = await navigator.serviceWorker.register('sw.js');
        pushSub = await reg.pushManager.getSubscription() ||
          await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: b64ToU8(window.SMA.vapidKey)
          });
        await fetch('api.php?action=push_subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            subscription: pushSub.toJSON(),
            pairs: alertState.pairs.map(p => p.s + ':' + p.tf)
          })
        });
        return true;
      } catch (e) {
        pushSub = null;   // fall back to in-page notifications
        return false;
      }
    }

    syncPush = async function() {
      if (!pushSub) return;
      try {
        await fetch('api.php?action=push_sync', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            endpoint: pushSub.endpoint,
            pairs: alertState.pairs.map(p => p.s + ':' + p.tf)
          })
        });
      } catch (e) { /* retry on next change */ }
    }

    syncUi = function() {
      const symEl = document.getElementById('alertWatchSym');
      if (symEl) symEl.textContent = state.symbol;
      // The watch list is NOT gated on browser-notification permission.
      //
      // It used to be: declining (or never granting) notifications hid the
      // whole panel, so a member who wanted email or Telegram alerts - or just
      // a saved list of pairs to follow - had no way to manage one. Browser
      // push is one delivery channel among several; permission for it should
      // not decide whether the list exists.
      configEl.style.display = 'block';
      if (permissionOk()) {
        enableBtn.textContent = pushSub
          ? '🔔 Browser alerts on — works even with the browser closed'
          : '🔔 Browser alerts on while the site is open';
        enableBtn.classList.add('on');
      } else {
        enableBtn.textContent = '🔔 Enable browser notifications (optional)';
        enableBtn.classList.remove('on');
      }
      // The bell is collapsed by default, so the count is the only thing
      // telling a member anything is being watched at all.
      const bc = document.getElementById('bellCount');
      if (bc) {
        // The real number, however many digits it takes.
        //
        // It used to read "99+", on the reasoning that a third digit would
        // widen the badge past the edge of the panel. That reasoning was
        // wrong about the geometry: the badge is anchored by its RIGHT edge
        // (right: -6px), so extra digits grow leftwards across the bell and
        // never towards the edge that would clip. Nothing was ever at risk of
        // being cut off - the number was being withheld for a problem that
        // does not exist. Bulk-watch puts hundreds of pairs on an account and
        // "99+" is the same badge at 100 as at 4000.
        //
        // The digit count goes on the element so the stylesheet can shrink
        // the text as the number grows, rather than letting the badge swell
        // until it covers the bell it belongs to.
        // FOUR DIGITS BECOME A MAGNITUDE, NOT SMALLER TYPE.
        //
        // The rule above was right that nothing gets clipped and wrong about
        // where that leaves the reader: keeping every digit meant shrinking
        // the text to seven and a half pixels by 10,000, and a number nobody
        // can read is withheld just as surely as "99+" withheld it. A count
        // is a magnitude - "1.2k" answers "how many am I watching" as well as
        // "1247" does, at a size that can actually be read, and the exact
        // figure is one hover or one open panel away in the title.
        const n = alertState.pairs.length;
        const shown = n >= 10000 ? Math.round(n / 1000) + 'k'
                    : n >= 1000 ? (Math.round(n / 100) / 10) + 'k'
                    : String(n);
        bc.textContent = shown;
        bc.dataset.len = String(shown.length);
        bc.title = n + ' pair' + (n === 1 ? '' : 's') + ' watched';
        bc.hidden = n === 0;
      }
      listEl.innerHTML = '';
      for (const p of alertState.pairs) {
        const li = document.createElement('li');
        const key = p.s + ':' + p.tf;
        const lastSig = (alertState.last[key] || {}).signal || '—';
        li.innerHTML = '<span>' + escapeHtml(p.s) + ' · ' + escapeHtml(p.tf) + '</span>' +
          '<span class="w-sig ' + lastSig.toLowerCase() + '">' + escapeHtml(lastSig) + '</span>';
        const rm = document.createElement('button');
        rm.textContent = '✕';
        rm.title = 'Stop watching';
        rm.addEventListener('click', function () {
          alertState.pairs = alertState.pairs.filter(x => x.s + ':' + x.tf !== key);
          delete alertState.last[key];
          persist();
          syncUi();
          syncPush();
          syncEmail();
        });
        li.appendChild(rm);
        listEl.appendChild(li);
      }
    }

    enableBtn.addEventListener('click', async function () {
      if (!('Notification' in window)) {
        enableBtn.textContent = 'Notifications not supported in this browser';
        return;
      }
      await Notification.requestPermission();
      if (permissionOk()) {
        await registerPush();   // browser-closed delivery when supported
        pollAlerts();
      }
      syncUi();
    });

    watchBtn.addEventListener('click', function () {
      // one or many timeframes, per the user's checkbox selection
      const tfs = Array.from(document.querySelectorAll('#alertTfChecks input:checked')).map(c => c.value);
      if (!tfs.length) {
        watchBtn.textContent = 'Pick at least one timeframe above';
        setTimeout(function () {
          watchBtn.innerHTML = '+ Watch <span id="alertWatchSym"></span> on selected timeframes';
          syncUi();
        }, 1800);
        return;
      }
      for (const tf of tfs) {
        if (!alertState.pairs.some(p => p.s === state.symbol && p.tf === tf)) {
          if (alertState.pairs.length >= PAIR_CAP) break;   // API cap
          alertState.pairs.push({ s: state.symbol, tf: tf });
        }
      }
      persist();
      syncUi();
      syncPush();
      syncEmail();
      pollAlerts();
    });

    // Bulk watch-all: every coin this user can access x each ticked timeframe.
    // Admin-gated (tier + pair cap); the button only renders for qualifying users.
    const bulkBtn = document.getElementById('alertBulkBtn');
    const clearBtn = document.getElementById('alertClearBtn');
    if (bulkBtn) {
      bulkBtn.addEventListener('click', function () {
        const tfs = Array.from(document.querySelectorAll('#alertTfChecks input:checked')).map(c => c.value);
        if (!tfs.length) {
          bulkBtn.textContent = 'Pick at least one timeframe above';
          setTimeout(function () {
            bulkBtn.innerHTML = '⚡ Watch ALL my coins on selected timeframes';
          }, 1800);
          return;
        }
        const syms = Array.from(document.querySelectorAll('#symbol option'))
          .filter(o => !o.disabled).map(o => o.value);
        // Count what the cap actually costs before deciding, so the button can
        // say "20 of your 60 coins" instead of stopping at an arbitrary point
        // and reporting a pair count nobody asked about. The cap counts pairs,
        // and a bulk watch is coins x timeframes - the number a reader has in
        // mind is coins, so that is the number they are told.
        let added = 0, capped = false, coinsAdded = 0;
        for (const s of syms) {
          let touched = false;
          for (const tf of tfs) {
            if (alertState.pairs.some(p => p.s === s && p.tf === tf)) continue;
            if (alertState.pairs.length >= PAIR_CAP) { capped = true; break; }
            alertState.pairs.push({ s: s, tf: tf });
            added++;
            touched = true;
          }
          if (touched) coinsAdded++;
          if (capped) break;
        }
        persist();
        syncUi();
        syncPush();
        syncEmail();
        pollAlerts();
        bulkBtn.textContent = capped
          ? '✓ ' + coinsAdded + ' of ' + syms.length + ' coins — pair limit ('
            + PAIR_CAP + ') reached'
          : '✓ Added ' + added + ' — watching ' + alertState.pairs.length + ' pairs';
        setTimeout(function () {
          bulkBtn.innerHTML = '⚡ Watch ALL my coins on selected timeframes';
        }, 2500);
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        if (!alertState.pairs.length) return;
        alertState.pairs = [];
        alertState.last = {};
        persist();
        syncUi();
        syncPush();
        syncEmail();
      });
    }

    // Watch the coins with the best verified record.
    //
    // The ranking is computed on the server and never in here: the browser
    // does not have the settled ledger, and a client that picked its own
    // "best" coins would be a second opinion about the record on the one
    // surface where there must be exactly one. It asks which coins, and adds
    // them; the ordering, the sample floor and the tier gate all stay behind
    // the endpoint.
    Array.prototype.forEach.call(document.querySelectorAll('.alerts-watch.top'), function (btn) {
      const original = btn.innerHTML;
      const restore = function (ms) { setTimeout(function () { btn.innerHTML = original; btn.disabled = false; }, ms || 2500); };
      btn.addEventListener('click', function () {
        const tfs = Array.from(document.querySelectorAll('#alertTfChecks input:checked')).map(c => c.value);
        if (!tfs.length) {
          btn.textContent = 'Pick at least one timeframe above';
          restore(1800);
          return;
        }
        btn.disabled = true;
        btn.textContent = 'Finding them…';
        fetch('api.php?action=top_coins&by=' + encodeURIComponent(btn.dataset.by || 'winrate'), {
          headers: { 'Accept': 'application/json' }
        })
          .then(r => r.json())
          .then(function (j) {
            if (!j || !j.ok) {
              btn.textContent = (j && j.error) ? j.error : 'Could not load the ranking';
              restore(2500);
              return;
            }
            const coins = (j.coins || []).map(c => c.symbol);
            if (!coins.length) {
              // Not an error, and saying "none" without saying why invites the
              // reader to assume the feature is broken. It is a young record.
              btn.textContent = 'No coin has ' + (j.min_settled || 10) + ' settled trades yet';
              restore(3200);
              return;
            }
            let added = 0, coinsAdded = 0, capped = false;
            for (const s of coins) {
              let touched = false;
              for (const tf of tfs) {
                if (alertState.pairs.some(p => p.s === s && p.tf === tf)) continue;
                if (alertState.pairs.length >= PAIR_CAP) { capped = true; break; }
                alertState.pairs.push({ s: s, tf: tf });
                added++;
                touched = true;
              }
              if (touched) coinsAdded++;
              if (capped) break;
            }
            persist();
            syncUi();
            syncPush();
            syncEmail();
            pollAlerts();
            btn.textContent = capped
              ? '✓ ' + coinsAdded + ' of ' + coins.length + ' coins — pair limit (' + PAIR_CAP + ') reached'
              : (added ? '✓ Added ' + added + ' — watching ' + alertState.pairs.length + ' pairs'
                       : '✓ Already watching all ' + coins.length);
            restore();
          })
          .catch(function () { btn.textContent = 'Could not load the ranking'; restore(); });
      });
    });

    notify = function(title, body) {
      try {
        const n = new Notification(title, { body: body, icon: 'assets/brand/icon-192.png', tag: title });
        n.onclick = function () { window.focus(); n.close(); };
      } catch (e) { /* notification blocked */ }
    }

    pollAlerts = async function() {
      // Poll whenever pairs are watched: the results populate the watch-list
      // verdicts on screen. Only the desktop notification itself needs
      // permission, and that is checked at the point of firing.
      if (!alertState.pairs.length) return;
      const pairs = alertState.pairs.map(p => p.s + ':' + p.tf).join(',');
      try {
        const r = await fetch('api.php?action=alerts&pairs=' + encodeURIComponent(pairs));
        const j = await r.json();
        if (!j.ok) return;
        for (const a of j.alerts) {
          const key = a.symbol + ':' + a.tf;
          const prev = alertState.last[key];
          alertState.last[key] = { signal: a.signal, at: a.at };
          if (prev && prev.signal !== a.signal && (a.signal === 'BUY' || a.signal === 'SELL')
              && permissionOk()) {
            const dir = a.signal === 'BUY' ? '📈 Bullish' : '📉 Bearish';
            notify(dir + ' — ' + a.symbol + ' (' + a.tf + ')',
              a.signal + ' signal · score ' + (a.score > 0 ? '+' : '') + a.score +
              ' · price ' + fmt(a.price));
          }
        }
        saveAlertState(alertState);
        syncUi();
      } catch (e) { /* offline - retry next tick */ }
    }

    // ---- email alerts (members only) ----
    const emailChk = document.getElementById('emailAlertChk');
    syncEmail = async function() {
      if (!emailChk || !window.SMA.isMember) return;
      const note = document.getElementById('emailNote');
      try {
        const r = await fetch('api.php?action=alert_email', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            enabled: emailChk.checked,
            pairs: alertState.pairs.map(p => p.s + ':' + p.tf)
          })
        });
        const j = await r.json();
        if (note) {
          note.textContent = !j.ok ? (j.error || 'Could not save')
            : (emailChk.checked
                ? (j.email_configured ? 'Email alerts on for your watched pairs.'
                                      : 'Saved — but the site has no email sending configured yet.')
                : '');
        }
      } catch (e) { if (note) note.textContent = 'Could not save — will retry.'; }
    }
    if (emailChk) {
      emailChk.checked = !!window.SMA.emailAlertsOn;
      // Say it on arrival, not only after a click. Someone who ticked this
      // last week and has been waiting since sees the same blank label as
      // someone for whom it works.
      const emailNote0 = document.getElementById('emailNote');
      if (emailNote0 && emailChk.checked && !window.SMA.emailConfigured) {
        emailNote0.textContent = 'This site has no email sending set up yet, so nothing can be '
          + 'delivered. Browser notifications above still work.';
      }
      emailChk.addEventListener('change', syncEmail);
    }

    document.getElementById('symbol').addEventListener('change', syncUi);
    syncUi();
    hydrate();
    // Background-friendly: this poll intentionally keeps running while the
    // tab is hidden so alerts arrive instantly.
    const alertSec = Math.max(30, window.SMA.signalAutoSeconds || 60);
    setInterval(pollAlerts, alertSec * 1000);
    if (permissionOk()) {
      // Re-attach the push subscription silently on every visit.
      registerPush().then(function () { syncUi(); pollAlerts(); });
    }
  }

  // ------------------------------------------------ coin favourites
  const FAVS_KEY = 'sma_favs_v1';
  const favBtn = document.getElementById('favBtn');
  const favBar = document.getElementById('favBar');
  const symbolSel = document.getElementById('symbol');

  function loadFavs() {
    try {
      const f = JSON.parse(localStorage.getItem(FAVS_KEY) || '[]');
      return Array.isArray(f) ? f : [];
    } catch (e) { return []; }
  }

  function renderFavs() {
    if (!favBtn || !favBar) return;
    const favs = loadFavs();
    favBtn.textContent = favs.includes(state.symbol) ? '★' : '☆';
    favBtn.classList.toggle('on', favs.includes(state.symbol));
    favBtn.title = favs.includes(state.symbol) ? 'Remove from favourites' : 'Add to favourites';
    favBar.innerHTML = '';
    for (const sym of favs) {
      // Only show favourites the viewer can actually open.
      const opt = symbolSel.querySelector('option[value="' + CSS.escape(sym) + '"]');
      if (!opt || opt.disabled) continue;
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'fav-chip' + (sym === state.symbol ? ' active' : '');
      chip.innerHTML = '<span class="star">★</span>' + escapeHtml(sym);
      chip.addEventListener('click', function () {
        if (state.symbol === sym) return;
        symbolSel.value = sym;
        state.symbol = sym;
        renderFavs();
        refreshAll();
      });
      favBar.appendChild(chip);
    }
  }

  if (favBtn) {
    favBtn.addEventListener('click', function () {
      let favs = loadFavs();
      if (favs.includes(state.symbol)) {
        favs = favs.filter(s => s !== state.symbol);
      } else if (favs.length < 20) {
        favs.push(state.symbol);
      }
      localStorage.setItem(FAVS_KEY, JSON.stringify(favs));
      renderFavs();
    });
    symbolSel.addEventListener('change', renderFavs);
    renderFavs();
  }

  // ------------------------------------------------ share the signal card
  // Renders the current verdict to an offscreen canvas and offers it as a PNG.
  // Sharing a setup previously meant a manual screenshot of the whole page.
  (function () {
    const btn = document.getElementById('shareBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
      const j = state.lastSignal;
      if (!j) return;
      // Wider and taller than before: the card it mirrors now carries the
      // whole plan, the levels it was derived from and where price is
      // standing structurally, and a shared image that drops half of them is
      // worth less than the screenshot it replaced.
      const W = 1000, H = 700, cv = document.createElement('canvas');
      cv.width = W; cv.height = H;
      const g = cv.getContext('2d');
      const css = getComputedStyle(document.body);
      const read = (n, f) => (css.getPropertyValue(n) || '').trim() || f;
      const up = read('--up', '#2ED3A0'), down = read('--down', '#FF6A5F');
      const isBuy = j.signal === 'BUY';
      const accent = j.signal === 'NEUTRAL' ? read('--muted', '#8A94A8') : (isBuy ? up : down);

      g.fillStyle = read('--bg', '#07090E');
      g.fillRect(0, 0, W, H);
      g.fillStyle = accent;
      g.fillRect(0, 0, 8, H);

      g.fillStyle = read('--text', '#EDF0F6');
      g.font = '600 22px system-ui, sans-serif';
      g.fillText(j.symbol + '  ·  ' + j.tf, 48, 62);

      g.fillStyle = accent;
      g.font = '800 96px system-ui, sans-serif';
      g.fillText(verdictLabel(j.signal), 48, 168);

      g.font = '500 18px system-ui, sans-serif';
      g.fillStyle = read('--muted', '#8A94A8');
      g.fillText('Grade ' + (j.grade || '?') + '   ·   score ' + (j.score > 0 ? '+' : '') + j.score +
                 '   ·   confidence ' + j.confidence + '%', 48, 208);

      // The ladder, on the line under the verdict. A shared BUY that omits a
      // daily downtrend is not a shared signal, it is half of one.
      const shareInd = j.indicators || {};
      const shareFrames = (shareInd.mtf && shareInd.mtf.frames) || {};
      const shareKeys = Object.keys(shareFrames);
      if (shareKeys.length) {
        let x = 48;
        g.font = '600 14px system-ui, sans-serif';
        g.fillStyle = read('--muted', '#8A94A8');
        g.fillText('HIGHER TIMEFRAMES', x, 240);
        x += g.measureText('HIGHER TIMEFRAMES').width + 18;
        shareKeys.forEach(function (tf) {
          const f = shareFrames[tf];
          const dir = Number(f.dir || 0);
          const label = tf + ' ' + (dir > 0 ? '▲' : (dir < 0 ? '▼' : '—')) +
                        ' ' + Math.round(Number(f.strength || 0) * 100) + '%';
          g.fillStyle = dir > 0 ? up : (dir < 0 ? down : read('--muted', '#8A94A8'));
          g.fillText(label, x, 240);
          x += g.measureText(label).width + 20;
        });
      }

      const text = read('--text', '#EDF0F6');
      const muted = read('--muted', '#8A94A8');
      const border = read('--border', '#141A25');

      // Two columns: what to do on the left, what it was read from on the
      // right, matching the order of the card on screen.
      function column(x, heading, rows) {
        let y = 272;
        g.fillStyle = muted;
        g.font = '600 12px system-ui, sans-serif';
        g.fillText(heading.toUpperCase(), x, y);
        g.strokeStyle = border;
        g.lineWidth = 1;
        g.beginPath();
        g.moveTo(x, y + 10);
        g.lineTo(x + 400, y + 10);
        g.stroke();
        y += 40;
        g.font = '500 17px system-ui, sans-serif';
        for (const [label, val, col] of rows) {
          if (val === null || val === undefined || val === '') continue;
          g.fillStyle = muted;
          g.fillText(label, x, y);
          g.fillStyle = col || text;
          const w = g.measureText(String(val)).width;
          g.fillText(String(val), x + 400 - w, y);   // right-aligned figures
          y += 34;
        }
        return y;
      }

      const L = j.levels;
      const ind = j.indicators || {};
      let yEnd = 272;
      if (L) {
        yEnd = column(48, 'Trade plan', [
          ['Entry', fmt(L.entry), text],
          ['Stop loss', fmt(L.stop_loss), down],
          ['Target 1', fmt(L.tp1), up],
          ['Target 2', fmt(L.tp2), up],
          ['Target 3', fmt(L.tp3), up],
          ['Risk : Reward', L.rr ? '1 : ' + L.rr : null, text],
          ['Liquidity magnet', L.liq_magnet ? fmt(L.liq_magnet) +
            (L.liq_magnet_r ? '  (' + L.liq_magnet_r + 'R)' : '') : null, text],
        ]);
      } else {
        g.fillStyle = muted;
        g.font = '500 17px system-ui, sans-serif';
        g.fillText('No active trade plan on this verdict.', 48, 300);
        yEnd = 332;
      }
      // Right column: what the plan was read from, then where price is
      // standing structurally - the two answer different questions and the
      // second is the one a screenshot usually leaves out.
      const smc = ind.smc || {};
      const smcRows = [];
      if (smc.zone) {
        smcRows.push(['Range position',
          smc.zone + ' (' + Math.round(Number(smc.zone_pct || 0) * 100) + '%)',
          smc.zone === 'discount' ? up : (smc.zone === 'premium' ? down : muted)]);
      }
      Object.keys(SMC_LABELS)
        .filter(k => smc[k] && typeof smc[k].dir === 'number')
        .sort((a, b) => (smc[a].age || 0) - (smc[b].age || 0))
        .slice(0, 2)   // two freshest; the canvas has room for no more
        .forEach(function (k) {
          const v = smc[k];
          smcRows.push([SMC_LABELS[k],
            (v.dir > 0 ? '▲' : '▼') + '  ' + v.age + ' bar' + (v.age === 1 ? '' : 's') + ' ago',
            v.dir > 0 ? up : down]);
        });

      const yLev = column(552, 'Key levels', [
        ['Support', ind.support != null ? fmt(ind.support) : null, up],
        ['Resistance', ind.resistance != null ? fmt(ind.resistance) : null, down],
        ['RSI (14)', ind.rsi != null ? fmt(ind.rsi) : null, text],
        ['ATR (14)', ind.atr != null ? fmt(ind.atr) : null, text],
      ].concat(smcRows));
      // Rule above the footer, under whichever column ran longer.
      const yRule = Math.max(yEnd, yLev) - 18;
      g.strokeStyle = border;
      g.beginPath();
      g.moveTo(48, yRule);
      g.lineTo(W - 48, yRule);
      g.stroke();

      g.fillStyle = read('--muted', '#8A94A8');
      g.font = '400 13px system-ui, sans-serif';
      g.fillText((window.SMA.siteName || ''),
                 48, H - 34);
      g.fillText(new Date().toLocaleString(), 48, H - 14);

      cv.toBlob(function (blob) {
        if (!blob) return;
        const file = new File([blob], j.symbol + '-' + j.tf + '.png', { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          navigator.share({ files: [file], title: j.symbol + ' ' + j.signal }).catch(function () {});
          return;
        }
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = file.name;
        a.click();
        setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
      }, 'image/png');
    });
  })();

  // ------------------------------------------------ ask about this setup
  // The answer is grounded in the stored analysis for this exact pair and
  // timeframe, so the model cannot describe a different chart.
  (function () {
    const form = document.getElementById('aiAskForm');
    if (!form) return;
    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const input = document.getElementById('aiQuestion');
      const out = document.getElementById('aiAnswer');
      const q = (input.value || '').trim();
      if (!q || !out) return;
      out.hidden = false;
      out.textContent = 'Thinking\u2026';
      try {
        const r = await fetch('api.php?action=ask', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ symbol: state.symbol, tf: state.tf, question: q })
        });
        const j = await r.json();
        out.textContent = j.ok ? j.answer : (j.error || 'No answer available.');
        if (j.ok) input.value = '';
      } catch (err) {
        out.textContent = 'Could not reach the explanation service.';
      }
    });
  })();

  // ------------------------------------------------ sidebar sections
  // Which sections the reader left open, remembered per browser. Reopening
  // the chart to a panel you had collapsed is its own small annoyance.
  (function () {
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem('sma_secs') || '{}') || {}; } catch (e) {}
    document.querySelectorAll('details.sec[data-sec]').forEach(function (d) {
      const k = d.getAttribute('data-sec');
      if (Object.prototype.hasOwnProperty.call(saved, k)) d.open = !!saved[k];
      d.addEventListener('toggle', function () {
        saved[k] = d.open;
        try { localStorage.setItem('sma_secs', JSON.stringify(saved)); } catch (e) {}
      });
    });
  })();

  // ------------------------------------------------ funds balance
  // Starting funds move with realised results, and margin tied up in open
  // positions is not available to commit again - so "available" is the number
  // that decides whether the next trade can be opened at all.

  // ONE LIVE PROFIT NUMBER, CAPPED THE WAY THE SERVER CAPS IT.
  //
  // Two places on this page mark an open trade to the live price - the single
  // position readout below and the positions panel - and they computed it
  // twice. Worse, the older one omitted the loss cap that Paper::bookedPnl
  // applies when the trade settles: on an isolated position the committed
  // margin is the most that can be lost, so an uncapped figure tells a member
  // they are down more money than they can lose, and then the portfolio books
  // something different. Whatever this returns must be what the server would
  // book if the trade closed at this price.
  function tradePnl(trade, price) {
    if (!trade) { return null; }
    const entry = parseFloat(trade.entry);
    const units = parseFloat(trade.units) || 0;
    const p = typeof price === 'number' && isFinite(price) ? price : null;
    if (p === null || !isFinite(entry) || !units) { return null; }
    const dir = String(trade.side || '').toUpperCase() === 'SELL' ? -1 : 1;
    const raw = dir * (p - entry) * units;
    const margin = parseFloat(trade.margin) || 0;
    return margin > 0 ? Math.max(raw, -margin) : raw;
  }

  // ------------------------------------------------ live position P/L
  // An open paper position marked to the live price, in cash. Previously a
  // member could only learn what a trade did once it had already settled.
  (function () {
    const box = document.getElementById('livePos');
    if (!box) return;
    const el = id => document.getElementById(id);
    let trade = null;                 // open trade on the pair currently shown

    const money = v => (v < 0 ? '−$' : '+$') +
      Math.abs(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    livePosRender = function (price) {
      if (!trade) { box.hidden = true; return; }
      const p = typeof price === 'number' && isFinite(price)
        ? price
        : (state.candles.length ? state.candles[state.candles.length - 1].c : null);
      if (p === null) { box.hidden = true; return; }
      const units = parseFloat(trade.units) || 0;
      const entry = parseFloat(trade.entry) || 0;
      const pnl = tradePnl(trade, p);
      if (pnl === null) { box.hidden = true; return; }
      const notional = entry * units;
      const pct = notional > 0 ? (pnl / notional) * 100 : 0;

      el('livePosSide').textContent = trade.side === 'SELL' ? 'SHORT' : 'LONG';
      el('livePosSide').className = 'live-pos-side ' + (trade.side === 'SELL' ? 'neg' : 'pos');
      const pnlEl = el('livePosPnl');
      pnlEl.textContent = money(pnl) + '  (' + (pct >= 0 ? '+' : '') + pct.toFixed(2) + '%)';
      pnlEl.className = 'live-pos-pnl ' + (pnl >= 0 ? 'pos' : 'neg');
      el('livePosEntry').textContent = fmt(entry);
      el('livePosNow').textContent = fmt(p);
      el('livePosSize').textContent =
        units.toLocaleString(undefined, { maximumFractionDigits: 6 }) + ' ' + baseAsset(trade.symbol) +
        ' · $' + notional.toLocaleString(undefined, { maximumFractionDigits: 2 });
      box.hidden = false;
    };

    livePosLoad = async function () {
      if (!window.SMA.isMember) { trade = null; livePosRender(); return; }
      try {
        const r = await fetch('api.php?action=paper');
        const j = await r.json();
        state.funds = j.funds || null;
        trade = (j.ok && Array.isArray(j.open))
          ? j.open.find(t => t.symbol === state.symbol && t.tf === state.tf) || null
          : null;
      } catch (e) { trade = null; }
      // The paper-trade button reads this, so an already-open pair shows as
      // such immediately instead of only after a click comes back refused.
      state.openTrade = trade;
      livePosRender();
      followReset();
    };
  })();

  // ------------------------------------------------ paper-trade this signal
  // Deliberately two steps. The previous version fired on a single click with
  // nothing on screen about what it was about to open, and reported "no open
  // trade available" both when the signal was NEUTRAL and when you already
  // held that pair - so clicking twice looked like a broken button.
  (function () {
    const btn = document.getElementById('followBtn');
    const box = document.getElementById('followConfirm');
    const facts = document.getElementById('followFacts');
    const go = document.getElementById('followGo');
    const cancel = document.getElementById('followCancel');
    const note = document.getElementById('followNote');
    if (!btn || !box) return;

    const IDLE = '+ Paper-trade this signal';
    let opened = null;         // 'SYMBOL:tf' already opened this session

    function close() { box.hidden = true; btn.hidden = false; }

    // TWO DIFFERENT EVENTS, AND THEY WERE ONE FUNCTION.
    //
    // followReset() closes the ticket, which is right when the SETUP changes -
    // a new coin or timeframe makes whatever is half-filled in meaningless.
    // It is wrong when only the PLAN changed: the member types their stop and
    // target into the ticket and presses "Use the levels below", and the panel
    // they are filling in slams shut in front of them. Caught by the
    // end-to-end test, which could not reach the size box afterwards.
    //
    // followSync() is the half that recomputes the button and re-prices, and
    // it is what the plan calls. followReset() is that plus the close.
    followSync = function () {
      btn.disabled = false;
      btn.textContent = IDLE;
      if (note) note.textContent = '';
      const L = state.levels;
      const key = state.symbol + ':' + state.tf;
      if (state.openTrade) {
        btn.disabled = true;
        btn.textContent = '✓ Already in your portfolio';
        if (note) note.innerHTML = 'Live profit and loss is shown above. ' +
          'Close it on your <a href="portfolio.php">portfolio</a> to open another.';
      } else if (opened === key) {
        btn.disabled = true;
        btn.textContent = '✓ Already in your portfolio';
        if (note) note.innerHTML = 'Open on your <a href="portfolio.php">portfolio</a>.';
      } else if (!ticketPlan()) {
        btn.disabled = true;
        btn.textContent = 'No setup to paper-trade';
        // What to do instead, rather than only what cannot be done. The
        // sentence used to end "switch to Manual to trade your own plan" -
        // there is no Manual now, and a dead instruction is worse than none.
        if (note) note.textContent = 'There is no setup on this coin and timeframe right now, '
          + 'so there is no entry or stop to follow. Try another timeframe, or open the '
          + 'scanner for the coins that do have one.';
      }
      // The numbers in an open ticket describe the plan, so they follow it -
      // including which target closes it. The ladder was clamped once, when
      // target: it stayed on "Target 3 (0)", an exit at a price that does not
      // exist, and the order button stayed disabled with nothing saying why.
      if (!box.hidden) {
        const tp = ticketPlan();
        if (tp) {
          exitTarget = exitClamp(tp);
          Array.prototype.forEach.call(document.getElementById('ticketExit').children,
            function (c) { c.classList.toggle('on', c.dataset.exitTarget === String(exitTarget)); });
        }
        priceTicket();
      }
    };
    followReset = function () {
      close();
      followSync();
    };

    const marginIn = document.getElementById('ticketMargin');
    const levSel = document.getElementById('ticketLev');
    const availEl = document.getElementById('ticketAvail');
    const errEl = document.getElementById('ticketErr');
    const m2 = v => v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // Everything downstream of the two inputs. Recomputed on every keystroke
    // so the quantity, the loss and the target are answers to what is on
    // screen, not to a setting saved somewhere else earlier.
    let entryMode = 'signal';
    // Which target closes the position: 3, 2 or 1 for a full exit there,
    // 0 for the site's scale-out plan, -1 for no target at all. The default
    // is the furthest published target - see Paper::EXIT_DEFAULT.
    // WHAT THE MEMBER ASKED FOR, AND WHAT IS ACTUALLY ON THE LADDER.
    //
    // These were one variable, and the clamp only ever counted DOWN. That was
    // harmless while the ladder was re-checked once per ticket, and wrong the
    // moment it re-checks as the member types: filling the stop in first left
    // targets 2 and 3 empty for that instant, the clamp walked the choice
    // down to Target 1, and typing target 2 and 3 afterwards could not put it
    // back. The ticket then closed at Target 1 on a three-target plan without
    // ever saying so.
    //
    // exitWanted is the member's choice and only they change it. exitTarget is
    // that choice with the rungs that do not exist yet taken off, recomputed
    // from scratch every time, so it recovers as the plan fills in.
    let exitWanted = 3;
    let exitTarget = 3;

    /** exitWanted, stepped down only as far as the targets that exist. */
    function exitClamp(tp) {
      if (exitWanted <= 0) {
        return exitWanted;          // 0 = the site's scale-out, -1 = no target
      }
      let n = exitWanted;
      while (n > 1 && !(tp && parseFloat(tp['tp' + n]) > 0)) { n--; }
      return n;
    }

    // The live price the ticket would fill at. lastTick is what the price
    // poller last saw; the forming candle's close is the fallback before the
    // first tick arrives.
    function marketPrice() {
      if (typeof lastTick === 'number' && isFinite(lastTick)) return lastTick;
      return state.candles.length ? state.candles[state.candles.length - 1].c : 0;
    }

    function priceTicket() {
      const L = ticketPlan();
      if (!L) {
        // typed into it. Everything downstream needs a stop, so the summary is
        // emptied and the order button held until there is one - rather than
        // returning and leaving whatever the last coin's numbers were on
        // screen next to a live Open button.
        if (facts) { facts.innerHTML = ''; }
        if (errEl) {
          errEl.textContent = '';
          errEl.hidden = !errEl.textContent;
        }
        go.disabled = true;
        return;
      }
      const avail = state.funds ? state.funds.available : 0;
      const margin = parseFloat(marginIn.value) || 0;
      const lev = parseFloat(levSel.value) || 1;
      const sigPx = parseFloat(L.entry) || 0;
      const mktPx = marketPrice();
      document.getElementById('ticketSigPx').textContent = fmt(sigPx);
      document.getElementById('ticketMktPx').textContent = mktPx ? fmt(mktPx) : '—';
      // pending order, which this is not - so the ticket previews market too.
      // Showing the planned entry here and booking a different one is the one
      // way this panel could lie about the trade it is opening.
      const entry = (L.mine || entryMode === 'market') && mktPx > 0 ? mktPx : sigPx;
      const notional = margin * lev;
      const units = entry > 0 ? notional / entry : 0;
      const perUnit = Math.abs(entry - (parseFloat(L.stop_loss) || 0));
      const loss = units * perUnit;
      // The isolated-margin cap, the same one Paper::bookedPnl applies when
      // the trade settles. Computed beside the raw figure rather than in place
      // of it, because whether the two differ is itself the warning.
      const lossCap = margin > 0 ? Math.min(loss, margin) : loss;
      const liquidatesFirst = margin > 0 && loss > margin + 0.005;
      const tp2 = parseFloat(L.tp2) || 0;
      const win = tp2 > 0 ? units * Math.abs(tp2 - entry) : null;
      // member's, and it can be the opposite of whatever the engine thinks.
      const dir = L.side === 'SELL' ? 'Short' : 'Long';

      // Stop and targets are structural, so entering later moves the ratio
      // rather than the levels. Recomputed here instead of quoting the
      // signal's published R:R, which only describes its own entry.
      // The targets on the exit buttons, so the choice is made against
      // prices rather than against three unlabelled numbers.
      ['1', '2', '3'].forEach(function (n) {
        const el = document.getElementById('ticketTp' + n);
        const v = parseFloat(L['tp' + n]) || 0;
        if (el) el.textContent = v > 0 ? fmt(v) : '—';
        const btn = document.querySelector('#ticketExit [data-exit-target="' + n + '"]');
        if (btn) btn.disabled = !(v > 0);
      });
      // The reward leg follows the exit actually chosen: quoting the ratio to
      // target 2 while the position is set to leave at target 1 describes a
      // trade nobody is taking.
      const exitPx = exitTarget > 0 ? (parseFloat(L['tp' + exitTarget]) || 0)
                   : (exitTarget === 0 ? tp2 : 0);
      const rr = perUnit > 0 && exitPx > 0 ? (Math.abs(exitPx - entry) / perUnit) : null;
      const rows = [
        ['Pair', state.symbol + ' · ' + state.tf],
        ['Plan', L.mine ? 'Yours' : 'The signal\u2019s'],
        ['Direction', dir],
        ['Entry', fmt(entry) + (L.mine || entryMode === 'market' ? ' (market)' : '')],
        ['Stop loss', fmt(L.stop_loss)],
        ['Target 1', fmt(L.tp1)],
        ['Risk : Reward', rr ? '1 : ' + rr.toFixed(2) : '—'],
        ['Size', units > 0 ? units.toLocaleString(undefined, { maximumFractionDigits: 6 }) + ' ' + baseAsset(state.symbol) : '—'],
        ['Position value', notional > 0 ? '$' + m2(notional) : '—'],
        // WHAT CAN ACTUALLY BE LOST, WHICH IS NEVER MORE THAN THE MARGIN.
        //
        // Reported from a live ticket: $1,067.87 committed at 100x showed
        // "Loss if stopped -$15,886.81 (371.93% of available)" on a $4,271
        // wallet. None of that can happen. The position is isolated, so
        // Paper::bookedPnl caps the loss at the margin - the trade liquidates
        // long before a stop that far away is reached - and the ticket was
        // quoting a number fourteen times the real worst case, as a share of a
        // wallet it cannot touch. Third place today the same missing cap has
        // turned up, so it is said plainly: the screen must never claim a loss
        // the server would not book.
        //
        // When the stop sits beyond the liquidation point the row says so,
        // because "you will be liquidated first" is the thing the reader needs
        // to know and a capped number alone would hide it.
        ['Loss if stopped', [lossCap > 0
          ? '−$' + m2(lossCap)
            + (avail > 0 ? ' (' + (lossCap / avail * 100).toFixed(2) + '% of available)' : '')
            + (liquidatesFirst ? ' — liquidated before your stop' : '')
          : '—', 'neg']],
        ['Profit at TP 2', [win ? '+$' + m2(win) : '—', 'pos']],
        ['Closes at', exitTarget > 0
          ? 'Target ' + exitTarget + ' (' + fmt(parseFloat(L['tp' + exitTarget]) || 0) + '), the stop, or the time stop'
          : (exitTarget === 0
              ? 'Part at TP 1, rest at TP 2 — or the stop, or the time stop'
              : 'No target — only the stop or the time stop')],
      ];
      facts.innerHTML = rows.map(function (r) {
        const v = Array.isArray(r[1]) ? r[1][0] : r[1];
        const cls = Array.isArray(r[1]) ? ' class="' + r[1][1] + '"' : '';
        return '<div><dt>' + r[0] + '</dt><dd' + cls + '>' + v + '</dd></div>';
      }).join('');

      // Entering the wrong side of the stop is an instant loss, not a trade.
      const stop = parseFloat(L.stop_loss) || 0;
      const past = dir === 'Long' ? entry <= stop : entry >= stop;
      let err = '';
      if (past) err = 'The market has passed this setup’s stop — nothing left to enter.';
      else if (margin > 0 && margin > avail + 1e-9) err = 'More than your available $' + m2(avail) + '.';
      errEl.textContent = err;
      errEl.hidden = !err;
      go.disabled = margin <= 0 || !!err;
    }

    marginIn.addEventListener('input', function () {
      Array.prototype.forEach.call(document.getElementById('ticketPcts').children,
        function (c) { c.classList.remove('on'); });
      // The top-up answers a shortfall, so it goes as soon as there is not one:
      // lower the amount to something the balance covers and the deposit box
      // disappears without having to be dismissed.
      //
      // Here rather than inside priceTicket(), which returns early when there
      // is no published setup - that early return is right for pricing and
      // wrong for this, and putting the check behind it meant the box stayed
      // up after the amount had already been fixed.
      syncTopUp();
      priceTicket();
    });
    levSel.addEventListener('change', priceTicket);
    document.getElementById('ticketExit').addEventListener('click', function (e) {
      const b = e.target.closest('button[data-exit-target]');
      if (!b || b.disabled) return;
      exitWanted = parseInt(b.dataset.exitTarget, 10);
      if (isNaN(exitWanted)) exitWanted = 3;
      exitTarget = exitClamp(ticketPlan());
      Array.prototype.forEach.call(this.children, function (c) { c.classList.toggle('on', c === b); });
      priceTicket();
    });
    document.getElementById('ticketEntry').addEventListener('click', function (e) {
      const b = e.target.closest('button[data-entry]');
      if (!b) return;
      entryMode = b.dataset.entry;
      Array.prototype.forEach.call(this.children, function (c) { c.classList.toggle('on', c === b); });
      priceTicket();
    });
    document.getElementById('ticketPcts').addEventListener('click', function (e) {
      const p = e.target.closest('button[data-pct]');
      if (!p) return;
      const avail = state.funds ? state.funds.available : 0;
      marginIn.value = (avail * (parseFloat(p.dataset.pct) / 100)).toFixed(2);
      Array.prototype.forEach.call(this.children, function (c) { c.classList.toggle('on', c === p); });
      syncTopUp();     // a percentage of the balance is always affordable
      priceTicket();
    });

    btn.addEventListener('click', function () {
      // THE LAST PLACE THE SIGNAL WAS REQUIRED.
      //
      // `if (!state.levels) return` - the engine's levels - so on a coin with
      // form the member needs is INSIDE the panel it refuses to open. The
      // plan is the reason to open the panel rather than a reason not to.
      if (!ticketPlan()) { return; }
      const avail = state.funds ? state.funds.available : 0;
      availEl.textContent = '$' + m2(avail);
      marginIn.value = '';
      errEl.hidden = true;
      // A fresh ticket starts with no shortfall, so it starts with no deposit
      // box - otherwise one left over from the last attempt greets the next
      // trade as though this one were already short of funds.
      hideTopUp();
      entryMode = 'signal';
      exitWanted = 3;
      exitTarget = 3;
      Array.prototype.forEach.call(document.getElementById('ticketEntry').children,
        function (c) { c.classList.toggle('on', c.dataset.entry === 'signal'); });
      // Fall back down the ladder when fewer targets were published - or, in
      // button is never a disabled one.
      exitTarget = exitClamp(ticketPlan());
      Array.prototype.forEach.call(document.getElementById('ticketExit').children,
        function (c) { c.classList.toggle('on', c.dataset.exitTarget === String(exitTarget)); });
      Array.prototype.forEach.call(document.getElementById('ticketPcts').children,
        function (c) { c.classList.remove('on'); });
      priceTicket();
      btn.hidden = true;
      box.hidden = false;
      marginIn.focus();
    });

    cancel.addEventListener('click', close);

    // Running out of balance is not a failure, it is a missing step.
    //
    // It used to close the ticket and say "add funds in your portfolio", which
    // means leaving the chart, depositing, coming back, finding the coin and
    // reopening the ticket - and a setup that was valid when you started
    // usually is not by the time you get back. The ticket stays open now and
    // asks for the amount it is short of, right there.
    const topBox = document.getElementById('ticketTopUp');
    const topAmt = document.getElementById('ticketTopAmt');
    const topGo = document.getElementById('ticketTopGo');
    const topPresets = document.getElementById('ticketTopPresets');

    // While a shortfall is on screen, "Open paper trade" is a button whose only
    // possible outcome is the same error again. It is disabled rather than
    // hidden, so the ticket does not jump about, and Cancel stays reachable.
    function showTopUp(on) {
      if (topBox) { topBox.hidden = !on; }
      go.disabled = on;
      go.title = on ? 'Add the funds below, or lower the amount to commit' : '';
    }
    function hideTopUp() { showTopUp(false); }

    // Take the box away the moment the amount is one the balance covers.
    // Only ever hides: whether there IS a shortfall is the server's answer,
    // not something to guess from a stale balance, so this never shows it.
    function syncTopUp() {
      if (!topBox || topBox.hidden) { return; }
      const avail = state.funds ? parseFloat(state.funds.available) || 0 : 0;
      const margin = parseFloat(marginIn.value) || 0;
      if (margin > 0 && margin <= avail) {
        hideTopUp();
        errEl.hidden = true;
      }
    }

    /* ONE BUTTON. THE MODE ALREADY ANSWERED THE QUESTION.
     *
     * There were two ways to open a position - the ticket's own button for the
     * engine's plan, and a second button inside an "or trade my own levels"
     * switch above the chart had already asked, asked again, in a different
     * place, with a different answer possible. Somebody could be in Manual and
     * press the button that opens the ENGINE's trade.
     *
     * Opens the signal's plan: the entry the member chose, their amount and
     * their leverage, closing at the target rung they picked. */
    async function doOpen() {
      // The same plan the summary above was drawn from, so the trade that
      // opens is the trade the member was shown.
      const body = {
        op: 'open', symbol: state.symbol, tf: state.tf,
        margin: parseFloat(marginIn.value) || 0,
        leverage: parseFloat(levSel.value) || 1,
        entry_mode: entryMode,
        // THE RUNG THE MEMBER PICKED, WHICH USED TO BE THROWN AWAY.
        //
        // The summary said "Closes at Target 1" and the body did not carry
        // exit_target at all, so the server fell back to its default of 3 and
        // the position ran past the target the member had chosen to leave at.
        exit_target: exitTarget
      };
      const r = await fetch('api.php?action=paper', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      return r.json();
    }

    function onOpened(j) {
      opened = state.symbol + ':' + state.tf;
      close();
      livePosLoad();   // start marking it to the live price straight away
      btn.disabled = true;
      btn.textContent = '✓ Added to your portfolio';
      if (note) note.innerHTML = 'Track it on your <a href="portfolio.php">portfolio</a>. ' +
        'It settles itself when the stop, a target or the time stop is reached.';
    }

    // Everything except being short of funds ends the attempt as before.
    function onFailed(j) {
      if (j.reason === 'insufficient_funds') {
        // Keep the ticket up and pre-fill the shortfall, rounded up to a
        // round number so the member is not asked to type $37.42.
        const want = parseFloat(marginIn.value) || 0;
        const have = (j.funds && parseFloat(j.funds.available)) || 0;
        const short = Math.max(0, want - have);
        if (topBox) {
          topAmt.value = short > 0 ? String(Math.ceil(short / 10) * 10) : '';
          showTopUp(true);
          topAmt.focus();
        }
        errEl.textContent = 'Short by $' + m2(short) + '. Add funds below, or commit less.';
        errEl.hidden = false;
        return;
      }
      close();
      if (j.reason === 'duplicate') {
        opened = state.symbol + ':' + state.tf;
        btn.disabled = true;
        btn.textContent = '✓ Already in your portfolio';
      }
      if (note) note.textContent = j.error || 'Could not open this position.';
    }

    go.addEventListener('click', async function () {
      go.disabled = true;
      const label = go.textContent;
      go.dataset.busy = '1';
      go.textContent = 'Opening…';
      hideTopUp();
      try {
        const j = await doOpen();
        if (j.ok) { onOpened(j); } else { onFailed(j); }
      } catch (e) {
        if (note) note.textContent = 'Network error — try again.';
      } finally {
        // Not unconditionally: a shortfall has just disabled this button on
        // purpose, and re-enabling it here would put back the one press whose
        // only possible outcome is the same error.
        go.disabled = !!(topBox && !topBox.hidden);
        delete go.dataset.busy;
        go.textContent = label;
      }
    });

    if (topPresets) {
      topPresets.addEventListener('click', function (e) {
        const b = e.target.closest('button[data-top]');
        if (!b) return;
        topAmt.value = b.dataset.top;
        topAmt.focus();
      });
    }

    if (topGo) {
      // Deposit, then immediately try the trade again - one press, because
      // "add funds" and "open the trade" were only ever one intention.
      topGo.addEventListener('click', async function () {
        const amt = parseFloat(topAmt.value) || 0;
        if (amt <= 0) { topAmt.focus(); return; }
        topGo.disabled = true;
        const label = topGo.textContent;
        topGo.textContent = 'Adding…';
        try {
          const dr = await fetch('api.php?action=paper', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ op: 'deposit', amount: amt })
          });
          const dj = await dr.json();
          if (!dj.ok) {
            errEl.textContent = dj.error || 'Could not add funds.';
            errEl.hidden = false;
            return;
          }
          // The wallet moved, so the ticket's own numbers have to move with it
          // before anything is opened against them. priceTicket() redraws the
          // available line and the percentage buttons from state.funds, so
          // setting that and re-pricing is the whole update.
          state.funds = dj.funds || state.funds;
          priceTicket();
          hideTopUp();
          errEl.hidden = true;
          const j = await doOpen();
          if (j.ok) { onOpened(j); } else { onFailed(j); }
        } catch (e) {
          errEl.textContent = 'Network error — try again.';
          errEl.hidden = false;
        } finally {
          topGo.disabled = false;
          topGo.textContent = label;
        }
      });
    }
  })();

  // ------------------------------------------------ keyboard shortcuts
  // A chart people use for hours should be drivable without the mouse.
  document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    // 1-9 still jump straight to a timeframe now that they are a dropdown -
    // the shortcut is the reason a fast reader tolerates one at all.
    const tfSel = document.getElementById('tfSelect');
    if (/^[1-9]$/.test(e.key) && tfSel && tfSel.options[+e.key - 1]) {
      tfSel.selectedIndex = +e.key - 1;
      tfSel.dispatchEvent(new Event('change'));
      e.preventDefault();
    } else if (e.key === '/') {
      const s = document.getElementById('symbolSearch');
      if (s) { s.focus(); e.preventDefault(); }
    } else if (e.key === 'r') {
      refreshAll();
    } else if (e.key === 'f') {
      const f = document.getElementById('favBtn');
      if (f) f.click();
    }
  });

  // ------------------------------------------------ scroll reveals
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => io.observe(el));
  } else {
    document.querySelectorAll('.reveal').forEach(el => el.classList.add('in'));
  }


  /* ==========================================================================
   * "TRADE MY OWN LEVELS"
   *
   * The ticket above follows the site's plan. This one opens the same kind of
   * paper position from a stop and targets the member decided for themselves,
   * which is the thing a chart is read FOR - and the thing the portfolio could
   * not record, so it was a scoreboard for the engine rather than for the
   * person using it.
   *
   * The levels are theirs. The entry is not: it is fetched by the server at
   * the moment of opening, because a portfolio whose fills were typed in is a
   * portfolio of trades that never happened. Everything else - side, stop,
   * three targets, size, leverage - comes from here, and is checked again on
   * the server, since a rule enforced in a form is not a rule.
   * ====================================================================== */
  (function myTradeTicket() {
    const box = document.getElementById('myTrade');
    if (!box) return;
    const sideBox = document.getElementById('mtSide');
    const stopIn = document.getElementById('mtStop');
    const tp1In = document.getElementById('mtTp1');
    const tp2In = document.getElementById('mtTp2');
    const tp3In = document.getElementById('mtTp3');
    const rrEl = document.getElementById('mtRr');
    const errEl = document.getElementById('mtErr');
    const fromLines = document.getElementById('mtFromLines');
    const fillNote = document.getElementById('mtFill');
    let side = 'BUY';

    function marketPx() {
      const cs = state.candles;
      return cs.length ? parseFloat(cs[cs.length - 1].c) : 0;
    }

    function setSide(s) {
      side = s;
      sideBox.querySelectorAll('[data-side]').forEach(function (b) {
        b.classList.toggle('on', b.dataset.side === s);
      });
      showRr();
    }
    sideBox.querySelectorAll('[data-side]').forEach(function (b) {
      b.addEventListener('click', function () { setSide(b.dataset.side); });
    });

    /* Risk and reward, said before the trade rather than after it. The number
     * that matters is not the target, it is the distance to the stop against
     * the distance to the target - and a ticket that does not show it lets
     * somebody take a 0.4R setup without ever seeing that it was one. */
    function showRr() {
      const px = marketPx();
      const stop = parseFloat(stopIn.value);
      const tp = parseFloat(tp1In.value);
      if (!px || !isFinite(stop) || !isFinite(tp) || stop <= 0 || tp <= 0) { rrEl.textContent = ''; return; }
      const risk = Math.abs(px - stop);
      const reward = Math.abs(tp - px);
      if (!risk) { rrEl.textContent = ''; return; }
      const rr = reward / risk;
      const wrongStop = side === 'BUY' ? stop >= px : stop <= px;
      const wrongTp = side === 'BUY' ? tp <= px : tp >= px;
      if (wrongStop || wrongTp) {
        rrEl.className = 'mt-rr bad';
        rrEl.textContent = wrongStop
          ? (side === 'BUY' ? 'The stop is above the price - on a long it goes below.'
                            : 'The stop is below the price - on a short it goes above.')
          : (side === 'BUY' ? 'Target 1 is below the price - on a long it goes above.'
                            : 'Target 1 is above the price - on a short it goes below.');
        return;
      }
      rrEl.className = 'mt-rr' + (rr < 1 ? ' warn' : '');
      rrEl.textContent = 'Risk ' + fmt(risk) + '  ·  reward to target 1 ' + fmt(reward)
        + '  ·  ' + rr.toFixed(2) + ' : 1'
        + (rr < 1 ? '  — you are risking more than you stand to make on the first target.' : '');
    }
    [stopIn, tp1In, tp2In, tp3In].forEach(function (el) {
      el.addEventListener('input', showRr);
    });

    /* The lines already on the chart, turned into a plan.
     *
     * Somebody who has just drawn their level should not then type it into a
     * box. Horizontal lines below the price become the stop (nearest first),
     * the ones above become the targets in order - and reversed for a short.
     * It fills the fields rather than opening anything, so the guess is always
     * inspected before it is acted on. */
    /* Fill the ticket from lines the member has NAMED.
     *
     * This used to guess: it took every horizontal line, sorted the ones above
     * and below the price, and assumed the nearest below was the stop on a
     * long. That is right until somebody draws a level they are watching but
     * not trading, and it can never know that the line at 63,200 is the stop
     * rather than the first target. Now the chart carries the answer, because
     * the member said so.
     *
     * Exposed so drawSetRole can call it: labelling a line should update the
     * boxes immediately rather than after pressing something else. */
    mtSyncFromLines = function () {
      var named = {};
      drawings.forEach(function (d) {
        if (d.type === 'hline' && d.role && isFinite(d.pts[0].p) && d.pts[0].p > 0) {
          named[d.role] = d.pts[0].p;
        }
      });
      var r = function (v) { return v === undefined ? '' : Number(v.toFixed(digitsFor(v))); };
      var touched = false;
      [['stop', stopIn], ['tp1', tp1In], ['tp2', tp2In], ['tp3', tp3In]].forEach(function (pair) {
        if (named[pair[0]] !== undefined) { pair[1].value = r(named[pair[0]]); touched = true; }
      });
      if (touched) { showRr(); }
      return named;
    };

    fromLines.addEventListener('click', function () {
      const px = marketPx();
      if (!px) return;
      // Named lines win outright. Only when nothing has been labelled does it
      // fall back to the old above/below guess, so a member who has never used
      // the labels is no worse off than before.
      const named = mtSyncFromLines();
      if (Object.keys(named).length) {
        errEl.hidden = true;
        if (named.stop === undefined) {
          errEl.hidden = false;
          errEl.textContent = 'Filled in your targets. Mark one line as the stop loss too - a '
            + 'trade with no stop has no risk to measure the reward against.';
        }
        return;
      }
      const levels = drawings
        .filter(function (d) { return d.type === 'hline'; })
        .map(function (d) { return d.pts[0].p; })
        .filter(function (v) { return isFinite(v) && v > 0; });
      if (!levels.length) {
        errEl.hidden = false;
        errEl.textContent = 'No horizontal lines on this chart yet - draw your levels with the '
          + 'horizontal tool above, then tap a line and say what it is.';
        return;
      }
      errEl.hidden = true;
      const below = levels.filter(function (v) { return v < px; }).sort(function (a, b) { return b - a; });
      const above = levels.filter(function (v) { return v > px; }).sort(function (a, b) { return a - b; });
      const stops = side === 'BUY' ? below : above;
      const tps = side === 'BUY' ? above : below;
      // Rounded to the pair's own precision. A level read back off the chart
      // is a float with fourteen decimals on it, and a stop of
      // "64873.81408589412" in a box is a number nobody typed and nobody can
      // check.
      const r = function (v) { return v === undefined ? '' : Number(v.toFixed(digitsFor(v))); };
      if (stops.length) stopIn.value = r(stops[0]);
      tp1In.value = r(tps[0]);
      tp2In.value = r(tps[1]);
      tp3In.value = r(tps[2]);
      showRr();
      // SAY WHAT WAS MISSING, RATHER THAN FILLING WHAT WAS THERE AND STOPPING.
      //
      // Every line can be on one side of the price - it usually is, if
      // somebody has marked support under a market they are watching - and
      // then this filled the stop, left the targets blank, and let them press
      // open to be told "set at least the first target". The button had done
      // its job and looked like it had failed.
      const missing = [];
      if (!stops.length) {
        missing.push('no line ' + (side === 'BUY' ? 'below' : 'above') + ' the price to use as a stop');
      }
      if (!tps.length) {
        missing.push('no line ' + (side === 'BUY' ? 'above' : 'below') + ' the price to use as a target');
      }
      if (missing.length) {
        errEl.hidden = false;
        errEl.textContent = 'Filled in what I could — ' + missing.join(', ')
          + '. Draw one, or type it in below.';
      }
    });

    box.addEventListener('toggle', function () {
      if (!box.open) return;
      const px = marketPx();
      if (fillNote && px) {
        fillNote.textContent = 'Entry: at market when you press open — ' + fmt(px) + ' right now.';
      }
      showRr();
    });

  })();

  window.addEventListener('resize', resize);
  initPlan();
  resize();
  refreshAll();
  const autoSec = window.SMA.signalAutoSeconds || 0;
  if (autoSec > 0) {
    // Live auto-update of chart + signal; paused while the tab is hidden.
    setInterval(function () {
      if (document.visibilityState === 'visible') refreshAll(true);
    }, autoSec * 1000);
  }
  if (window.SMA.newsEnabled) {
    loadNews();
    // fully automated live refresh; interval is admin-configurable
    setInterval(loadNews, (window.SMA.newsPollSeconds || 90) * 1000);
  }

  // ---- Market context ------------------------------------------------------
  // The reads that describe the market this pair trades in, rather than the
  // pair itself. Every one of them is already an engine input; showing them
  // means a published verdict is not a number out of nowhere.
  //
  // Each cell is filled on its own, so a feed that returns nothing - futures
  // data is geo-blocked in some regions, and spot-only coins have none at all
  // - leaves a dash rather than blanking the panel or reading as a zero.
  async function loadContext() {
    const grid = document.getElementById('ctxGrid');
    if (!grid) return;
    // Same stale-response guard as loadChart()/analyse()/tickPrice(): a
    // symbol/tf switch while this fetch is in flight must not paint one
    // coin's funding/OI/taker/long-short/breadth numbers under another
    // coin's chart, with no symbol label on the panel to expose the mix-up.
    const reqSymbol = state.symbol;
    const reqTf = state.tf;
    let d;
    try {
      const r = await fetch('api.php?action=context&symbol=' + encodeURIComponent(reqSymbol) +
                            '&tf=' + encodeURIComponent(reqTf));
      d = await r.json();
      if (!d || !d.ok) return;
      if (reqSymbol !== state.symbol || reqTf !== state.tf) { return; }   // stale - a newer request already took over
    } catch (e) { return; }

    const set = (id, txt, cls) => {
      const el = document.getElementById(id);
      if (!el) return;
      el.textContent = txt;
      el.className = cls || '';
    };
    const dash = '\u2014';
    const sign = (v, dp) => (v > 0 ? '+' : '') + Number(v).toFixed(dp);

    // Fear & Greed: value, label and needle. The needle is the whole point -
    // 27 means nothing to a reader who does not know the scale runs 0-100.
    const fng = d.fng;
    const needle = document.getElementById('fngNeedle');
    if (typeof fng === 'number') {
      const label = fng <= 20 ? 'Extreme fear' : fng <= 40 ? 'Fear'
                  : fng < 60 ? 'Neutral' : fng < 80 ? 'Greed' : 'Extreme greed';
      set('fngValue', String(fng));
      set('fngLabel', label);
      if (needle) needle.style.left = Math.max(0, Math.min(100, fng)) + '%';
      const t = d.fng_trend;
      set('fngTrend', typeof t === 'number' && t !== 0
        ? sign(t, 0) + ' over three days' + (Math.abs(t) >= 10 ? ' (voting)' : '')
        : '');
      const g = document.getElementById('fngGauge');
      if (g) g.setAttribute('aria-label', 'Fear and Greed index ' + fng + ' - ' + label);
    } else {
      set('fngValue', dash);
      set('fngLabel', 'Fear & Greed unavailable');
      if (needle) needle.style.left = '50%';
    }

    // Funding is quoted per 8h and the interesting part is the sign: positive
    // means longs are paying to stay long.
    set('ctxFunding', typeof d.funding === 'number'
      ? sign(d.funding * 100, 4) + '% / 8h' : dash,
      typeof d.funding === 'number' ? (d.funding > 0 ? 'neg' : 'pos') : '');
    set('ctxLs', typeof d.long_short === 'number'
      ? Number(d.long_short).toFixed(2) + (d.long_short > 1 ? ' long-heavy' : ' short-heavy') : dash);
    set('ctxOi', typeof d.oi_change === 'number' ? sign(d.oi_change, 2) + '%' : dash,
      typeof d.oi_change === 'number' ? (d.oi_change >= 0 ? 'pos' : 'neg') : '');
    set('ctxTaker', typeof d.taker === 'number'
      ? Number(d.taker).toFixed(2) + (d.taker >= 1 ? ' buyers lifting' : ' sellers hitting') : dash,
      typeof d.taker === 'number' ? (d.taker >= 1 ? 'pos' : 'neg') : '');
    // Short on purpose: the label already says "Breadth", and the long form
    // wrapped to two lines on a phone.
    set('ctxBreadth', typeof d.breadth === 'number'
      ? Number(d.breadth).toFixed(0) + '% above 20-bar avg' : dash,
      typeof d.breadth === 'number' ? (d.breadth >= 50 ? 'pos' : 'neg') : '');
  }
  // ---- Multi-timeframe verdict strip ---------------------------------------
  async function loadMtfStrip() {
    const host = document.getElementById('mtfStrip');
    if (!host) return;
    // Same stale-response guard as loadChart()/analyse()/tickPrice(): without
    // it, a symbol switch while this fetch is in flight can render another
    // coin's whole BUY/SELL/NEUTRAL ladder under "Every timeframe at once."
    const reqSymbol = state.symbol;
    let d;
    try {
      const r = await fetch('api.php?action=mtf&symbol=' + encodeURIComponent(reqSymbol));
      d = await r.json();
      if (!d || !d.ok) return;
      if (reqSymbol !== state.symbol) { return; }   // stale - a newer request already took over
    } catch (e) { return; }
    host.textContent = '';
    (d.frames || []).forEach(f => {
      const cell = document.createElement('div');
      const verdict = f.signal || 'none';
      cell.className = 'tfv-cell ' + verdict.toLowerCase() + (f.tf === state.tf ? ' on' : '');
      const tf = document.createElement('b');
      tf.textContent = f.tf;
      const v = document.createElement('span');
      // "No reading yet" is a different statement from "neutral", and the
      // strip has to keep them apart or an unanalysed frame reads as a
      // considered verdict of nothing.
      v.textContent = f.signal || '—';
      cell.appendChild(tf); cell.appendChild(v);
      if (f.age_min !== null && f.age_min !== undefined) {
        const age = document.createElement('small');
        const m = f.age_min;
        age.textContent = m < 60 ? m + 'm ago'
          : m < 1440 ? Math.floor(m / 60) + 'h ago' : Math.floor(m / 1440) + 'd ago';
        cell.appendChild(age);
      }
      host.appendChild(cell);
    });
  }
  window.smaLoadMtfStrip = loadMtfStrip;
  if (document.getElementById('mtfStrip')) loadMtfStrip();

  window.smaLoadContext = loadContext;
  if (document.getElementById('ctxGrid')) {
    loadContext();
    // Slow on purpose: Fear & Greed updates once a day and the derivatives
    // reads are cached server-side for fifteen minutes, so anything faster is
    // requests spent to redraw the same numbers.
    setInterval(function () {
      if (document.visibilityState === 'visible') loadContext();
    }, 300000);
  }
})();

/* ------------------------------------------------ the scanner, over the chart
 *
 * Tap Scanner and the live setups come to you; tap one and it loads into the
 * chart underneath, ready to trade. The board used to be a separate page,
 * which meant losing the chart to read the list and losing the list to read a
 * chart - the two halves of one question, never on screen together.
 *
 * Appended as its own block rather than threaded into the chart module: it
 * needs nothing from that scope except the two <select>s it drives, and it
 * drives them by dispatching the same 'change' event a human click produces,
 * so the coin and timeframe travel through exactly the path they always did
 * rather than a second copy of it that could drift.
 */
(function () {
  var openBtn = document.getElementById('scanOpen');
  var pop = document.getElementById('scanPop');
  if (!openBtn || !pop) { return; }
  var list = document.getElementById('scanPopList');
  var note = document.getElementById('scanPopNote');
  // Scrolls straight to the "Already running" heading - see render() below
  // for when it is shown, and its own comment in charts.php for why it
  // exists at all rather than just leaving the section to be scrolled past.
  var jumpBtn = document.getElementById('scanPopJump');
  var closeBtn = document.getElementById('scanClose');
  // The icon beside the close button that swaps this SAME popup's body over
  // to the by-coin win rate list - see openCoins()/closeCoins() below. Only
  // rendered when perf_coins is unlocked, so it and the title it relabels
  // are nullable.
  var coinsBtn = document.getElementById('scanPopCoinsBtn');
  var titleEl = document.getElementById('scanPopTitle');
  var symbolClearBtn = document.getElementById('scanPopSymbolClear');
  var lastFocus = null;
  var loadedAt = 0;

  // MOVED TO THE BODY, AND IT IS NOT TIDINESS.
  //
  // position:fixed is relative to the viewport only while no ancestor has a
  // transform, filter or perspective - any of those becomes the containing
  // block instead. .chart-panel has a transform, so the sheet rendered inside
  // the chart section at top:-325 with no backdrop: a list sitting in the page
  // flow rather than over it. Measured before this line existed.
  //
  // Reparenting is the fix that does not care which ancestor did it, and it
  // survives somebody adding a transform to a different wrapper later.
  if (pop.parentNode !== document.body) { document.body.appendChild(pop); }

  function esc(v) {
    return String(v === null || v === undefined ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // WHAT THE BOARD CLAIMED, CHECKED AGAINST WHAT THE CHART FINDS.
  //
  // The board reads the last STORED verdict; the chart re-runs the engine on
  // the current candle. They can disagree, and when they do the ticket used to
  // say "NO SETUP" with no explanation - which reads as the site losing a
  // signal it had just advertised. It says what happened instead.
  //
  // Read from the rendered badge rather than re-fetching: the chart has just
  // asked the server the same question, and asking twice would be a second
  // answer that could differ from the one on screen. Retried because the
  // refresh is asynchronous and the badge is not updated the instant the
  // select changes.
  function checkClaim(claim) {
    var box = document.getElementById('staleCall');
    if (!box) { return; }
    var tries = 0;
    (function look() {
      var badge = document.getElementById('signalBadge');
      var live = badge ? (badge.textContent || '').trim().toUpperCase() : '';
      var settled = live === 'BUY' || live === 'SELL' || live.indexOf('NO SETUP') === 0
                 || live === 'NEUTRAL';
      if (!settled && tries++ < 12) { return void setTimeout(look, 500); }
      if (live.indexOf(claim.signal) === 0) { box.hidden = true; return; }
      var when = (claim.bars === 0 || claim.bars)
        ? (claim.bars === 0 ? 'on the current bar' : claim.bars + ' bar'
           + (claim.bars === 1 ? '' : 's') + ' ago')
        : 'earlier';
      box.innerHTML = 'The board\u2019s last call here was <strong>' + esc(claim.signal)
        + '</strong>, read ' + esc(when) + '. The engine reads this candle as <strong>'
        + esc(live || 'no setup') + '</strong> \u2014 that setup has gone, so there is nothing '
        + 'to follow on it now. The board shows the last verdict it stored; the chart re-reads '
        + 'the market as you look at it.';
      box.hidden = false;
    }());
  }

  function pick(sym, tf) {
    var symSel = document.getElementById('symbol');
    var tfSel = document.getElementById('tfSelect');
    if (!symSel) { return; }
    // The coin may not be in the list a free member is shown. Say so rather
    // than silently doing nothing, which reads as a dead row.
    var opt = symSel.querySelector('option[value="' + (window.CSS && CSS.escape ? CSS.escape(sym) : sym) + '"]');
    if (!opt) {
      note.textContent = sym + ' is not on your coin list, so it cannot be opened here.';
      return;
    }
    symSel.value = sym;
    symSel.dispatchEvent(new Event('change'));
    // Cleared on every pick, so a notice about the last coin never lingers
    // over a new one.
    var box = document.getElementById('staleCall');
    if (box) { box.hidden = true; }
    if (claimed) { checkClaim(claimed); }
    if (tfSel && tf) {
      var has = Array.prototype.some.call(tfSel.options, function (o) { return o.value === tf; });
      // The timeframe options are rebuilt for the new coin, so it is set after
      // that change has run rather than before it.
      setTimeout(function () {
        var again = Array.prototype.some.call(tfSel.options, function (o) { return o.value === tf; });
        if (has || again) { tfSel.value = tf; tfSel.dispatchEvent(new Event('change')); }
      }, 0);
    }
    close();
  }

  // Narrowed in the browser against the rows already fetched, so switching
  // between Buy and Sell is instant. GRADE IS A RANK, NOT A STRING: "A and up"
  // has to include A+, and comparing letters would put A+ below A because '+'
  // sorts after nothing.
  var GRADE_RANK = { 'A+': 4, 'A': 3, 'B': 2, 'C': 1, 'D': 0 };
  // Three INDEPENDENT toggles, not one choice of three - see the comment
  // beside the markup in charts.php. Strongest starts on, matching the sort
  // this board always opened with; Latest and Highest win rate stack on top
  // of it (or replace it) rather than swapping it out.
  // Live only - this sheet no longer offers a Closed board, so there is no
  // 'view' flag left to carry.
  //
  // 'symbol' is set by tapping a coin in the "By coin" panel below - it
  // narrows this same list to that one coin rather than jumping straight to
  // the chart, so picking a coin from its win rate answers "what does THIS
  // coin have live right now" instead of leaving the board unfiltered.
  var filters = { dir: '', tf: '', grade: '', rr: '', symbol: '',
                   sortScore: true, sortLatest: false, sortWinrate: false };
  var allRows = [];
  // Setups already touched by a target - not enterable, still running. See
  // the comment on Scanner::$progress (src/Scanner.php) and the 'progress'
  // key api.php's 'board' action sends alongside 'signals'. Kept in a list of
  // its own rather than merged into allRows so it can never end up sorted or
  // filtered into looking like an ordinary row - the section it renders into
  // (see render() below) is the only place these appear.
  var allProgress = [];
  var claimed = null;

  // A short description of which of the three are active, for the note text
  // under the filters - "strongest, then best win rate first" rather than
  // leaving the reader to infer it from which buttons are lit.
  function sortLabel() {
    var parts = [];
    if (filters.sortWinrate) { parts.push('best win rate'); }
    if (filters.sortScore) { parts.push('strongest'); }
    if (filters.sortLatest) { parts.push('newest'); }
    return parts.length ? parts.join(', then ') + ' first' : 'as scanned';
  }

  // Shared by allRows and allProgress, so the two lists can never end up
  // answering "which coin/timeframe/direction was asked for" differently.
  function matchesFilters(r, min) {
    if (filters.dir && r.signal !== filters.dir) { return false; }
    if (filters.tf && r.tf !== filters.tf) { return false; }
    if (min !== null && (GRADE_RANK[r.grade] || 0) < min) { return false; }
    // A row stored before the types existed carries no tier, and an unknown
    // is not a match for a type asked for by name. filters.rr can only ever
    // be set by the select, and the page does not render one once the
    // operator has narrowed the site to some of the three types.
    if (filters.rr && String(r.rr_tier || '') !== filters.rr) { return false; }
    if (filters.symbol && r.symbol !== filters.symbol) { return false; }
    // Highest win rate FILTERS as well as ranks: on means "only coins with
    // a verified record", not "the same rows with the rated ones nudged
    // up." A coin with no rate yet is not a worse win rate, it is an
    // unanswered question, and this toggle is how a reader asks to see
    // only the answered ones.
    if (filters.sortWinrate && r.winRate === undefined) { return false; }
    return true;
  }

  // Same tie-break order every list on this sheet uses - win rate, then
  // strength, then recency - so two lists on screen at once never disagree
  // about which of their own rows comes first.
  function sortRows(rows) {
    return rows.slice().sort(function (a, b) {
      // The filter above already dropped every unrated row when this toggle
      // is on, so both sides always have a real winRate here.
      if (filters.sortWinrate && a.winRate !== b.winRate) { return b.winRate - a.winRate; }
      if (filters.sortScore) {
        var ds = Math.abs(b.score || 0) - Math.abs(a.score || 0);
        if (ds) { return ds; }
      }
      if (filters.sortLatest) { return (b.at || 0) - (a.at || 0); }
      return 0;
    });
  }

  function apply() {
    var min = filters.grade ? (GRADE_RANK[filters.grade] || 0) : null;
    // Sorted on COPIES of the filtered rows. Sorting allRows/allProgress in
    // place would reorder the thing every other view reads from, so
    // switching a toggle back off would not restore the order it started in.
    var rows = sortRows(allRows.filter(function (r) { return matchesFilters(r, min); }));
    var progRows = sortRows(allProgress.filter(function (r) { return matchesFilters(r, min); }));
    syncSymbolClear();
    render(rows, progRows);
  }

  // The coin filter is the one with no visible control of its own (see the
  // comment beside #scanPopSymbolClear in charts.php) - this is that
  // control, kept in step with filters.symbol on every apply() rather than
  // only where it is set, so a reader can never end up filtered to a coin
  // with nothing on screen saying so or offering to undo it.
  function syncSymbolClear() {
    if (!symbolClearBtn) { return; }
    if (filters.symbol) {
      symbolClearBtn.hidden = false;
      symbolClearBtn.textContent = '✕ ' + filters.symbol + ' only — show all coins';
    } else {
      symbolClearBtn.hidden = true;
    }
  }

  /* THE LOCKED PANEL, WHICH IS A SALES MOMENT AND WAS ONE SENTENCE.
   *
   * A free member taps Scanner at the exact moment they want it - which is the
   * best chance the site will get - and was told "The scanner is part of
   * Premium" in grey. That asks somebody to buy a number they cannot see.
   *
   * The count is the honest half of the pitch: there are eleven setups on the
   * board right now, and Premium is what tells you which coins they are on. It
   * comes from the server and is honest in the direction that costs us - on a
   * quiet day it says two.
   *
   * The rows behind it are DELIBERATELY FAKE and deliberately look it: blurred
   * bars, no coin names, no numbers. Blurring real signals would be a paywall
   * you can beat with a screenshot and a filter, and inventing plausible ones
   * would be inventing signals. This is a shape, not data.
   */
  function renderLocked(live) {
    var foot = document.getElementById('scanPopFoot');
    if (foot) { foot.textContent = ''; }
    var f = document.getElementById('scanPopFilters');
    if (f) { f.hidden = true; }
    pop.querySelectorAll('.scan-pop-seg').forEach(function (el) { el.hidden = true; });
    if (coinsBtn) { coinsBtn.hidden = true; }
    if (symbolClearBtn) { symbolClearBtn.hidden = true; }
    note.textContent = '';
    list.innerHTML = '';
    var wrap = document.createElement('li');
    wrap.className = 'sp-lock';
    var ghosts = '';
    for (var i = 0; i < 4; i++) {
      ghosts += '<div class="sp-ghost"><span class="g1"></span><span class="g2"></span>'
             + '<span class="g3"></span></div>';
    }
    wrap.innerHTML =
      '<div class="sp-ghosts" aria-hidden="true">' + ghosts + '</div>'
      + '<div class="sp-lock-body">'
      + '<div class="sp-lock-badge">\u{1F512}</div>'
      + (live > 0
          ? '<h3><strong>' + live + '</strong> live setup' + (live === 1 ? '' : 's')
            + ' on the board right now</h3>'
            + '<p>Premium tells you which coins they are on, on which timeframe, '
            + 'with the entry, stop and targets already worked out.</p>'
          : '<h3>The scanner is part of Premium</h3>'
            + '<p>Every watched coin ranked in one board, with the entry, stop and targets '
            + 'already worked out \u2014 so a setup finds you instead of you opening charts '
            + 'one at a time.</p>')
      + '<ul class="sp-lock-list">'
      + '<li>Every coin ranked, strongest first</li>'
      + '<li>Filter by direction, timeframe and grade</li>'
      + '<li>Tap one to open it on the chart and trade it</li>'
      + '<li>See how the last ones settled, in R</li>'
      + '</ul>'
      + '<a class="btn-primary sp-lock-cta" href="upgrade.php">See the plans</a>'
      + '<p class="sp-lock-note">The chart and its signal stay free \u2014 this is the board '
      + 'that finds them for you.</p>'
      + '</div>';
    list.appendChild(wrap);
  }

  // EVERY TRACKED COIN, RANKED BY ITS OWN VERIFIED RECORD - SWAPPED INTO
  // THE SAME POPUP, NOT A SECOND ONE.
  //
  // This has been its own tab, a panel sitting open above the setups list, a
  // collapsible section inside the same card, and briefly a genuinely
  // separate popup stacked on top of this one - the last of those read as
  // leaving "Live setups" rather than looking something up inside it. Back
  // to one popup: the icon beside its close button swaps this same card's
  // body (and its title) over to the coin list in place, see
  // openCoins()/closeCoins() below. TrackRecord::topCoins(), via the
  // api.php 'board' action's view=bycoin branch - fetched the first time it
  // is opened rather than on every sheet-open, since most visits never ask
  // for it.
  var TOPCOINS_PAGE = 20;
  var topCoinsAll = [];
  var topCoinsShown = 0;
  var topCoinsLoadedAt = 0;
  var topCoinsOpen = false;

  // Tapping a coin here FILTERS THE BOARD BELOW, it does not jump to the
  // chart - "which of these has BTCUSDT got live right now" is the question
  // a win-rate list answers, and the actual setup (if there is one) is still
  // one more tap away on the filtered row itself. Tapping the same coin again
  // clears the filter, the same toggle-on-toggle-off the sort buttons use.
  // Either way it swaps this card's body back to the setups list - that is
  // the whole point of tapping a coin here.
  function coinClick(sym) {
    filters.symbol = (filters.symbol === sym) ? '' : sym;
    closeCoins();
  }

  function listRows(box, rows) {
    box.innerHTML = '';
    if (!rows.length) {
      var empty = document.createElement('p');
      empty.className = 'hint-p';
      empty.style.margin = '4px 0 8px';
      empty.textContent = 'No coin has a verified record yet.';
      box.appendChild(empty);
      return;
    }
    rows.forEach(function (r) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'scan-pop-row';
      b.classList.toggle('on', filters.symbol === r.symbol);
      b.innerHTML = '<span class="sp-sym">' + esc(r.symbol) + '</span>'
        + '<span class="sp-right">'
        + '<span class="sp-winrate">' + esc(r.winRate) + '% win</span>'
        + '<br><span class="sp-age">' + esc(r.settled) + ' trade' + (r.settled === 1 ? '' : 's')
        + (typeof r.sumR === 'number'
            ? ' · ' + (r.sumR > 0 ? '+' : '') + esc(r.sumR) + 'R total' : '')
        + '</span></span>';
      b.addEventListener('click', function () { coinClick(r.symbol); });
      box.appendChild(b);
    });
    if (topCoinsShown < topCoinsAll.length) {
      var loadMore = document.createElement('button');
      loadMore.type = 'button';
      loadMore.className = 'spc-loadmore';
      loadMore.textContent = 'Load more (' + (topCoinsAll.length - topCoinsShown) + ' left)';
      loadMore.addEventListener('click', function () {
        topCoinsShown = Math.min(topCoinsShown + TOPCOINS_PAGE, topCoinsAll.length);
        renderTopCoins();
      });
      box.appendChild(loadMore);
    }
  }

  function renderTopCoins() {
    var box = document.getElementById('scanPopTopCoins');
    if (!box) { return; }
    listRows(box, topCoinsAll.slice(0, topCoinsShown));
  }

  // Swaps this ONE popup's body between the setups list and the coin list -
  // never a second popup, never both at once. The title above changes with
  // it (there is no separate header to say which list this is otherwise),
  // and the icon stays lit (.on, aria-pressed) while the coin list is
  // showing, the same "lit while active" look the sort toggles use.
  function openCoins() {
    var box = document.getElementById('scanPopTopCoins');
    if (!box) { return; }
    topCoinsOpen = true;
    list.hidden = true;
    note.hidden = true;
    var foot = document.getElementById('scanPopFoot');
    if (foot) { foot.hidden = true; }
    box.hidden = false;
    if (titleEl) { titleEl.textContent = 'By coin win rate'; }
    if (coinsBtn) {
      coinsBtn.classList.add('on');
      coinsBtn.setAttribute('aria-pressed', 'true');
    }
    topCoinsShown = Math.min(TOPCOINS_PAGE, topCoinsAll.length || TOPCOINS_PAGE);
    if (Date.now() - topCoinsLoadedAt < 30000 && topCoinsAll.length) {
      renderTopCoins();
      return;
    }
    box.innerHTML = '<p class="hint-p" style="margin:4px 0 8px">Loading&hellip;</p>';
    loadTopCoins();
  }
  function closeCoins() {
    if (!topCoinsOpen) { return; }
    topCoinsOpen = false;
    var box = document.getElementById('scanPopTopCoins');
    if (box) { box.hidden = true; }
    list.hidden = false;
    note.hidden = false;
    var foot = document.getElementById('scanPopFoot');
    if (foot) { foot.hidden = false; }
    if (titleEl) { titleEl.textContent = 'Live setups'; }
    if (coinsBtn) {
      coinsBtn.classList.remove('on');
      coinsBtn.setAttribute('aria-pressed', 'false');
    }
    // Re-applies rather than trusting the list's old content - the reader
    // may just have picked a coin, and its note/count needs to reflect that.
    apply();
  }

  function loadTopCoins() {
    fetch('api.php?action=board&view=bycoin&limit=200', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.ok) { return; }
        topCoinsLoadedAt = Date.now();
        topCoinsAll = j.coins || [];
        topCoinsShown = Math.min(TOPCOINS_PAGE, topCoinsAll.length);
        renderTopCoins();
      })
      .catch(function () { /* the list is a bonus, not the board - fail quiet */ });
  }

  // TP1/TP2/TP3, in the words a member would use for them - same wording the
  // track record's open rows used to carry before that list moved here (see
  // Scanner::touched() and the "ALREADY MOVED IS ALREADY GONE" note beside
  // it in src/Scanner.php for why these rows are not in the setups list
  // below).
  var STAGE_LABEL = { tp1: 'TP1 reached', tp2: 'TP2 reached', tp3: 'TP3 — closing' };

  // Bars-and-wall-clock age, plus time left against the setup's own deadline -
  // shared by the setups list and the "already running" section below it, so
  // the two can never end up formatting the same fields two different ways.
  // A running call still has a deadline (its time stop), same as a fresh one,
  // so this applies to both rather than only to rows still open to enter.
  function rowAge(r) {
    var age = (r.bars_ago === 0 || r.bars_ago) ? (r.bars_ago === 0
      ? 'this bar' : r.bars_ago + ' bar' + (r.bars_ago === 1 ? '' : 's') + ' ago') : '';
    if (r.at) {
      var clock = (window.SMA && window.SMA.timeAgo) ? window.SMA.timeAgo(r.at) : '';
      if (clock) { age = age ? age + ' · ' + clock : clock; }
    }
    var expAt = parseInt(r.expires_at, 10) || 0;
    if (expAt > 0) {
      var leftS = expAt - Math.floor(Date.now() / 1000);
      var span = (window.SMA && window.SMA.spanText)
        ? window.SMA.spanText(leftS) : Math.round(leftS / 60) + 'm';
      age = (age ? age + ' · ' : '')
          + (leftS > 0 ? span + ' left' : 'past its time stop');
    }
    return age;
  }

  // This coin's own verified win rate, not the site-wide one - same premium
  // data as the "By coin" tab. Only present once TrackRecord::winRatesFor
  // found enough settled trades on this symbol for the figure to mean
  // something (see api.php's 'board' action), so an unrated coin shows no
  // badge rather than a 0% that would read as "this coin never wins".
  function winBadgeHtml(r) {
    return (r.winRate !== undefined && r.winRate !== null)
      ? '<span class="sp-winrate" title="' + esc(r.winSettled) + ' verified trade'
        + (r.winSettled === 1 ? '' : 's') + '">' + esc(r.winRate) + '% win</span> · '
      : '';
  }

  // Shows or hides the "N already running ↓" chip above the list. n is 0 when
  // there is nothing to jump to, or nothing above it worth skipping past (see
  // both call sites in render()) - hidden rather than shown-and-disabled, so
  // a reader never taps a chip that does nothing.
  function setJump(n) {
    if (!jumpBtn) { return; }
    jumpBtn.hidden = !n;
    if (n) { jumpBtn.textContent = n + ' already running ↓'; }
  }

  function render(rows, progRows) {
    list.innerHTML = '';
    var narrowed = rows.length !== allRows.length;
    // Declared once, up here, so BOTH branches below set it. It used to sit
    // inside the non-empty branch only, which meant filtering down to zero
    // fresh setups (with some already-running ones still to show) left
    // whatever the footer said last render sitting there uncorrected -
    // stale text is worse than none, because it reads as still true.
    var foot = document.getElementById('scanPopFoot');
    // Said when there is nothing to take right now but something already
    // running is about to render below - without it, the note reads as "the
    // board is empty" while the list underneath is not.
    var progNote = progRows.length
      ? ' ' + progRows.length + ' already running, shown below.' : '';
    if (!rows.length) {
      note.textContent = (allRows.length
        ? 'Nothing matches that filter. ' + allRows.length + ' row'
          + (allRows.length === 1 ? '' : 's') + ' on the board without it.'
        : 'No live BUY or SELL setups right now. The board fills as the engine '
          + 'works through the coin list.') + progNote;
      if (foot) {
        foot.textContent = progRows.length
          ? progRows.length + ' already running, shown above' : '';
      }
      // Nothing above it to skip past - the section is already the first
      // thing in the list, so a jump to it would move nothing.
      setJump(0);
      renderProgress(progRows);
      return;
    }
    var order = sortLabel();
    var what = 'live setup';
    note.textContent = narrowed
      ? 'Showing ' + rows.length + ' of ' + allRows.length + ', ' + order
        + '. Tap one to open it on the chart.'
      : allRows.length + ' ' + what + (allRows.length === 1 ? '' : 's') + ', ' + order
        + '. Tap one to open it on the chart.';
    if (foot) {
      // Said at the bottom as well as the top, because a reader who has
      // scrolled a long list has lost sight of how long it was.
      foot.textContent = 'End of the board \u00b7 ' + rows.length + ' shown'
        + (progRows.length ? ', ' + progRows.length + ' already running' : '');
    }
    setJump(progRows.length);
    rows.forEach(function (r) {
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'scan-pop-row';
      var right;
      {
        var age = rowAge(r);
        // This coin's own verified win rate, not the site-wide one - same
        // premium data as the "By coin" tab. Only present once
        // TrackRecord::winRatesFor found enough settled trades on this
        // symbol for the figure to mean something (see api.php's 'board'
        // action), so an unrated coin shows no badge rather than a 0% that
        // would read as "this coin never wins".
        right = (r.grade ? '<span class="sp-grade">' + esc(r.grade) + '</span> · ' : '')
          + winBadgeHtml(r)
          // The type, beside the grade: the grade says how good the read is,
          // the type says what it pays. Two different questions, and a reader
          // choosing a row wants both.
          + (r.rr_type ? '<span class="rr-type rr' + (r.rr_tier || 1) + '">'
             + esc(r.rr_type) + '</span> · ' : '')
          + 'score ' + esc(r.score)
          + (age ? '<br><span class="sp-age">' + esc(age) + '</span>' : '');
      }
      b.innerHTML = '<span class="sp-sym">' + esc(r.symbol) + '</span>'
        + '<span class="sp-tf">' + esc(r.tf) + '</span>'
        + '<span class="sp-dir ' + (r.signal === 'BUY' ? 'buy' : 'sell') + '">' + esc(r.signal) + '</span>'
        + '<span class="sp-right">' + right + '</span>';
      // The reference goes in the tooltip rather than the row. Every row here
      // is a candidate being skimmed, and nine characters of identifier on
      // each of twenty of them buries the three facts the reader is actually
      // comparing. It is one hover or long-press away, and it is on the chart
      // in full the moment a row is opened.
      if (r.ref) { b.title = 'Signal ID ' + r.ref; }
      b.addEventListener('click', function () {
        claimed = { signal: r.signal, bars: r.bars_ago };
        pick(r.symbol, r.tf);
      });
      li.appendChild(b);
      list.appendChild(li);
    });
    renderProgress(progRows);
  }

  // ALREADY RUNNING - ITS OWN SECTION, NOT MIXED IN WITH THE SETUPS ABOVE.
  //
  // These rows already touched a target (see Scanner::$progress and the
  // "ALREADY MOVED IS ALREADY GONE" note in src/Scanner.php), so the entry
  // above is gone and they are not something to take. A fresh setup and one
  // already most of the way to its target look identical once merged into
  // one list - a heading and a stage badge in place of the grade/score exist
  // so a reader can never mistake one for the other. Appended after
  // render()'s own rows rather than clearing the list itself, so it renders
  // the same way whether the setups list above it was empty or full.
  function renderProgress(progRows) {
    if (!progRows.length) { return; }
    var head = document.createElement('li');
    head.className = 'scan-pop-heading';
    head.textContent = 'Already running (' + progRows.length + ')';
    list.appendChild(head);
    progRows.forEach(function (r) {
      var li = document.createElement('li');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'scan-pop-row';
      var age = rowAge(r);
      // winBadgeHtml() puts its own trailing separator before what follows
      // it (see its use in the setups list above); here the stage comes
      // first, so the badge's own separator is trimmed and re-added in front
      // of it instead.
      var badge = winBadgeHtml(r).replace(/ · $/, '');
      var right = '<span class="sp-stage">' + esc(STAGE_LABEL[r.stage] || 'Running') + '</span>'
        + (badge ? ' · ' + badge : '')
        + (age ? '<br><span class="sp-age">' + esc(age) + '</span>' : '');
      b.innerHTML = '<span class="sp-sym">' + esc(r.symbol) + '</span>'
        + '<span class="sp-tf">' + esc(r.tf) + '</span>'
        + '<span class="sp-dir ' + (r.signal === 'BUY' ? 'buy' : 'sell') + '">' + esc(r.signal) + '</span>'
        + '<span class="sp-right">' + right + '</span>';
      if (r.ref) { b.title = 'Signal ID ' + r.ref; }
      b.addEventListener('click', function () {
        claimed = { signal: r.signal, bars: r.bars_ago };
        pick(r.symbol, r.tf);
      });
      li.appendChild(b);
      list.appendChild(li);
    });
  }

  function load() {
    // Ten seconds of grace. Opening and closing the sheet twice while reading
    // is normal and should not be two round trips.
    if (Date.now() - loadedAt < 10000 && list.children.length) { return; }
    note.textContent = 'Loading…';
    // Undo anything the locked panel hid - a member who upgrades in another
    // tab and comes back should not find the controls missing.
    pop.querySelectorAll('.scan-pop-seg').forEach(function (el) { el.hidden = false; });
    var fbox = document.getElementById('scanPopFilters');
    if (fbox) { fbox.hidden = false; }
    if (coinsBtn) { coinsBtn.hidden = false; }
    // The WHOLE board, not a taste of it. This sheet replaced the scanner page
    // in the menu, so a cap that quietly dropped the tail would be hiding
    // setups the reader used to be able to reach. No 'view' param - live is
    // the only board this sheet asks for now.
    fetch('api.php?action=board&limit=200',
          { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        if (res.j && res.j.error === 'locked') {
          renderLocked(res.j.live || 0);
          return;
        }
        if (!res.j || !res.j.ok) { throw new Error('bad reply'); }
        loadedAt = Date.now();
        allRows = res.j.signals || [];
        allProgress = res.j.progress || [];
        apply();
      })
      .catch(function () {
        list.innerHTML = '';
        note.textContent = 'Could not load the board just now.';
      });
  }

  function open() {
    lastFocus = document.activeElement;
    pop.hidden = false;
    // Always reopens on the setups list, never mid-way through the by-coin
    // one - a reader who closed the sheet while that was showing should not
    // find the setups list still swapped out next time.
    closeCoins();
    document.body.style.overflow = 'hidden';
    // close() already cleared filters.symbol, but load() below has its own
    // ten-second cache and skips the fetch (and the render that comes with
    // it) on a quick reopen - so without this, the LAST rendered list (still
    // filtered to one coin) would sit on screen even though the filter
    // behind it is already gone, which is exactly the "stuck" complaint this
    // was meant to fix. allRows is still the last fetch's data, so re-running
    // apply() on it now is free and makes the reset visible immediately;
    // load() then refreshes allRows for real once its cache allows it.
    if (allRows.length) { apply(); }
    load();
    // Not loadTopCoins() here any more - the by-coin list is fetched the
    // first time its icon is tapped (see openCoins()), not on every
    // sheet-open, since most visits never ask for it.
    closeBtn.focus();
  }
  function close() {
    pop.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
    // The coin filter resets itself here rather than surviving to the next
    // open - it has no visible control anyone would think to look for on a
    // future visit (see syncSymbolClear()), so a filter that outlived this
    // one would read as the board being stuck on one coin for no reason.
    filters.symbol = '';
  }

  // Sort-button data-val to the filters flag it owns. Kept as one table
  // rather than three near-identical click handlers.
  var SORT_FLAG = { score: 'sortScore', latest: 'sortLatest', winrate: 'sortWinrate' };

  if (coinsBtn) {
    coinsBtn.addEventListener('click', function () {
      if (topCoinsOpen) { closeCoins(); } else { openCoins(); }
    });
  }
  if (symbolClearBtn) {
    symbolClearBtn.addEventListener('click', function () { filters.symbol = ''; apply(); });
  }
  if (jumpBtn) {
    jumpBtn.addEventListener('click', function () {
      var head = list.querySelector('.scan-pop-heading');
      if (head) { head.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  }
  // ONE Escape handler, not two independent ones - the coin list is a state
  // of THIS popup now, not a second one, so Escape steps back to the setups
  // list first (matching a coin tap or the icon itself) and only closes the
  // sheet outright once that state is already off.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    if (topCoinsOpen) { closeCoins(); return; }
    if (!pop.hidden) { close(); }
  });

  (function wireFilters() {
    // Strongest/Latest/Highest win rate each toggle their own flag - see the
    // comment beside this markup in charts.php for why they no longer swap
    // each other off.
    var sortBox = pop.querySelector('.scan-pop-seg[data-filter="sort"]');
    if (sortBox) {
      sortBox.querySelectorAll('button').forEach(function (btn) {
        var flag = SORT_FLAG[btn.dataset.val];
        if (!flag) { return; }
        btn.addEventListener('click', function () {
          filters[flag] = !filters[flag];
          btn.classList.toggle('on', filters[flag]);
          apply();
        });
      });
    }
    var seg = pop.querySelector('.scan-pop-seg[data-filter="dir"]');
    if (seg) {
      seg.querySelectorAll('button').forEach(function (btn) {
        btn.addEventListener('click', function () {
          filters.dir = btn.dataset.val || '';
          seg.querySelectorAll('button').forEach(function (o) {
            o.classList.toggle('on', o === btn);
          });
          apply();
        });
      });
    }
    var tfSel = document.getElementById('scanPopTf');
    if (tfSel) { tfSel.addEventListener('change', function () { filters.tf = tfSel.value; apply(); }); }
    var gSel = document.getElementById('scanPopGrade');
    if (gSel) { gSel.addEventListener('change', function () { filters.grade = gSel.value; apply(); }); }
    var rSel = document.getElementById('scanPopRr');
    if (rSel) { rSel.addEventListener('change', function () { filters.rr = rSel.value; apply(); }); }
  }());

  openBtn.addEventListener('click', open);
  closeBtn.addEventListener('click', close);
  // The backdrop closes it; a click inside the card must not.
  pop.addEventListener('click', function (e) { if (e.target === pop) { close(); } });
  // Escape is handled by the one combined listener above, not a second one
  // here - see the comment beside it for why two independent listeners on
  // the same key both firing on one keystroke was a real bug.
}());
