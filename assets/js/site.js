/* ==========================================================================
   TECHBISS — site behaviour
   Every block checks that its elements exist, so one file serves every page.
   Nothing here is required for the content to be readable.
   ========================================================================== */
(function () {
  "use strict";

  var RM = matchMedia("(prefers-reduced-motion: reduce)").matches;
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };

  /* --- background light field ------------------------------------------ */
  var cv = $("#blobs");
  if (cv) {
    var ctx = cv.getContext("2d"), W, H;
    var ps = [
      { x: .2, y: .3, r: 280, c: "201,255,61", vx: .35, vy: .22 },
      { x: .7, y: .25, r: 330, c: "123,97,255", vx: -.28, vy: .31 },
      { x: .5, y: .75, r: 300, c: "255,107,61", vx: .24, vy: -.27 }
    ];
    var sized = false;
    function size() {
      W = cv.width = innerWidth; H = cv.height = innerHeight;
      if (!sized) { ps.forEach(function (p) { p.px = p.x * W; p.py = p.y * H; }); sized = true; }
    }
    size();
    addEventListener("resize", size);
    function tick() {
      ctx.clearRect(0, 0, W, H);
      ctx.globalCompositeOperation = "lighter";
      ps.forEach(function (p) {
        p.px += p.vx; p.py += p.vy;
        if (p.px < -p.r * .4 || p.px > W + p.r * .4) p.vx *= -1;
        if (p.py < -p.r * .4 || p.py > H + p.r * .4) p.vy *= -1;
        var g = ctx.createRadialGradient(p.px, p.py, 0, p.px, p.py, p.r);
        g.addColorStop(0, "rgba(" + p.c + ",.16)");
        g.addColorStop(.5, "rgba(" + p.c + ",.05)");
        g.addColorStop(1, "rgba(" + p.c + ",0)");
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.arc(p.px, p.py, p.r, 0, 6.2832); ctx.fill();
      });
      ctx.globalCompositeOperation = "source-over";
      if (!RM) requestAnimationFrame(tick);
    }
    tick();
  }

  /* --- header ----------------------------------------------------------- */
  var hdr = $(".site-head");
  if (hdr) addEventListener("scroll", function () {
    hdr.classList.toggle("on", scrollY > 40);
  }, { passive: true });

  /* --- mobile menu ------------------------------------------------------ */
  var mb = $("#mb"), sheet = $("#sheet");
  if (mb && sheet) {
    function closeSheet() {
      sheet.classList.remove("on"); mb.textContent = "☰";
      mb.setAttribute("aria-expanded", "false");
      document.body.classList.remove("is-locked");
    }
    mb.addEventListener("click", function () {
      var on = sheet.classList.toggle("on");
      mb.textContent = on ? "✕" : "☰";
      mb.setAttribute("aria-expanded", on ? "true" : "false");
      document.body.classList.toggle("is-locked", on);
    });
    $$("a", sheet).forEach(function (a) { a.addEventListener("click", closeSheet); });
    addEventListener("keydown", function (e) { if (e.key === "Escape") closeSheet(); });
  }

  /* --- marquee: duplicate the strip so the loop has no seam ------------- */
  var runner = $("#runner");
  if (runner) runner.innerHTML += runner.innerHTML;

  /* --- chrome shimmer follows scroll position --------------------------- */
  var chromes = $$(".chrome");
  if (chromes.length) {
    function shimmer() {
      var h = document.documentElement.scrollHeight - innerHeight;
      var p = h > 0 ? scrollY / h : 0;
      chromes.forEach(function (c, i) {
        c.style.setProperty("--cp", (p * 160 + i * 40) % 100 + "%");
      });
    }
    addEventListener("scroll", shimmer, { passive: true });
    shimmer();
  }

  /* --- stacking cards: each dims as the next slides over it ------------- */
  var cards = $$(".sc");
  if (cards.length && !RM) {
    addEventListener("scroll", function () {
      cards.forEach(function (c, i) {
        if (i === cards.length - 1) { c.style.transform = ""; c.style.filter = ""; return; }
        var travel = c.getBoundingClientRect().top - 110;
        if (travel < 0) {
          var k = Math.min(-travel / (innerHeight * .85), 1);
          c.style.transform = "scale(" + (1 - k * .06) + ") translateY(" + (-k * 14) + "px)";
          c.style.filter = "brightness(" + (1 - k * .22) + ")";
        } else { c.style.transform = ""; c.style.filter = ""; }
      });
    }, { passive: true });
  }

  /* --- reveal on scroll -------------------------------------------------- */
  var rv = $$(".rv");
  if (rv.length) {
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
      });
    }, { threshold: .14 });
    rv.forEach(function (el, i) {
      el.style.transitionDelay = (i % 4) * .08 + "s";
      io.observe(el);
    });
  }

  /* --- process rail ------------------------------------------------------ */
  var ps2 = $$(".ps");
  if (ps2.length) {
    var pio = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add("in"); pio.unobserve(e.target); }
      });
    }, { threshold: .4 });
    ps2.forEach(function (el) { pio.observe(el); });
  }

  /* --- counters ---------------------------------------------------------- */
  var counts = $$(".count");
  if (counts.length) {
    var cio = new IntersectionObserver(function (es) {
      es.forEach(function (e) {
        if (!e.isIntersecting) return;
        cio.unobserve(e.target);
        var n = e.target, to = parseFloat(n.dataset.to);
        var dec = parseInt(n.dataset.dec || 0, 10), start = null;
        if (RM) { n.textContent = dec ? to.toFixed(dec) : to; return; }
        requestAnimationFrame(function step(ts) {
          if (!start) start = ts;
          var p = Math.min((ts - start) / 1500, 1);
          var v = to * (1 - Math.pow(1 - p, 4));
          n.textContent = dec ? v.toFixed(dec) : Math.round(v);
          if (p < 1) requestAnimationFrame(step);
        });
      });
    }, { threshold: .55 });
    counts.forEach(function (n) { cio.observe(n); });
  }

  /* --- spotlight follows the pointer across a card ---------------------- */
  $$(".card").forEach(function (el) {
    el.addEventListener("mousemove", function (e) {
      var r = el.getBoundingClientRect();
      el.style.setProperty("--mx", (e.clientX - r.left) / r.width * 100 + "%");
      el.style.setProperty("--my", (e.clientY - r.top) / r.height * 100 + "%");
    });
  });

  /* --- quote slider ------------------------------------------------------ */
  var qs = $$(".qs"), qbox = $("#qbox");
  if (qs.length > 1) {
    var qi = 0, timer = null;
    function go(i) {
      qi = (i + qs.length) % qs.length;
      qs.forEach(function (q, j) { q.classList.toggle("on", j === qi); });
      if (timer) clearInterval(timer);
      if (!RM) timer = setInterval(function () { go(qi + 1); }, 6500);
    }
    var next = $("#qnext"), prev = $("#qprev");
    if (next) next.addEventListener("click", function () { go(qi + 1); });
    if (prev) prev.addEventListener("click", function () { go(qi - 1); });
    if (!RM) timer = setInterval(function () { go(qi + 1); }, 6500);
    if (qbox) {
      var sx = null;
      qbox.addEventListener("pointerdown", function (e) { sx = e.clientX; });
      qbox.addEventListener("pointerup", function (e) {
        if (sx === null) return;
        var d = e.clientX - sx;
        if (Math.abs(d) > 50) go(qi + (d < 0 ? 1 : -1));
        sx = null;
      });
    }
  }

  /* --- FAQ accordion ----------------------------------------------------- */
  $$(".acc").forEach(function (acc) {
    var items = $$(".item", acc);
    function setOpen(item, open) {
      var a = $(".a", item), btn = $(".q", item);
      item.classList.toggle("open", open);
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      a.style.maxHeight = open ? a.scrollHeight + "px" : "0px";
    }
    items.forEach(function (item, i) {
      $(".q", item).addEventListener("click", function () {
        var open = !item.classList.contains("open");
        items.forEach(function (o) { setOpen(o, false); });
        if (open) setOpen(item, true);
      });
      setOpen(item, i === 0);
    });
    addEventListener("resize", function () {
      items.forEach(function (o) { if (o.classList.contains("open")) setOpen(o, true); });
    });
  });

  /* --- enquiry form ------------------------------------------------------
     Validates in the browser, then posts to contact.php. If that endpoint
     is not there yet, the visitor is told to email instead — never a
     silent failure.                                                        */
  var form = $("form.enquiry");
  if (form) {
    var status = $("#formstatus");
    function fieldOf(input) { return input.closest(".field"); }
    function validate(input) {
      var ok = input.checkValidity();
      var f = fieldOf(input);
      if (f) f.classList.toggle("invalid", !ok);
      return ok;
    }
    $$("input,select,textarea", form).forEach(function (input) {
      input.addEventListener("blur", function () { validate(input); });
      input.addEventListener("input", function () {
        var f = fieldOf(input);
        if (f && f.classList.contains("invalid")) validate(input);
      });
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var inputs = $$("input,select,textarea", form);
      var bad = inputs.filter(function (i) { return !validate(i); });
      if (bad.length) { bad[0].focus(); return; }
      if (form.querySelector('[name="company_website"]').value) return; /* honeypot */

      var btn = $("button[type=submit]", form);
      var label = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Sending…";
      status.className = "formstatus";

      fetch(form.action, { method: "POST", body: new FormData(form) })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (data) {
          if (!data.ok) return Promise.reject(data.error || "rejected");
          form.reset();
          status.className = "formstatus show ok";
          status.textContent = "Thanks — that reached us. We answer within one business day.";
        })
        .catch(function () {
          status.className = "formstatus show bad";
          status.innerHTML = "That did not send. Email <a href=\"mailto:hello@techbiss.com\" " +
            "style=\"color:inherit;text-decoration:underline\">hello@techbiss.com</a> " +
            "and we will pick it up from there.";
        })
        .then(function () { btn.disabled = false; btn.textContent = label; });
    });
  }
})();
