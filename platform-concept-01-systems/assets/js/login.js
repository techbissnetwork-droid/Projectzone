/* ==========================================================================
   TECHBISS PLATFORM — login.html page script
   Role selector (copy-only), password visibility toggle, forgot-password
   inline panel swap, and field validation + a loading-state submit that
   resolves to the client dashboard (there is no backend — this is a frontend
   concept, so a syntactically valid email/password always "succeeds").
   ========================================================================== */
(function(){
  "use strict";

  /* ---------------- Role tabs (heading/copy only) ---------------- */
  var roleTabs = document.querySelectorAll('[data-role]');
  function setRole(role){
    roleTabs.forEach(function(tab){
      var active = tab.getAttribute('data-role') === role;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-role-for]').forEach(function(el){
      el.hidden = el.getAttribute('data-role-for') !== role;
    });
  }
  roleTabs.forEach(function(tab){
    tab.addEventListener('click', function(){ setRole(tab.getAttribute('data-role')); });
  });
  var params = new URLSearchParams(location.search);
  var requestedRole = params.get('role');
  if (requestedRole && document.querySelector('[data-role="' + requestedRole + '"]')) setRole(requestedRole);

  /* ---------------- Password show/hide ---------------- */
  var toggleBtn = document.querySelector('[data-toggle-password]');
  var passwordInput = document.getElementById('loginPassword');
  if (toggleBtn && passwordInput) {
    toggleBtn.addEventListener('click', function(){
      var show = passwordInput.type === 'password';
      passwordInput.type = show ? 'text' : 'password';
      toggleBtn.setAttribute('aria-pressed', show ? 'true' : 'false');
      toggleBtn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      toggleBtn.querySelector('[data-eye-open]').hidden = show;
      toggleBtn.querySelector('[data-eye-closed]').hidden = !show;
    });
  }

  /* ---------------- Forgot password panel swap ---------------- */
  var loginForm = document.querySelector('[data-login-form]');
  var forgotForm = document.querySelector('[data-forgot-form]');
  var showForgotBtn = document.querySelector('[data-show-forgot]');
  var backToLoginBtn = document.querySelector('[data-back-to-login]');
  if (showForgotBtn) showForgotBtn.addEventListener('click', function(){
    loginForm.hidden = true;
    forgotForm.hidden = false;
    var f = document.getElementById('forgotEmail');
    if (f) f.focus();
  });
  if (backToLoginBtn) backToLoginBtn.addEventListener('click', function(){
    forgotForm.hidden = true;
    loginForm.hidden = false;
    document.querySelector('[data-reset-success]').hidden = true;
  });
  var sendResetBtn = document.querySelector('[data-send-reset]');
  if (sendResetBtn) sendResetBtn.addEventListener('click', function(){
    var field = document.querySelector('[data-field="forgotEmail"]');
    var input = document.getElementById('forgotEmail');
    var ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim());
    field.classList.toggle('has-error', !ok);
    field.classList.toggle('has-success', ok);
    var msg = field.querySelector('[data-field-msg]');
    msg.classList.toggle('is-error', !ok);
    msg.textContent = ok ? '' : 'Enter a valid email address.';
    if (!ok) return;
    sendResetBtn.classList.add('btn-loading');
    sendResetBtn.disabled = true;
    setTimeout(function(){
      sendResetBtn.classList.remove('btn-loading');
      sendResetBtn.disabled = false;
      document.querySelector('[data-reset-success]').hidden = false;
    }, 800);
  });

  /* ---------------- Sign in ---------------- */
  var emailInput = document.getElementById('loginEmail');
  var submitBtn = document.querySelector('[data-login-submit]');

  function setFieldState(name, ok, msg){
    var field = document.querySelector('[data-field="' + name + '"]');
    if (!field) return;
    field.classList.remove('has-error', 'has-success');
    if (ok === true) field.classList.add('has-success');
    if (ok === false) field.classList.add('has-error');
    var msgEl = field.querySelector('[data-field-msg]');
    if (msgEl) { msgEl.classList.toggle('is-error', ok === false); msgEl.textContent = ok === false ? msg : ''; }
  }

  if (loginForm) {
    loginForm.addEventListener('submit', function(e){
      e.preventDefault();
      var emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim());
      var passwordOk = passwordInput.value.length >= 6;
      setFieldState('email', emailOk, 'Enter a valid email address.');
      setFieldState('password', passwordOk, 'Password must be at least 6 characters.');
      if (!emailOk) { emailInput.focus(); return; }
      if (!passwordOk) { passwordInput.focus(); return; }

      submitBtn.classList.add('btn-loading');
      submitBtn.disabled = true;
      setTimeout(function(){
        submitBtn.classList.remove('btn-loading');
        submitBtn.textContent = '✓ Signed in';
        setTimeout(function(){ location.href = 'dashboard.html'; }, 450);
      }, 1100);
    });
  }

})();
