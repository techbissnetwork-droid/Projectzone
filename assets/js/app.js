/* TECHBISS · admin + portal behaviour */
(function () {
  'use strict';
  var doc = document;

  /* Close the mobile sidebar after navigating. */
  var toggle = doc.getElementById('navToggle');
  if (toggle) {
    doc.querySelectorAll('.side a').forEach(function (a) {
      a.addEventListener('click', function () { toggle.checked = false; });
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { toggle.checked = false; }
    });
  }

  /* Ask before anything destructive. */
  doc.addEventListener('submit', function (e) {
    var form = e.target;
    var msg = form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });

  /* Suggest a slug from the title, until the slug is edited by hand. */
  var title = doc.querySelector('[data-slug-source]');
  var slug  = doc.querySelector('[data-slug-target]');
  if (title && slug && slug.value === '') {
    var touched = false;
    slug.addEventListener('input', function () { touched = true; });
    title.addEventListener('input', function () {
      if (touched) { return; }
      slug.value = title.value.toLowerCase().normalize('NFKD')
        .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 160);
    });
  }

  /* Live preview for a newly chosen image. */
  doc.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var img = doc.querySelector(input.getAttribute('data-preview'));
      if (!img || !input.files || !input.files[0]) { return; }
      var url = URL.createObjectURL(input.files[0]);
      img.src = url; img.hidden = false;
      img.addEventListener('load', function () { URL.revokeObjectURL(url); }, { once: true });
    });
  });

  /* Submit filter bars on change without a separate button. */
  doc.querySelectorAll('form[data-autosubmit] select').forEach(function (sel) {
    sel.addEventListener('change', function () { sel.form.submit(); });
  });

  /* Keep long ticket threads scrolled to the newest message. */
  var thread = doc.querySelector('[data-scroll-end]');
  if (thread) { thread.scrollTop = thread.scrollHeight; }
})();
