/* =========================================================================
   TECHBISS — Concept 02: Executive Digital Empire
   Vanilla JS motion system. Zero dependencies, zero build step.
   ========================================================================= */
(function () {
  "use strict";

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* --------------------------- Sticky header border ---------------------- */
  var header = document.querySelector(".site-header");
  function onScrollHeader() {
    if (!header) return;
    header.classList.toggle("is-scrolled", window.scrollY > 8);
  }
  onScrollHeader();
  window.addEventListener("scroll", onScrollHeader, { passive: true });

  /* --------------------------------- Mobile nav --------------------------- */
  var navToggle = document.querySelector("[data-nav-toggle]");
  var mobileNav = document.querySelector("[data-mobile-nav]");
  var navClose = document.querySelector("[data-nav-close]");

  function openMobileNav() {
    if (!mobileNav) return;
    mobileNav.classList.add("is-open");
    navToggle.setAttribute("aria-expanded", "true");
    document.documentElement.classList.add("nav-open");
    var firstLink = mobileNav.querySelector("a, button");
    if (firstLink) firstLink.focus({ preventScroll: true });
  }
  function closeMobileNav() {
    if (!mobileNav) return;
    mobileNav.classList.remove("is-open");
    navToggle.setAttribute("aria-expanded", "false");
    document.documentElement.classList.remove("nav-open");
    if (navToggle) navToggle.focus({ preventScroll: true });
  }
  if (navToggle && mobileNav) {
    navToggle.addEventListener("click", function () {
      var expanded = navToggle.getAttribute("aria-expanded") === "true";
      if (expanded) { closeMobileNav(); } else { openMobileNav(); }
    });
  }
  if (navClose) navClose.addEventListener("click", closeMobileNav);
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && mobileNav && mobileNav.classList.contains("is-open")) {
      closeMobileNav();
    }
  });
  // Mobile submenu accordions
  document.querySelectorAll("[data-mobile-submenu-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var panel = document.getElementById(btn.getAttribute("aria-controls"));
      var open = btn.getAttribute("aria-expanded") === "true";
      btn.setAttribute("aria-expanded", String(!open));
      if (panel) panel.hidden = open;
    });
  });

  /* --------------------------------- Reveal on scroll ---------------------- */
  var revealEls = document.querySelectorAll("[data-reveal]");
  if ("IntersectionObserver" in window && !reduceMotion && revealEls.length) {
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: "0px 0px -8% 0px" }
    );
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add("is-visible"); });
  }

  /* --------------------------------- Stat counters -------------------------- */
  var counters = document.querySelectorAll("[data-count-to]");
  function animateCount(el) {
    var target = parseFloat(el.getAttribute("data-count-to"));
    var suffix = el.getAttribute("data-count-suffix") || "";
    var decimals = el.getAttribute("data-count-decimals") ? parseInt(el.getAttribute("data-count-decimals"), 10) : 0;
    if (reduceMotion) {
      el.textContent = target.toFixed(decimals) + suffix;
      return;
    }
    var duration = 1400;
    var start = null;
    function step(ts) {
      if (start === null) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var value = target * eased;
      el.textContent = value.toFixed(decimals) + suffix;
      if (progress < 1) {
        window.requestAnimationFrame(step);
      } else {
        el.textContent = target.toFixed(decimals) + suffix;
      }
    }
    window.requestAnimationFrame(step);
  }
  if ("IntersectionObserver" in window && counters.length) {
    var countIo = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateCount(entry.target);
            countIo.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.4 }
    );
    counters.forEach(function (el) { countIo.observe(el); });
  } else {
    counters.forEach(animateCount);
  }

  /* --------------------------------- Dropdown (keyboard) --------------------- */
  document.querySelectorAll(".nav-item-dropdown").forEach(function (item) {
    var toggle = item.querySelector(".nav-dropdown-toggle");
    if (!toggle) return;
    toggle.addEventListener("click", function (e) {
      e.preventDefault();
      var panel = item.querySelector(".nav-dropdown-panel");
      var isOpen = item.classList.contains("force-open");
      document.querySelectorAll(".nav-item-dropdown.force-open").forEach(function (o) {
        o.classList.remove("force-open");
      });
      if (!isOpen) item.classList.toggle("force-open", true);
    });
  });
  document.addEventListener("click", function (e) {
    if (!e.target.closest(".nav-item-dropdown")) {
      document.querySelectorAll(".nav-item-dropdown.force-open").forEach(function (o) {
        o.classList.remove("force-open");
      });
    }
  });

  /* --------------------------------- FAQ accordion --------------------------- */
  document.querySelectorAll(".faq-item").forEach(function (item) {
    var q = item.querySelector(".faq-q");
    var a = item.querySelector(".faq-a");
    if (!q || !a) return;
    q.addEventListener("click", function () {
      var isOpen = item.getAttribute("data-open") === "true";
      // Close siblings within the same group for a cleaner reading flow
      var group = item.closest(".faq-group");
      if (group) {
        group.querySelectorAll(".faq-item").forEach(function (sib) {
          if (sib !== item) {
            sib.setAttribute("data-open", "false");
            sib.querySelector(".faq-q").setAttribute("aria-expanded", "false");
            sib.querySelector(".faq-a").style.height = "0px";
          }
        });
      }
      if (isOpen) {
        item.setAttribute("data-open", "false");
        q.setAttribute("aria-expanded", "false");
        a.style.height = "0px";
      } else {
        item.setAttribute("data-open", "true");
        q.setAttribute("aria-expanded", "true");
        a.style.height = a.scrollHeight + "px";
      }
    });
  });
  // Keep open panels sized correctly on resize
  window.addEventListener("resize", function () {
    document.querySelectorAll('.faq-item[data-open="true"] .faq-a').forEach(function (a) {
      a.style.height = a.scrollHeight + "px";
    });
  });

  /* --------------------------------- Slider (portfolio) ----------------------- */
  document.querySelectorAll("[data-slider]").forEach(function (root) {
    var track = root.querySelector(".slider-track");
    var slides = Array.prototype.slice.call(root.querySelectorAll(".slide"));
    var prevBtn = root.querySelector("[data-slider-prev]");
    var nextBtn = root.querySelector("[data-slider-next]");
    var tabs = Array.prototype.slice.call(root.querySelectorAll(".slider-tab"));
    var counter = root.querySelector("[data-slider-counter]");
    var index = 0;
    var total = slides.length;

    function render() {
      track.style.transform = "translateX(-" + index * 100 + "%)";
      tabs.forEach(function (t, i) {
        t.setAttribute("aria-current", i === index ? "true" : "false");
      });
      if (counter) {
        counter.textContent = String(index + 1).padStart(2, "0") + " / " + String(total).padStart(2, "0");
      }
      if (prevBtn) prevBtn.disabled = false;
      if (nextBtn) nextBtn.disabled = false;
    }
    function goTo(i) {
      index = (i + total) % total;
      render();
    }
    if (prevBtn) prevBtn.addEventListener("click", function () { goTo(index - 1); });
    if (nextBtn) nextBtn.addEventListener("click", function () { goTo(index + 1); });
    tabs.forEach(function (t, i) {
      t.addEventListener("click", function () { goTo(i); });
    });
    root.setAttribute("tabindex", "0");
    root.addEventListener("keydown", function (e) {
      if (e.key === "ArrowRight") { goTo(index + 1); }
      if (e.key === "ArrowLeft") { goTo(index - 1); }
    });

    // Touch swipe
    var startX = null;
    track.addEventListener("touchstart", function (e) { startX = e.touches[0].clientX; }, { passive: true });
    track.addEventListener("touchend", function (e) {
      if (startX === null) return;
      var diff = e.changedTouches[0].clientX - startX;
      if (Math.abs(diff) > 40) {
        if (diff < 0) { goTo(index + 1); } else { goTo(index - 1); }
      }
      startX = null;
    });

    render();
  });

  /* --------------------------------- Contact form validation ------------------ */
  var form = document.querySelector("[data-contact-form]");
  if (form) {
    var successPanel = document.querySelector("[data-form-success]");
    var fields = Array.prototype.slice.call(form.querySelectorAll("[data-validate]"));

    function showError(field, message) {
      var wrap = field.closest(".field");
      var errorEl = wrap ? wrap.querySelector(".field-error") : null;
      if (wrap) wrap.classList.add("has-error");
      if (errorEl) errorEl.textContent = message;
      field.setAttribute("aria-invalid", "true");
    }
    function clearError(field) {
      var wrap = field.closest(".field");
      var errorEl = wrap ? wrap.querySelector(".field-error") : null;
      if (wrap) wrap.classList.remove("has-error");
      if (errorEl) errorEl.textContent = "";
      field.setAttribute("aria-invalid", "false");
    }
    function validateField(field) {
      var value = field.value.trim();
      var rule = field.getAttribute("data-validate");
      if (rule === "required" && value === "") {
        showError(field, "This field is required.");
        return false;
      }
      if (rule === "email") {
        if (value === "") { showError(field, "Email address is required."); return false; }
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!re.test(value)) { showError(field, "Enter a valid email address."); return false; }
      }
      if (rule === "phone" && value !== "") {
        var phoneRe = /^[0-9+\-\s()]{7,}$/;
        if (!phoneRe.test(value)) { showError(field, "Enter a valid phone number."); return false; }
      }
      if (rule === "select" && (value === "" || value === "placeholder")) {
        showError(field, "Please make a selection.");
        return false;
      }
      if (rule === "textarea-required") {
        if (value === "") { showError(field, "Tell us a little about your project."); return false; }
        if (value.length < 12) { showError(field, "Please add a bit more detail (12+ characters)."); return false; }
      }
      clearError(field);
      return true;
    }
    fields.forEach(function (field) {
      field.addEventListener("blur", function () { validateField(field); });
      field.addEventListener("input", function () {
        if (field.closest(".field").classList.contains("has-error")) validateField(field);
      });
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
      form.classList.add("is-hidden");
      if (successPanel) {
        successPanel.classList.add("is-visible");
        successPanel.setAttribute("tabindex", "-1");
        successPanel.focus();
      }
    });
  }

  /* --------------------------------- Active nav underline (already via aria-current CSS) */
})();
