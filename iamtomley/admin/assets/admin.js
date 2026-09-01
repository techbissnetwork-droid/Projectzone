/* ============================================================================
   Admin panel behaviour.

   The only interactive piece is the "Detect" button: it takes whatever address
   is in a URL field, asks the server to find that site's logo or preview image,
   and drops the saved path into the form with a live preview. Everything else
   on these pages is a plain form post.
   ========================================================================== */
(function () {
  'use strict';

  var cfg = window.ADMIN || {};
  var pick = function (sel) { return sel ? document.querySelector(sel) : null; };

  /** Show a short status line under the button. */
  function say(el, text, tone) {
    if (!el) return;
    el.textContent = text;
    el.className = 'detect-note' + (tone ? ' ' + tone : '');
  }

  /** Point a preview <img> at a path, or hide it when there is nothing to show. */
  function preview(el, src) {
    if (!el) return;
    if (src) {
      el.src = src;
      el.hidden = false;
    } else {
      el.removeAttribute('src');
      el.hidden = true;
    }
  }

  /* ── Detect an image from a URL ─────────────────────────────────────────── */
  document.querySelectorAll('[data-detect]').forEach(function (btn) {
    var urlEl  = pick(btn.dataset.detect);
    var pathEl = pick(btn.dataset.detectTarget);
    var prevEl = pick(btn.dataset.detectPreview);
    var nameEl = pick(btn.dataset.detectPrefix);
    var noteEl = pick(btn.dataset.detectNote);

    btn.addEventListener('click', function () {
      if (!urlEl || !pathEl) return;
      var url = (urlEl.value || '').trim();
      if (!url || url === '#') {
        say(noteEl, 'Enter the site address first, then press Detect.', 'warn');
        urlEl.focus();
        return;
      }

      var original = btn.innerHTML;
      btn.disabled = true;
      btn.textContent = 'Looking…';
      say(noteEl, 'Reading ' + url + ' — this can take a few seconds.', '');

      var body = new URLSearchParams();
      body.set('csrf', cfg.csrf || '');
      body.set('url', url);
      body.set('prefix', nameEl ? (nameEl.value || 'site') : 'site');

      fetch(cfg.detectUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'The server sent back an unexpected response.' }; }); })
        .then(function (data) {
          if (data && data.ok) {
            pathEl.value = data.path;
            preview(prevEl, data.src);
            say(noteEl, 'Found the ' + (data.kind || 'image').toLowerCase() + ' — save the form to keep it.', 'ok');
          } else {
            say(noteEl, (data && data.error) || 'Nothing could be found at that address.', 'warn');
          }
        })
        .catch(function () {
          say(noteEl, 'Could not reach the server. Check your connection and try again.', 'warn');
        })
        .then(function () {
          btn.disabled = false;
          btn.innerHTML = original;
        });
    });
  });

  /* ── Clear the chosen image ─────────────────────────────────────────────── */
  document.querySelectorAll('[data-clear-image]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var pathEl = pick(btn.dataset.clearImage);
      var prevEl = pick(btn.dataset.clearPreview);
      var noteEl = pick(btn.dataset.clearNote);
      if (pathEl) pathEl.value = '';
      preview(prevEl, '');
      say(noteEl, 'Image cleared — save the form to apply it.', '');
    });
  });

  /* ── Typing a path by hand updates the preview too ──────────────────────── */
  document.querySelectorAll('[data-preview-for]').forEach(function (img) {
    var pathEl = pick(img.dataset.previewFor);
    if (!pathEl) return;
    pathEl.addEventListener('input', function () {
      var v = (pathEl.value || '').trim();
      preview(img, v ? (v.charAt(0) === '/' ? (cfg.base || '') + v : v) : '');
    });
  });

  /* ── Game form: only show the fields the chosen source needs ────────────── */
  document.querySelectorAll('[data-source-select]').forEach(function (sel) {
    var sync = function () {
      document.querySelectorAll('[data-source-only]').forEach(function (el) {
        var wanted = el.dataset.sourceOnly.split(/\s+/);
        el.hidden = wanted.indexOf(sel.value) === -1;
      });
    };
    sel.addEventListener('change', sync);
    sync();
  });
})();
