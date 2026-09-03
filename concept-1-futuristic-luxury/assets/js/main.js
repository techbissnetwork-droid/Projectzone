/* ==========================================================================
   TECHBISS — Concept 01: Futuristic Luxury
   Vanilla JS. Zero dependencies. Zero build step.
   ========================================================================== */
(function () {
  "use strict";

  var prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var hasFinePointer = window.matchMedia("(pointer: fine)").matches;

  /* ------------------------------------------------------------------ */
  /* Sticky header                                                       */
  /* ------------------------------------------------------------------ */
  function initHeader() {
    var header = document.querySelector(".site-header");
    if (!header) return;
    function onScroll() {
      if (window.scrollY > 12) {
        header.classList.add("is-solid");
      } else {
        header.classList.remove("is-solid");
      }
    }
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  /* ------------------------------------------------------------------ */
  /* Active nav link per current page                                    */
  /* ------------------------------------------------------------------ */
  function initActiveNav() {
    var page = document.body.getAttribute("data-page");
    if (!page) return;
    var links = document.querySelectorAll("[data-nav-link]");
    links.forEach(function (link) {
      if (link.getAttribute("data-nav-link") === page) {
        link.setAttribute("aria-current", "page");
      }
    });
  }

  /* ------------------------------------------------------------------ */
  /* Mobile nav                                                           */
  /* ------------------------------------------------------------------ */
  function initMobileNav() {
    var toggle = document.querySelector(".nav-toggle");
    var menu = document.querySelector(".nav-mobile");
    var closeBtn = document.querySelector(".nav-mobile-close");
    if (!toggle || !menu) return;

    function openMenu() {
      menu.classList.add("is-open");
      toggle.setAttribute("aria-expanded", "true");
      document.body.classList.add("nav-open");
    }
    function closeMenu() {
      menu.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
      document.body.classList.remove("nav-open");
    }
    toggle.addEventListener("click", function () {
      var expanded = toggle.getAttribute("aria-expanded") === "true";
      expanded ? closeMenu() : openMenu();
    });
    if (closeBtn) closeBtn.addEventListener("click", closeMenu);

    menu.querySelectorAll(".nav-mobile-list > li > a").forEach(function (a) {
      a.addEventListener("click", closeMenu);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && menu.classList.contains("is-open")) closeMenu();
    });

    // Mobile submenu (Solutions dropdown) toggle
    var subToggle = document.querySelector(".nav-mobile-sub-toggle");
    var sub = document.querySelector(".nav-mobile-sub");
    if (subToggle && sub) {
      subToggle.addEventListener("click", function () {
        var expanded = subToggle.getAttribute("aria-expanded") === "true";
        subToggle.setAttribute("aria-expanded", String(!expanded));
        sub.classList.toggle("is-open");
      });
    }
  }

  /* ------------------------------------------------------------------ */
  /* Scroll reveal via IntersectionObserver                               */
  /* ------------------------------------------------------------------ */
  function initReveal() {
    var items = document.querySelectorAll(".reveal");
    if (!items.length) return;

    if (prefersReducedMotion || !("IntersectionObserver" in window)) {
      items.forEach(function (el) { el.classList.add("is-visible"); });
      return;
    }

    // Stagger delay per item within its parent group, via data-reveal-index or nth-child order
    items.forEach(function (el) {
      if (!el.style.getPropertyValue("--reveal-delay")) {
        var idx = el.getAttribute("data-reveal-index");
        if (idx !== null) {
          el.style.setProperty("--reveal-delay", (parseInt(idx, 10) * 90) + "ms");
        }
      }
    });

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: "0px 0px -60px 0px" }
    );

    items.forEach(function (el) { observer.observe(el); });
  }

  /* ------------------------------------------------------------------ */
  /* Hero parallax (mousemove, fine pointer only, reduced-motion aware)   */
  /* ------------------------------------------------------------------ */
  function initHeroParallax() {
    var hero = document.querySelector("[data-hero-parallax]");
    if (!hero) return;
    if (prefersReducedMotion || !hasFinePointer) return;

    var layers = hero.querySelectorAll(".hero-glow");
    if (!layers.length) return;

    var targetX = 0, targetY = 0, currentX = 0, currentY = 0;
    var raf = null;

    function onMove(e) {
      var rect = hero.getBoundingClientRect();
      var relX = (e.clientX - rect.left) / rect.width - 0.5;
      var relY = (e.clientY - rect.top) / rect.height - 0.5;
      targetX = relX;
      targetY = relY;
      if (!raf) raf = requestAnimationFrame(tick);
    }

    function tick() {
      currentX += (targetX - currentX) * 0.06;
      currentY += (targetY - currentY) * 0.06;
      layers.forEach(function (layer, i) {
        var strength = (i + 1) * 4; // small px offsets, a few px max
        var rot = (i + 1) * 0.4;
        layer.style.transform =
          "translate3d(" + (currentX * strength) + "px, " + (currentY * strength) + "px, 0) rotate(" + (currentX * rot) + "deg)";
      });
      if (Math.abs(targetX - currentX) > 0.001 || Math.abs(targetY - currentY) > 0.001) {
        raf = requestAnimationFrame(tick);
      } else {
        raf = null;
      }
    }

    hero.addEventListener("mousemove", onMove);
    hero.addEventListener("mouseleave", function () {
      targetX = 0; targetY = 0;
      if (!raf) raf = requestAnimationFrame(tick);
    });
  }

  /* ------------------------------------------------------------------ */
  /* Animated stat counters                                               */
  /* ------------------------------------------------------------------ */
  function initCounters() {
    var counters = document.querySelectorAll("[data-counter]");
    if (!counters.length) return;

    function animate(el) {
      var target = parseFloat(el.getAttribute("data-counter"));
      var suffix = el.getAttribute("data-counter-suffix") || "";
      var decimals = el.getAttribute("data-counter-decimals") ? parseInt(el.getAttribute("data-counter-decimals"), 10) : 0;

      if (prefersReducedMotion) {
        el.textContent = target.toFixed(decimals) + suffix;
        return;
      }

      var duration = 1400;
      var start = null;

      function step(ts) {
        if (!start) start = ts;
        var progress = Math.min((ts - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        var value = target * eased;
        el.textContent = value.toFixed(decimals) + suffix;
        if (progress < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }

    if (!("IntersectionObserver" in window)) {
      counters.forEach(animate);
      return;
    }

    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animate(entry.target);
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.4 }
    );
    counters.forEach(function (el) { observer.observe(el); });
  }

  /* ------------------------------------------------------------------ */
  /* Hand-rolled carousel                                                 */
  /* ------------------------------------------------------------------ */
  function initCarousels() {
    var carousels = document.querySelectorAll("[data-carousel]");
    carousels.forEach(function (root) {
      var viewport = root.querySelector(".carousel-viewport");
      var track = root.querySelector(".carousel-track");
      var prevBtn = root.querySelector(".carousel-prev");
      var nextBtn = root.querySelector(".carousel-next");
      var dotsWrap = root.querySelector(".carousel-dots");
      if (!viewport || !track) return;

      var items = Array.prototype.slice.call(track.children);
      if (!items.length) return;

      var index = 0;
      var perView = getPerView();
      var pointerStartX = null;
      var trackStartOffset = 0;

      function getPerView() {
        var w = window.innerWidth;
        if (w >= 1100) return 3;
        if (w >= 760) return 2;
        return 1;
      }

      function maxIndex() {
        return Math.max(0, items.length - perView);
      }

      function buildDots() {
        if (!dotsWrap) return;
        dotsWrap.innerHTML = "";
        var count = maxIndex() + 1;
        for (var i = 0; i < count; i++) {
          var dot = document.createElement("button");
          dot.className = "carousel-dot";
          dot.type = "button";
          dot.setAttribute("aria-label", "Go to slide " + (i + 1));
          dot.addEventListener("click", function (idx) {
            return function () { goTo(idx); };
          }(i));
          dotsWrap.appendChild(dot);
        }
        updateDots();
      }

      function updateDots() {
        if (!dotsWrap) return;
        Array.prototype.forEach.call(dotsWrap.children, function (dot, i) {
          dot.classList.toggle("is-active", i === index);
        });
      }

      function update(animate) {
        var itemWidth = items[0].getBoundingClientRect().width;
        var gap = parseFloat(getComputedStyle(track).gap || "24");
        var offset = index * (itemWidth + gap);
        track.style.transition = animate === false ? "none" : "";
        track.style.transform = "translateX(-" + offset + "px)";
        updateDots();
        if (prevBtn) prevBtn.disabled = index === 0;
        if (nextBtn) nextBtn.disabled = index >= maxIndex();
      }

      function goTo(i) {
        index = Math.max(0, Math.min(i, maxIndex()));
        update();
      }

      if (prevBtn) prevBtn.addEventListener("click", function () { goTo(index - 1); });
      if (nextBtn) nextBtn.addEventListener("click", function () { goTo(index + 1); });

      root.setAttribute("tabindex", "0");
      root.addEventListener("keydown", function (e) {
        if (e.key === "ArrowLeft") { e.preventDefault(); goTo(index - 1); }
        if (e.key === "ArrowRight") { e.preventDefault(); goTo(index + 1); }
      });

      // Pointer / touch swipe
      viewport.addEventListener("pointerdown", function (e) {
        pointerStartX = e.clientX;
        trackStartOffset = index;
        track.style.transition = "none";
        viewport.setPointerCapture && viewport.setPointerCapture(e.pointerId);
      });
      viewport.addEventListener("pointerup", function (e) {
        if (pointerStartX === null) return;
        var dx = e.clientX - pointerStartX;
        track.style.transition = "";
        if (dx < -50) goTo(trackStartOffset + 1);
        else if (dx > 50) goTo(trackStartOffset - 1);
        else update();
        pointerStartX = null;
      });
      viewport.addEventListener("pointercancel", function () { pointerStartX = null; update(); });

      window.addEventListener("resize", function () {
        var newPerView = getPerView();
        if (newPerView !== perView) {
          perView = newPerView;
          buildDots();
        }
        update(false);
      });

      buildDots();
      update(false);
    });
  }

  /* ------------------------------------------------------------------ */
  /* FAQ accordion                                                        */
  /* ------------------------------------------------------------------ */
  function initAccordions() {
    var questions = document.querySelectorAll(".faq-question");
    questions.forEach(function (btn) {
      var panel = document.getElementById(btn.getAttribute("aria-controls"));
      if (!panel) return;
      var inner = panel.querySelector(".faq-answer-inner");
      btn.addEventListener("click", function () {
        var expanded = btn.getAttribute("aria-expanded") === "true";
        btn.setAttribute("aria-expanded", String(!expanded));
        if (expanded) {
          panel.style.maxHeight = null;
        } else {
          panel.style.maxHeight = inner.offsetHeight + "px";
        }
      });
    });
  }

  /* ------------------------------------------------------------------ */
  /* Portfolio case-card expand / filter                                  */
  /* ------------------------------------------------------------------ */
  function initCaseCards() {
    var toggles = document.querySelectorAll(".case-toggle-btn");
    toggles.forEach(function (btn) {
      var panel = document.getElementById(btn.getAttribute("aria-controls"));
      if (!panel) return;
      btn.addEventListener("click", function () {
        var expanded = btn.getAttribute("aria-expanded") === "true";
        btn.setAttribute("aria-expanded", String(!expanded));
        panel.classList.toggle("is-open", !expanded);
        btn.querySelector("span") && (btn.querySelector("span").textContent = expanded ? "View Case Study" : "Hide Case Study");
      });
    });

    var chips = document.querySelectorAll(".filter-chip");
    var cards = document.querySelectorAll("[data-industry]");
    chips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        chips.forEach(function (c) { c.classList.remove("is-active"); c.setAttribute("aria-pressed", "false"); });
        chip.classList.add("is-active");
        chip.setAttribute("aria-pressed", "true");
        var filter = chip.getAttribute("data-filter");
        cards.forEach(function (card) {
          var match = filter === "all" || card.getAttribute("data-industry") === filter;
          card.style.display = match ? "" : "none";
        });
      });
    });
  }

  /* ------------------------------------------------------------------ */
  /* Contact form validation                                              */
  /* ------------------------------------------------------------------ */
  function initContactForm() {
    var form = document.querySelector("[data-contact-form]");
    if (!form) return;
    var success = document.querySelector("[data-form-success]");

    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function setError(field, message) {
      var wrap = field.closest(".field");
      var errorEl = wrap.querySelector(".field-error");
      if (message) {
        wrap.classList.add("has-error");
        if (errorEl) errorEl.textContent = message;
        field.setAttribute("aria-invalid", "true");
      } else {
        wrap.classList.remove("has-error");
        if (errorEl) errorEl.textContent = "";
        field.removeAttribute("aria-invalid");
      }
    }

    function validateField(field) {
      var value = field.value.trim();
      if (field.hasAttribute("required") && !value) {
        setError(field, "This field is required.");
        return false;
      }
      if (field.type === "email" && value && !emailPattern.test(value)) {
        setError(field, "Enter a valid email address.");
        return false;
      }
      if (field.type === "tel" && value && value.replace(/[^0-9]/g, "").length < 7) {
        setError(field, "Enter a valid phone number.");
        return false;
      }
      setError(field, "");
      return true;
    }

    var fields = form.querySelectorAll("input, select, textarea");
    fields.forEach(function (field) {
      field.addEventListener("blur", function () { validateField(field); });
    });

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var valid = true;
      fields.forEach(function (field) {
        if (!validateField(field)) valid = false;
      });
      if (!valid) {
        var firstError = form.querySelector(".has-error input, .has-error select, .has-error textarea");
        if (firstError) firstError.focus();
        return;
      }
      form.classList.add("is-submitted");
      if (success) {
        success.classList.add("is-visible");
        success.setAttribute("tabindex", "-1");
        success.focus();
      }
    });
  }

  /* ------------------------------------------------------------------ */
  /* Init                                                                 */
  /* ------------------------------------------------------------------ */
  document.addEventListener("DOMContentLoaded", function () {
    initHeader();
    initActiveNav();
    initMobileNav();
    initReveal();
    initHeroParallax();
    initCounters();
    initCarousels();
    initAccordions();
    initCaseCards();
    initContactForm();
  });
})();
