/* ==========================================================================
   TECHBISS — Login: role tabs, validation, loading state, forgot-password
   ========================================================================== */
(function () {
  "use strict";
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var role = 'Client';
  var roleLabels = { Client: 'Client Dashboard', Staff: 'Staff Dashboard', Admin: 'Admin Dashboard' };

  var roleTabs = document.querySelectorAll('[data-role-tab]');
  roleTabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      role = btn.getAttribute('data-role-tab');
      roleTabs.forEach(function (b) { b.setAttribute('aria-selected', b === btn ? 'true' : 'false'); });
    });
  });

  var emailField = document.querySelector('[data-email-field]');
  var emailInput = document.querySelector('[data-email-input]');
  var passwordField = document.querySelector('[data-password-field]');
  var passwordInput = document.querySelector('[data-password-input]');
  var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function validateEmail(showState) {
    var v = (emailInput.value || '').trim();
    var ok = emailRe.test(v);
    if (showState) {
      emailField.classList.toggle('has-error', !ok);
      emailField.classList.toggle('has-success', ok);
      emailField.querySelector('.field-msg').textContent = ok ? '' : 'Enter a valid email address';
    }
    return ok;
  }
  function validatePassword(showState) {
    var v = passwordInput.value || '';
    var ok = v.length >= 6;
    if (showState) {
      passwordField.classList.toggle('has-error', !ok);
      passwordField.querySelector('.field-msg').textContent = ok ? '' : 'Password must be at least 6 characters';
    }
    return ok;
  }
  emailInput.addEventListener('blur', function () { validateEmail(true); });
  passwordInput.addEventListener('blur', function () { validatePassword(true); });
  emailInput.addEventListener('input', function () { if (emailField.classList.contains('has-error')) validateEmail(true); });
  passwordInput.addEventListener('input', function () { if (passwordField.classList.contains('has-error')) validatePassword(true); });

  var signInForm = document.querySelector('[data-signin-form]');
  var signInBtn = document.querySelector('[data-signin-btn]');
  var successEl = document.querySelector('[data-signin-success]');
  if (signInForm) {
    signInForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var okE = validateEmail(true), okP = validatePassword(true);
      if (!okE) { emailInput.focus(); return; }
      if (!okP) { passwordInput.focus(); return; }
      if (signInBtn.classList.contains('is-loading')) return;
      signInBtn.classList.add('is-loading');
      signInBtn.disabled = true;
      var label = signInBtn.querySelector('.btn-text');
      if (label) label.textContent = 'Signing In…';
      var delay = reduced ? 0 : 900;
      setTimeout(function () {
        if (label) label.textContent = 'Signed In';
        if (successEl) successEl.textContent = 'Redirecting to your ' + roleLabels[role] + '…';
        setTimeout(function () { window.location.href = 'dashboard.html'; }, reduced ? 0 : 500);
      }, delay);
    });
  }

  /* ---------- Forgot password ---------- */
  var signInPanel = document.querySelector('[data-panel="signin"]');
  var resetPanel = document.querySelector('[data-panel="reset"]');
  var forgotBtn = document.querySelector('[data-forgot-btn]');
  var backBtn = document.querySelector('[data-back-signin]');
  if (forgotBtn) forgotBtn.addEventListener('click', function () {
    signInPanel.hidden = true; resetPanel.hidden = false;
    var f = resetPanel.querySelector('input'); if (f) f.focus();
  });
  if (backBtn) backBtn.addEventListener('click', function () {
    resetPanel.hidden = true; signInPanel.hidden = false;
  });

  var resetForm = document.querySelector('[data-reset-form]');
  var resetEmailField = document.querySelector('[data-reset-email-field]');
  var resetEmailInput = document.querySelector('[data-reset-email-input]');
  var resetSuccess = document.querySelector('[data-reset-success]');
  if (resetForm) {
    resetForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = (resetEmailInput.value || '').trim();
      var ok = emailRe.test(v);
      resetEmailField.classList.toggle('has-error', !ok);
      resetEmailField.querySelector('.field-msg').textContent = ok ? '' : 'Enter a valid email address';
      if (!ok) { resetEmailInput.focus(); return; }
      if (resetSuccess) resetSuccess.textContent = 'If an account exists for that email, a reset link is on its way.';
    });
  }
})();
