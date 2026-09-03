/* ===================================================================
   TECHBISS — Concept 03: Next-Generation Digital World
   Vanilla JS motion system. Zero dependencies.
   =================================================================== */
(function(){
  "use strict";

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------------- Sticky header solidify ---------------- */
  var header = document.querySelector(".site-header");
  function onScrollHeader(){
    if(!header) return;
    if(window.scrollY > 12){ header.classList.add("is-solid"); }
    else{ header.classList.remove("is-solid"); }
  }
  onScrollHeader();
  window.addEventListener("scroll", onScrollHeader, { passive:true });

  /* ---------------- Mega menu (desktop) ---------------- */
  document.querySelectorAll(".has-mega").forEach(function(item){
    var trigger = item.querySelector(":scope > button, :scope > .nav-link > button");
    var toggleBtn = item.querySelector("button");
    if(!toggleBtn) return;

    function open(){ item.classList.add("is-open"); toggleBtn.setAttribute("aria-expanded","true"); }
    function close(){ item.classList.remove("is-open"); toggleBtn.setAttribute("aria-expanded","false"); }

    toggleBtn.addEventListener("click", function(e){
      e.preventDefault();
      if(item.classList.contains("is-open")){ close(); } else { open(); }
    });
    item.addEventListener("mouseenter", function(){ if(window.innerWidth >= 1080) open(); });
    item.addEventListener("mouseleave", function(){ if(window.innerWidth >= 1080) close(); });
    document.addEventListener("click", function(e){
      if(!item.contains(e.target)) close();
    });
    document.addEventListener("keydown", function(e){
      if(e.key === "Escape") close();
    });
  });

  /* ---------------- Mobile menu ---------------- */
  var navToggle = document.querySelector(".nav-toggle");
  var mobileMenu = document.querySelector(".mobile-menu");
  var mobileClose = document.querySelector(".mobile-menu-close");

  function openMobile(){
    if(!mobileMenu) return;
    mobileMenu.classList.add("is-open");
    navToggle.setAttribute("aria-expanded","true");
    document.body.classList.add("menu-open");
  }
  function closeMobile(){
    if(!mobileMenu) return;
    mobileMenu.classList.remove("is-open");
    navToggle.setAttribute("aria-expanded","false");
    document.body.classList.remove("menu-open");
  }
  if(navToggle){
    navToggle.addEventListener("click", function(){
      if(mobileMenu.classList.contains("is-open")) closeMobile(); else openMobile();
    });
  }
  if(mobileClose){ mobileClose.addEventListener("click", closeMobile); }
  document.querySelectorAll(".mobile-menu a").forEach(function(a){
    a.addEventListener("click", closeMobile);
  });
  document.addEventListener("keydown", function(e){
    if(e.key === "Escape") closeMobile();
  });

  /* ---------------- Scroll reveal (staggered, sequential) ---------------- */
  var revealEls = document.querySelectorAll(".reveal");
  if("IntersectionObserver" in window && !reduceMotion){
    var groups = {};
    revealEls.forEach(function(el){
      var group = el.closest("section") || document.body;
      var key = group.dataset.revealId || (group.dataset.revealId = "g" + Math.random().toString(36).slice(2));
      groups[key] = groups[key] || [];
      groups[key].push(el);
    });
    Object.keys(groups).forEach(function(key){
      groups[key].forEach(function(el, i){
        el.style.transitionDelay = Math.min(i * 90, 450) + "ms";
      });
    });
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if(entry.isIntersecting){
          entry.target.classList.add("is-visible");
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.14, rootMargin: "0px 0px -6% 0px" });
    revealEls.forEach(function(el){ io.observe(el); });
  } else {
    revealEls.forEach(function(el){ el.classList.add("is-visible"); });
  }

  /* ---------------- Timeline sequential reveal ---------------- */
  var timelineItems = document.querySelectorAll(".timeline-item");
  if(timelineItems.length){
    if("IntersectionObserver" in window && !reduceMotion){
      var tio = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if(entry.isIntersecting){
            entry.target.classList.add("is-visible");
            tio.unobserve(entry.target);
          }
        });
      }, { threshold: 0.35 });
      timelineItems.forEach(function(el){ tio.observe(el); });
    } else {
      timelineItems.forEach(function(el){ el.classList.add("is-visible"); });
    }
  }

  /* ---------------- Hero pointer tilt (subtle, gated) ---------------- */
  var tiltEls = document.querySelectorAll("[data-tilt]");
  var pointerFine = window.matchMedia("(pointer: fine)").matches;
  if(tiltEls.length && pointerFine && !reduceMotion){
    tiltEls.forEach(function(el){
      var parent = el.closest(".hero-floats") || el.parentElement;
      parent.addEventListener("mousemove", function(e){
        var rect = parent.getBoundingClientRect();
        var relX = (e.clientX - rect.left) / rect.width - 0.5;
        var relY = (e.clientY - rect.top) / rect.height - 0.5;
        var depth = parseFloat(el.dataset.tilt) || 1;
        var tx = relX * 10 * depth;
        var ty = relY * 8 * depth;
        el.style.transform = "translate(" + tx.toFixed(2) + "px," + ty.toFixed(2) + "px)";
      });
      parent.addEventListener("mouseleave", function(){
        el.style.transform = "translate(0,0)";
      });
    });
  }

  /* ---------------- Animated stat counters ---------------- */
  var counters = document.querySelectorAll("[data-count-to]");
  if(counters.length){
    function animateCount(el){
      var target = parseFloat(el.dataset.countTo);
      var suffix = el.dataset.suffix || "";
      var duration = reduceMotion ? 1 : 1400;
      var start = null;
      function step(ts){
        if(start === null) start = ts;
        var progress = Math.min((ts - start) / duration, 1);
        var eased = 1 - Math.pow(1 - progress, 3);
        var val = target * eased;
        el.textContent = (Number.isInteger(target) ? Math.round(val) : val.toFixed(1)) + suffix;
        if(progress < 1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }
    if("IntersectionObserver" in window){
      var cio = new IntersectionObserver(function(entries){
        entries.forEach(function(entry){
          if(entry.isIntersecting){
            animateCount(entry.target);
            cio.unobserve(entry.target);
          }
        });
      }, { threshold: 0.6 });
      counters.forEach(function(el){ cio.observe(el); });
    } else {
      counters.forEach(function(el){ el.textContent = el.dataset.countTo + (el.dataset.suffix || ""); });
    }
  }

  /* ---------------- Hand-rolled carousel component ---------------- */
  function initCarousel(root){
    var track = root.querySelector(".carousel-track");
    var slides = Array.prototype.slice.call(root.querySelectorAll(".carousel-slide"));
    var prevBtn = root.querySelector(".car-prev");
    var nextBtn = root.querySelector(".car-next");
    var dotsWrap = root.querySelector(".car-dots");
    var progressBar = root.querySelector(".car-progress-bar");
    if(!track || !slides.length) return;

    var index = 0;

    if(dotsWrap){
      dotsWrap.innerHTML = "";
      slides.forEach(function(_, i){
        var dot = document.createElement("button");
        dot.className = "car-dot";
        dot.type = "button";
        dot.setAttribute("aria-label", "Go to slide " + (i + 1));
        dot.addEventListener("click", function(){ goTo(i); });
        dotsWrap.appendChild(dot);
      });
    }
    var dots = dotsWrap ? Array.prototype.slice.call(dotsWrap.children) : [];

    function update(){
      dots.forEach(function(d, i){ d.classList.toggle("is-active", i === index); });
      if(progressBar){
        var pct = slides.length > 1 ? (index / (slides.length - 1)) * 100 : 100;
        progressBar.style.width = pct + "%";
      }
      if(prevBtn) prevBtn.disabled = index === 0;
      if(nextBtn) nextBtn.disabled = index === slides.length - 1;
    }

    function goTo(i){
      index = Math.max(0, Math.min(i, slides.length - 1));
      var slide = slides[index];
      track.scrollTo({ left: slide.offsetLeft - track.offsetLeft, behavior: reduceMotion ? "auto" : "smooth" });
      update();
    }

    if(prevBtn) prevBtn.addEventListener("click", function(){ goTo(index - 1); });
    if(nextBtn) nextBtn.addEventListener("click", function(){ goTo(index + 1); });

    root.setAttribute("tabindex", "0");
    root.addEventListener("keydown", function(e){
      if(e.key === "ArrowLeft"){ goTo(index - 1); }
      if(e.key === "ArrowRight"){ goTo(index + 1); }
    });

    /* pointer / touch swipe */
    var isDown = false, startX = 0, startScroll = 0;
    track.addEventListener("pointerdown", function(e){
      isDown = true;
      startX = e.clientX;
      startScroll = track.scrollLeft;
      track.classList.add("dragging");
      track.setPointerCapture(e.pointerId);
    });
    track.addEventListener("pointermove", function(e){
      if(!isDown) return;
      var dx = e.clientX - startX;
      track.scrollLeft = startScroll - dx;
    });
    function endDrag(){
      if(!isDown) return;
      isDown = false;
      track.classList.remove("dragging");
      var slideWidth = slides[0].getBoundingClientRect().width + 20;
      var nearest = Math.round(track.scrollLeft / slideWidth);
      goTo(nearest);
    }
    track.addEventListener("pointerup", endDrag);
    track.addEventListener("pointercancel", endDrag);
    track.addEventListener("pointerleave", function(){ if(isDown) endDrag(); });

    /* sync index on manual scroll (wheel trackpads etc.) */
    var scrollTimeout;
    track.addEventListener("scroll", function(){
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(function(){
        var slideWidth = slides[0].getBoundingClientRect().width + 20;
        var nearest = Math.round(track.scrollLeft / slideWidth);
        index = Math.max(0, Math.min(nearest, slides.length - 1));
        update();
      }, 120);
    }, { passive:true });

    update();
  }
  document.querySelectorAll(".carousel").forEach(initCarousel);

  /* ---------------- Portfolio filter + expand ---------------- */
  var filterBtns = document.querySelectorAll(".filter-btn");
  var caseCards = document.querySelectorAll(".case-card");
  if(filterBtns.length){
    filterBtns.forEach(function(btn){
      btn.addEventListener("click", function(){
        filterBtns.forEach(function(b){ b.classList.remove("is-active"); b.setAttribute("aria-pressed","false"); });
        btn.classList.add("is-active");
        btn.setAttribute("aria-pressed","true");
        var filter = btn.dataset.filter;
        caseCards.forEach(function(card){
          var match = filter === "all" || card.dataset.industry === filter;
          card.style.display = match ? "" : "none";
        });
      });
    });
  }
  caseCards.forEach(function(card){
    var toggle = card.querySelector(".case-toggle");
    if(!toggle) return;
    toggle.addEventListener("click", function(){
      var isOpen = card.classList.contains("is-open");
      caseCards.forEach(function(c){ c.classList.remove("is-open"); });
      if(!isOpen){ card.classList.add("is-open"); }
    });
  });

  /* ---------------- FAQ accordion ---------------- */
  document.querySelectorAll(".faq-item").forEach(function(item){
    var btn = item.querySelector(".faq-q");
    var panel = item.querySelector(".faq-a");
    if(!btn || !panel) return;
    btn.addEventListener("click", function(){
      var open = item.getAttribute("data-open") === "true";
      if(open){
        item.setAttribute("data-open","false");
        btn.setAttribute("aria-expanded","false");
        panel.style.maxHeight = "0px";
      } else {
        item.setAttribute("data-open","true");
        btn.setAttribute("aria-expanded","true");
        panel.style.maxHeight = panel.scrollHeight + "px";
      }
    });
  });

  /* ---------------- Contact form validation ---------------- */
  var form = document.getElementById("contact-form");
  if(form){
    var fields = form.querySelectorAll("[data-validate]");
    function showError(field, message){
      var wrap = field.closest(".field");
      var errorEl = wrap.querySelector(".field-error");
      wrap.classList.toggle("has-error", !!message);
      if(errorEl) errorEl.textContent = message || "";
    }
    function validateField(field){
      var value = field.value.trim();
      if(field.hasAttribute("required") && !value){
        showError(field, "This field is required.");
        return false;
      }
      if(field.type === "email" && value){
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if(!re.test(value)){ showError(field, "Enter a valid email address."); return false; }
      }
      if(field.type === "tel" && value){
        var reTel = /^[0-9+\-()\s]{7,}$/;
        if(!reTel.test(value)){ showError(field, "Enter a valid phone number."); return false; }
      }
      showError(field, "");
      return true;
    }
    fields.forEach(function(field){
      field.addEventListener("blur", function(){ validateField(field); });
    });
    form.addEventListener("submit", function(e){
      e.preventDefault();
      var valid = true;
      fields.forEach(function(field){ if(!validateField(field)) valid = false; });
      var statusEl = form.querySelector(".form-status");
      if(!valid){
        if(statusEl) statusEl.textContent = "Please correct the highlighted fields.";
        var firstError = form.querySelector(".has-error input, .has-error select, .has-error textarea");
        if(firstError) firstError.focus();
        return;
      }
      if(statusEl) statusEl.textContent = "";
      form.style.display = "none";
      var success = document.querySelector(".success-panel");
      if(success){
        success.classList.add("is-visible");
        success.setAttribute("tabindex","-1");
        success.focus();
      }
    });
  }

  /* ---------------- Active nav on scroll not needed (per-page aria-current is static) ---------------- */

})();
