/* ==========================================================================
   TECHBISS PLATFORM — dashboard.html page script
   Sidebar tab switching (no page reload), hash deep-linking (dashboard.html#billing
   etc., used by footer/installer links), invoice "download" preview note, and
   the mock new-support-ticket form.
   ========================================================================== */
(function(){
  "use strict";

  var links = Array.prototype.slice.call(document.querySelectorAll('[data-panel-target]'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('[data-panel]'));
  var title = document.querySelector('[data-panel-title]');
  var validKeys = links.map(function(l){ return l.getAttribute('data-panel-target'); });

  function activate(key, updateHash){
    if (validKeys.indexOf(key) === -1) key = 'overview';
    panels.forEach(function(p){ p.hidden = p.id !== 'panel-' + key; });
    links.forEach(function(l){ l.classList.toggle('is-active', l.getAttribute('data-panel-target') === key); });
    var activeLink = links.filter(function(l){ return l.getAttribute('data-panel-target') === key; })[0];
    if (title && activeLink) title.textContent = activeLink.getAttribute('data-panel-label') || activeLink.textContent.trim();
    if (updateHash !== false) history.replaceState(null, '', '#' + key);
  }

  links.forEach(function(link){
    link.addEventListener('click', function(){ activate(link.getAttribute('data-panel-target')); });
  });

  var initialKey = (location.hash || '').replace('#', '');
  activate(initialKey || 'overview', false);

  /* ---------------- Billing: "download" preview note ---------------- */
  document.querySelectorAll('[data-action="download-invoice"]').forEach(function(btn){
    var original = btn.textContent;
    btn.addEventListener('click', function(){
      if (btn.getAttribute('data-busy') === '1') return;
      btn.setAttribute('data-busy', '1');
      btn.textContent = 'Preview only';
      setTimeout(function(){ btn.textContent = original; btn.removeAttribute('data-busy'); }, 1600);
    });
  });

  /* ---------------- Support: new ticket mock form ---------------- */
  var toggleBtn = document.querySelector('[data-action="toggle-ticket-form"]');
  var form = document.querySelector('[data-ticket-form]');
  var successMsg = document.querySelector('[data-ticket-success]');
  var ticketList = document.querySelector('[data-ticket-list]');
  var ticketCounter = 4022;

  if (toggleBtn && form) {
    toggleBtn.addEventListener('click', function(){
      form.hidden = !form.hidden;
      if (!form.hidden) {
        successMsg.hidden = true;
        var subjectField = document.getElementById('ticketSubject');
        if (subjectField) subjectField.focus();
      }
    });
  }
  if (form) {
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var subject = document.getElementById('ticketSubject').value.trim();
      if (!subject) { document.getElementById('ticketSubject').focus(); return; }
      var today = new Date();
      var dateStr = today.toISOString().slice(0, 10);
      var li = document.createElement('li');
      li.className = 'ticket-row ticket-row-new';
      li.innerHTML =
        '<span class="mono ticket-id">#TB-' + (ticketCounter++) + '</span>' +
        '<span class="ticket-subject">' + subject.replace(/</g, '&lt;') + '</span>' +
        '<span class="badge badge-info">Open</span>' +
        '<span class="mono ticket-updated">' + dateStr + '</span>';
      ticketList.insertBefore(li, ticketList.firstChild);
      successMsg.hidden = false;
      form.reset();
    });
  }

})();
