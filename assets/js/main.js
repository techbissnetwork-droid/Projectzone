/* ============================================================
   TECHBISS — "PULSE" interactions
   Preloader, cursor, magnetics, tilt, ambient field, scroll fx.
   Every block is defensive: pages only opt into what they include.
   ============================================================ */
(function(){
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ---------- preloader ---------- */
  var loader = document.getElementById('loader');
  var pctEl = document.getElementById('loaderPct');
  var barEl = document.getElementById('loaderBar');
  var statusEl = document.getElementById('loaderStatus');

  function finishLoad(){
    if(loader) loader.classList.add('is-done');
    playIntro();
  }
  if(loader && !reduce){
    var p = 0;
    var iv = setInterval(function(){
      p += Math.random()*18 + 6;
      if(p >= 100){ p = 100; clearInterval(iv); if(statusEl) statusEl.textContent = 'READY'; setTimeout(finishLoad, 260); }
      if(pctEl) pctEl.textContent = Math.floor(p) + '%';
      if(barEl) barEl.style.width = p + '%';
    }, 130);
  } else if(loader){
    loader.style.display = 'none';
  }

  function playIntro(){
    var words = document.querySelectorAll('#headline .word span');
    words.forEach(function(w, i){
      w.style.transition = 'transform 760ms cubic-bezier(.16,1,.3,1) '+(i*80)+'ms, opacity 600ms ease '+(i*80)+'ms';
      requestAnimationFrame(function(){ w.style.transform = 'none'; w.style.opacity = '1'; });
    });
    ['leadText','heroCta'].forEach(function(id, i){
      var el = document.getElementById(id);
      if(!el) return;
      el.style.transition = 'opacity 620ms ease '+(500+i*100)+'ms, transform 620ms ease '+(500+i*100)+'ms';
      requestAnimationFrame(function(){ el.style.opacity = '1'; el.style.transform = 'none'; });
    });
  }
  if(!loader || reduce) playIntro();

  /* ---------- mobile nav ---------- */
  var burger = document.getElementById('burger');
  var navEl = document.getElementById('mastNav');
  if(burger && navEl){
    burger.addEventListener('click', function(){
      var open = navEl.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    navEl.addEventListener('click', function(e){
      if(e.target.tagName === 'A'){
        navEl.classList.remove('is-open');
        burger.setAttribute('aria-expanded','false');
      }
    });
  }

  /* ---------- mark current nav link ---------- */
  (function(){
    var here = location.pathname.split('/').pop() || 'index.html';
    document.querySelectorAll('#mastNav a').forEach(function(a){
      var href = (a.getAttribute('href')||'').split('#')[0].split('/').pop();
      if(href && href === here) a.classList.add('is-current');
    });
  })();

  /* ---------- custom cursor ---------- */
  if(canHover && !reduce){
    var cursor = document.getElementById('cursor');
    if(cursor){
      var cx = window.innerWidth/2, cy = window.innerHeight/2, lx = cx, ly = cy;
      window.addEventListener('pointermove', function(e){ cx = e.clientX; cy = e.clientY; });
      document.querySelectorAll('a, button, [data-tilt], summary, input, select, textarea').forEach(function(el){
        el.addEventListener('mouseenter', function(){ cursor.classList.add('is-active'); });
        el.addEventListener('mouseleave', function(){ cursor.classList.remove('is-active'); });
      });
      (function follow(){
        lx += (cx-lx)*0.18; ly += (cy-ly)*0.18;
        cursor.style.transform = 'translate('+lx+'px,'+ly+'px) translate(-50%,-50%)';
        requestAnimationFrame(follow);
      })();
    }
  }

  /* ---------- magnetic buttons ---------- */
  if(canHover && !reduce){
    document.querySelectorAll('.magnetic').forEach(function(el){
      el.addEventListener('mousemove', function(e){
        var r = el.getBoundingClientRect();
        var mx = e.clientX - (r.left + r.width/2);
        var my = e.clientY - (r.top + r.height/2);
        el.style.transform = 'translate('+(mx*0.28)+'px,'+(my*0.35)+'px)';
      });
      el.addEventListener('mouseleave', function(){
        el.style.transition = 'transform 380ms cubic-bezier(.16,1,.3,1)';
        el.style.transform = 'none';
        setTimeout(function(){ el.style.transition = ''; }, 380);
      });
    });
  }

  /* ---------- tilt cards ---------- */
  if(canHover && !reduce){
    document.querySelectorAll('[data-tilt]').forEach(function(card){
      var glow = card.querySelector('.card__glow');
      card.addEventListener('mousemove', function(e){
        var r = card.getBoundingClientRect();
        var px = (e.clientX - r.left)/r.width, py = (e.clientY - r.top)/r.height;
        var rx = (py-0.5) * -8, ry = (px-0.5) * 10;
        card.style.transform = 'perspective(700px) rotateX('+rx+'deg) rotateY('+ry+'deg) translateZ(0)';
        if(glow){ glow.style.left = (px*100)+'%'; glow.style.top = (py*100)+'%'; }
      });
      card.addEventListener('mouseleave', function(){
        card.style.transition = 'transform 420ms cubic-bezier(.16,1,.3,1)';
        card.style.transform = 'none';
        setTimeout(function(){ card.style.transition = ''; }, 420);
      });
    });
  }

  /* ---------- ambient field canvas ---------- */
  var canvas = document.getElementById('field');
  if(canvas){
    var ctx = canvas.getContext('2d');
    var W, H, dpr = Math.min(window.devicePixelRatio||1, 2);
    function size(){
      var r = canvas.getBoundingClientRect();
      W = r.width; H = r.height;
      canvas.width = W*dpr; canvas.height = H*dpr;
      ctx.setTransform(dpr,0,0,dpr,0,0);
    }
    size();
    window.addEventListener('resize', size);

    var blobs = [
      {x:.22,y:.35,r:.34,c:'38,217,201',dx:.00021,dy:.00015,ph:0},
      {x:.75,y:.28,r:.30,c:'200,255,77',dx:-.00017,dy:.00023,ph:2},
      {x:.55,y:.7,r:.36,c:'255,93,143',dx:.00013,dy:-.00019,ph:4}
    ];
    var pmx = 0.5, pmy = 0.5;
    window.addEventListener('pointermove', function(e){
      pmx = e.clientX / window.innerWidth; pmy = e.clientY / window.innerHeight;
    });

    var t0 = performance.now();
    function drawField(now){
      var t = now - t0;
      ctx.clearRect(0,0,W,H);
      ctx.globalCompositeOperation = 'lighter';
      blobs.forEach(function(b){
        var bx = (b.x + Math.sin(t*b.dx + b.ph)*0.06 + (pmx-0.5)*0.02) * W;
        var by = (b.y + Math.cos(t*b.dy + b.ph)*0.06 + (pmy-0.5)*0.02) * H;
        var r = b.r * Math.max(W,H);
        var g = ctx.createRadialGradient(bx,by,0,bx,by,r);
        g.addColorStop(0,'rgba('+b.c+',0.20)');
        g.addColorStop(1,'rgba('+b.c+',0)');
        ctx.fillStyle = g;
        ctx.beginPath(); ctx.arc(bx,by,r,0,Math.PI*2); ctx.fill();
      });
      ctx.globalCompositeOperation = 'source-over';
      if(!reduce) requestAnimationFrame(drawField);
    }
    requestAnimationFrame(drawField);
  }

  /* ---------- scroll skew statement ---------- */
  var stEl = document.getElementById('statementText');
  if(stEl && !reduce){
    var lastY = window.scrollY, vel = 0;
    window.addEventListener('scroll', function(){
      var y = window.scrollY; vel = y - lastY; lastY = y;
    }, {passive:true});
    (function skewFrame(){
      var target = Math.max(-6, Math.min(6, vel*0.6));
      vel *= 0.85;
      stEl.style.transform = 'skewY('+target.toFixed(2)+'deg)';
      requestAnimationFrame(skewFrame);
    })();
  }

  /* ---------- scroll progress bar ---------- */
  var progressFill = document.getElementById('progressFill');
  if(progressFill){
    function updateProgress(){
      var doc = document.documentElement;
      var max = doc.scrollHeight - doc.clientHeight;
      var pct = max > 0 ? (window.scrollY / max) * 100 : 0;
      progressFill.style.width = pct + '%';
    }
    window.addEventListener('scroll', updateProgress, {passive:true});
    window.addEventListener('resize', updateProgress);
    updateProgress();
  }

  /* ---------- process rail fill + active step ---------- */
  var railEl = document.getElementById('rail');
  var railFill = document.getElementById('railFill');
  var stepEls = document.querySelectorAll('[data-step]');
  if(railEl && railFill && stepEls.length){
    function updateRail(){
      var r = railEl.getBoundingClientRect();
      var vh = window.innerHeight;
      var start = vh * 0.75, end = vh * 0.3;
      var total = r.height;
      var covered = start - r.top;
      var frac = Math.max(0, Math.min(1, covered / (total + (start-end))));
      railFill.style.height = (frac*100) + '%';
      stepEls.forEach(function(step){
        var sr = step.getBoundingClientRect();
        step.classList.toggle('is-active', sr.top < vh*0.65 && sr.bottom > vh*0.2);
      });
    }
    window.addEventListener('scroll', updateRail, {passive:true});
    window.addEventListener('resize', updateRail);
    updateRail();
  }

  /* ---------- reveal on scroll ---------- */
  var revealEls = document.querySelectorAll('.reveal');
  if(revealEls.length){
    if(reduce || !('IntersectionObserver' in window)){
      revealEls.forEach(function(el){ el.classList.add('is-in'); });
    } else {
      var io = new IntersectionObserver(function(entries){
        entries.forEach(function(e){
          if(e.isIntersecting){ e.target.classList.add('is-in'); io.unobserve(e.target); }
        });
      }, {rootMargin:'0px 0px -12% 0px', threshold:0.08});
      revealEls.forEach(function(el){ io.observe(el); });
    }
  }

  /* ---------- count-up stats ---------- */
  var counters = document.querySelectorAll('[data-count]');
  if(counters.length){
    var runCount = function(el){
      var target = parseFloat(el.getAttribute('data-count'));
      var suffix = el.getAttribute('data-suffix') || '';
      var prefix = el.getAttribute('data-prefix') || '';
      if(reduce){ el.textContent = prefix + target + suffix; return; }
      var start = performance.now(), dur = 1200;
      (function tick(now){
        var k = Math.min(1, (now-start)/dur);
        var eased = 1 - Math.pow(1-k, 3);
        var val = target % 1 === 0 ? Math.round(target*eased) : (target*eased).toFixed(1);
        el.textContent = prefix + val + suffix;
        if(k < 1) requestAnimationFrame(tick);
      })(start);
    };
    if(!('IntersectionObserver' in window)){
      counters.forEach(runCount);
    } else {
      var cio = new IntersectionObserver(function(entries){
        entries.forEach(function(e){
          if(e.isIntersecting){ runCount(e.target); cio.unobserve(e.target); }
        });
      }, {threshold:0.4});
      counters.forEach(function(el){ cio.observe(el); });
    }
  }

  /* ---------- year stamp ---------- */
  document.querySelectorAll('[data-year]').forEach(function(el){
    el.textContent = new Date().getFullYear();
  });

  /* ---------- enquiry form ---------- */
  var form = document.getElementById('enquiryForm');
  if(form){
    var status = document.getElementById('formStatus');
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var data = new FormData(form);
      var name = (data.get('name')||'').toString().trim();
      var email = (data.get('email')||'').toString().trim();
      var message = (data.get('message')||'').toString().trim();
      if(!name || !email || !message){
        if(status){ status.textContent = 'Name, email and a short message are needed.'; status.className = 'form-status err'; }
        return;
      }
      if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
        if(status){ status.textContent = 'That email address does not look right.'; status.className = 'form-status err'; }
        return;
      }
      /* No backend on a static host: hand the enquiry to the mail client. */
      var lines = [
        'Name: ' + name,
        'Email: ' + email,
        'Business: ' + (data.get('company') || '—'),
        'Phone: ' + (data.get('phone') || '—'),
        'Needs: ' + (data.get('service') || '—'),
        'Budget: ' + (data.get('budget') || '—'),
        '',
        message
      ];
      var href = 'mailto:hello@techbiss.com'
        + '?subject=' + encodeURIComponent('New enquiry — ' + name)
        + '&body=' + encodeURIComponent(lines.join('\n'));
      window.location.href = href;
      if(status){
        status.textContent = 'Opening your email app — send the draft and we reply within one business day.';
        status.className = 'form-status ok';
      }
    });
  }
})();
