/* ==========================================================================
   TECHBISS — Soft UI Control Panel
   Shared behaviour: nav, dropdown, gauges, reveals, accordions, tabs,
   carousel, before/after toggle, contact form validation.
   ========================================================================== */
(function () {
  "use strict";

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------------------------------------------------------------------
     Mobile menu
     --------------------------------------------------------------------- */
  var menuToggle = document.getElementById("menuToggle");
  var mobileMenu = document.getElementById("mobile-menu");
  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener("click", function () {
      var open = mobileMenu.classList.toggle("is-open");
      menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    mobileMenu.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        mobileMenu.classList.remove("is-open");
        menuToggle.setAttribute("aria-expanded", "false");
      });
    });
    mobileMenu.querySelectorAll(".msub-trigger").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var sub = document.getElementById(btn.getAttribute("aria-controls"));
        var open = sub.classList.toggle("is-open");
        btn.setAttribute("aria-expanded", open ? "true" : "false");
      });
    });
  }

  /* ---------------------------------------------------------------------
     Desktop "Solutions" dropdown (click + keyboard + outside click)
     --------------------------------------------------------------------- */
  var dropdowns = document.querySelectorAll(".has-dropdown");
  dropdowns.forEach(function (dd) {
    var trigger = dd.querySelector(".nav-link");
    if (!trigger) return;
    trigger.addEventListener("click", function (e) {
      e.preventDefault();
      var isOpen = dd.getAttribute("data-open") === "true";
      dropdowns.forEach(function (o) {
        o.setAttribute("data-open", "false");
        var t = o.querySelector(".nav-link");
        if (t) t.setAttribute("aria-expanded", "false");
      });
      dd.setAttribute("data-open", isOpen ? "false" : "true");
      trigger.setAttribute("aria-expanded", isOpen ? "false" : "true");
    });
  });
  document.addEventListener("click", function (e) {
    dropdowns.forEach(function (dd) {
      if (!dd.contains(e.target)) {
        dd.setAttribute("data-open", "false");
        var t = dd.querySelector(".nav-link");
        if (t) t.setAttribute("aria-expanded", "false");
      }
    });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      dropdowns.forEach(function (dd) {
        dd.setAttribute("data-open", "false");
        var t = dd.querySelector(".nav-link");
        if (t) t.setAttribute("aria-expanded", "false");
      });
    }
  });

  /* ---------------------------------------------------------------------
     Gauge / dial fill animation (percent rings + process dials)
     --------------------------------------------------------------------- */
  function trimNum(n) {
    return (Math.round(n * 10) / 10).toString();
  }

  function animateGauge(el) {
    var target = parseFloat(el.getAttribute("data-target")) || 0;
    var numEl = el.parentElement ? el.parentElement.querySelector(".gauge-num") : null;
    if (!numEl) numEl = el.querySelector(".gauge-num");

    if (reduceMotion) {
      el.style.setProperty("--p", target);
      if (numEl) numEl.textContent = trimNum(target);
      return;
    }

    var duration = 1100;
    var start = null;
    function step(ts) {
      if (start === null) start = ts;
      var elapsed = ts - start;
      var t = Math.min(elapsed / duration, 1);
      var eased = 1 - Math.pow(1 - t, 3);
      var current = target * eased;
      el.style.setProperty("--p", current);
      if (numEl) numEl.textContent = trimNum(current);
      if (t < 1) {
        window.requestAnimationFrame(step);
      } else {
        el.style.setProperty("--p", target);
        if (numEl) numEl.textContent = trimNum(target);
      }
    }
    window.requestAnimationFrame(step);
  }

  var gauges = document.querySelectorAll("[data-target]");
  if ("IntersectionObserver" in window) {
    var gaugeIO = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateGauge(entry.target);
            gaugeIO.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.4 }
    );
    gauges.forEach(function (g) {
      gaugeIO.observe(g);
    });
  } else {
    gauges.forEach(animateGauge);
  }

  /* ---------------------------------------------------------------------
     Reveal on scroll
     --------------------------------------------------------------------- */
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && !reduceMotion) {
    var revealIO = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            revealIO.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15 }
    );
    reveals.forEach(function (r) {
      revealIO.observe(r);
    });
  } else {
    reveals.forEach(function (r) {
      r.classList.add("is-visible");
    });
  }

  /* ---------------------------------------------------------------------
     Process rail: mark steps active as they enter view (visual only)
     --------------------------------------------------------------------- */
  var processSteps = document.querySelectorAll(".process-step");
  if (processSteps.length && "IntersectionObserver" in window) {
    var stepIO = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.setAttribute("data-active", "true");
          }
        });
      },
      { threshold: 0.5 }
    );
    processSteps.forEach(function (s) {
      stepIO.observe(s);
    });
  }

  /* ---------------------------------------------------------------------
     Accordion (FAQ + generic) — works for any [data-accordion-item]
     --------------------------------------------------------------------- */
  document.querySelectorAll(".accordion-trigger").forEach(function (trigger) {
    trigger.addEventListener("click", function () {
      var item = trigger.closest(".accordion-item");
      var group = item ? item.closest(".accordion") : null;
      var wasOpen = item.getAttribute("data-open") === "true";

      if (group && group.hasAttribute("data-single-open")) {
        group.querySelectorAll(".accordion-item").forEach(function (other) {
          if (other !== item) {
            other.setAttribute("data-open", "false");
            var ot = other.querySelector(".accordion-trigger");
            if (ot) ot.setAttribute("aria-expanded", "false");
          }
        });
      }
      item.setAttribute("data-open", wasOpen ? "false" : "true");
      trigger.setAttribute("aria-expanded", wasOpen ? "false" : "true");
    });
  });

  /* ---------------------------------------------------------------------
     Case study cards (portfolio) — same expand pattern, plus filter tabs
     --------------------------------------------------------------------- */
  document.querySelectorAll(".case-head").forEach(function (trigger) {
    trigger.addEventListener("click", function () {
      var card = trigger.closest(".case-card");
      var wasOpen = card.getAttribute("data-open") === "true";
      card.setAttribute("data-open", wasOpen ? "false" : "true");
      trigger.setAttribute("aria-expanded", wasOpen ? "false" : "true");
    });
  });

  var filterTabs = document.querySelector("[data-filter-group]");
  if (filterTabs) {
    var buttons = filterTabs.querySelectorAll("button");
    var cards = document.querySelectorAll("[data-industry]");
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        buttons.forEach(function (b) {
          b.setAttribute("aria-selected", "false");
        });
        btn.setAttribute("aria-selected", "true");
        var filter = btn.getAttribute("data-filter");
        cards.forEach(function (card) {
          var match = filter === "all" || card.getAttribute("data-industry") === filter;
          card.hidden = !match;
        });
      });
    });
  }

  /* ---------------------------------------------------------------------
     Generic segmented tabs -> panel switcher (data-tabs / data-tab-panel)
     --------------------------------------------------------------------- */
  document.querySelectorAll("[data-tabs]").forEach(function (group) {
    var buttons = group.querySelectorAll("button[data-tab-target]");
    var panelWrap = document.querySelector(group.getAttribute("data-tabs"));
    if (!panelWrap) return;
    var panels = panelWrap.querySelectorAll("[data-tab-panel]");
    buttons.forEach(function (btn) {
      btn.addEventListener("click", function () {
        buttons.forEach(function (b) {
          b.setAttribute("aria-selected", "false");
        });
        btn.setAttribute("aria-selected", "true");
        var target = btn.getAttribute("data-tab-target");
        panels.forEach(function (p) {
          p.hidden = p.getAttribute("data-tab-panel") !== target;
        });
      });
    });
  });

  /* ---------------------------------------------------------------------
     Before / After toggle (business-digitization.html)
     --------------------------------------------------------------------- */
  var baToggle = document.querySelector("[data-ba-toggle]");
  if (baToggle) {
    var baViews = document.querySelectorAll(".ba-view");
    var baLabels = document.querySelectorAll(".ba-state-label");
    baToggle.addEventListener("click", function () {
      var pressed = baToggle.getAttribute("aria-pressed") === "true";
      var next = !pressed;
      baToggle.setAttribute("aria-pressed", next ? "true" : "false");
      var state = next ? "on" : "off";
      baViews.forEach(function (v) {
        v.classList.toggle("is-active", v.getAttribute("data-state") === state);
      });
      baLabels.forEach(function (l) {
        l.classList.toggle("is-active", l.getAttribute("data-state") === state);
      });
    });
  }

  /* ---------------------------------------------------------------------
     Carousels (testimonials, portfolio teaser)
     --------------------------------------------------------------------- */
  document.querySelectorAll("[data-carousel]").forEach(function (carousel) {
    var slides = carousel.querySelectorAll(".carousel-slide");
    var dotsWrap = carousel.querySelector(".carousel-dots");
    var prevBtn = carousel.querySelector('[data-dir="prev"]');
    var nextBtn = carousel.querySelector('[data-dir="next"]');
    var index = 0;
    var timer = null;

    if (!slides.length) return;

    var dots = [];
    if (dotsWrap) {
      slides.forEach(function (_, i) {
        var d = document.createElement("button");
        d.className = "carousel-dot";
        d.type = "button";
        d.setAttribute("aria-label", "Go to slide " + (i + 1));
        d.addEventListener("click", function () {
          show(i);
          restart();
        });
        dotsWrap.appendChild(d);
        dots.push(d);
      });
    }

    function show(i) {
      index = (i + slides.length) % slides.length;
      slides.forEach(function (s, si) {
        s.classList.toggle("is-active", si === index);
      });
      dots.forEach(function (d, di) {
        d.setAttribute("aria-current", di === index ? "true" : "false");
      });
    }

    function restart() {
      if (timer) clearInterval(timer);
      if (!reduceMotion) {
        timer = setInterval(function () {
          show(index + 1);
        }, 6500);
      }
    }

    if (prevBtn) prevBtn.addEventListener("click", function () { show(index - 1); restart(); });
    if (nextBtn) nextBtn.addEventListener("click", function () { show(index + 1); restart(); });

    show(0);
    restart();
  });

  /* ---------------------------------------------------------------------
     Contact form validation
     --------------------------------------------------------------------- */
  var form = document.getElementById("contact-form");
  if (form) {
    var successPanel = document.getElementById("form-success");

    var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var phoneRe = /^[0-9+()\-.\s]{7,20}$/;

    function fieldOf(input) {
      return input.closest(".field");
    }

    function setError(input, message) {
      var field = fieldOf(input);
      field.classList.add("has-error");
      var errEl = field.querySelector(".field-error");
      if (errEl) errEl.textContent = message;
      input.setAttribute("aria-invalid", "true");
    }

    function clearError(input) {
      var field = fieldOf(input);
      field.classList.remove("has-error");
      input.removeAttribute("aria-invalid");
    }

    function validateField(input) {
      var value = input.value.trim();
      if (input.hasAttribute("required") && !value) {
        setError(input, "This field is required.");
        return false;
      }
      if (input.type === "email" && value && !emailRe.test(value)) {
        setError(input, "Enter a valid email address.");
        return false;
      }
      if (input.type === "tel" && value && !phoneRe.test(value)) {
        setError(input, "Enter a valid phone number.");
        return false;
      }
      if (input.tagName === "SELECT" && input.hasAttribute("required") && !value) {
        setError(input, "Please make a selection.");
        return false;
      }
      clearError(input);
      return true;
    }

    var inputs = form.querySelectorAll("input, select, textarea");
    inputs.forEach(function (input) {
      input.addEventListener("blur", function () {
        validateField(input);
      });
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var valid = true;
      inputs.forEach(function (input) {
        if (!validateField(input)) valid = false;
      });

      if (!valid) {
        var firstError = form.querySelector(".has-error input, .has-error select, .has-error textarea");
        if (firstError) firstError.focus();
        return;
      }

      form.hidden = true;
      if (successPanel) {
        successPanel.classList.add("is-visible");
        successPanel.setAttribute("tabindex", "-1");
        successPanel.focus();
      }
    });
  }
})();
