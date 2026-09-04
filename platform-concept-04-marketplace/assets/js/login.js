/* ==========================================================================
   TECHBISS Auth — login.html only
   Role selector (Client/Staff/Admin), real inline validation with
   focus/error/success states, a password-visibility toggle, and a submit
   button with a loading micro-interaction that hands off to the dashboard.
   ========================================================================== */
(function(){
  "use strict";
  var form = document.getElementById('login-form');
  if (!form) return;

  var TB = window.TB_PRODUCTS;
  var miniCard = document.getElementById('auth-mini-card');
  if (miniCard && TB) {
    var product = TB.byId('ember-table');
    if (product) miniCard.innerHTML = TB.renderCard(product, { view: 'compact' });
  }

  /* ---------------- Role selector ---------------- */
  var ROLE_COPY = {
    client: { title: 'Every part of your business, online — in one place.', sub: 'Projects, websites, domains, billing and marketplace purchases, all from a single dashboard.' },
    staff: { title: 'Built for the team behind every launch.', sub: 'Manage client projects, installer runs and support tickets from one workspace.' },
    admin: { title: 'Full visibility across every TECHBISS account.', sub: 'Platform-wide oversight of clients, marketplace products and infrastructure.' }
  };
  var roleButtons = document.querySelectorAll('.role-btn');
  var visualTitle = document.getElementById('auth-visual-title');
  var visualSub = document.getElementById('auth-visual-sub');
  var demoRoleLabel = document.getElementById('demo-role-label');

  function setRole(role){
    roleButtons.forEach(function(b){
      var active = b.getAttribute('data-role') === role;
      b.classList.toggle('is-active', active);
      b.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    var copy = ROLE_COPY[role] || ROLE_COPY.client;
    if (visualTitle) visualTitle.textContent = copy.title;
    if (visualSub) visualSub.textContent = copy.sub;
    if (demoRoleLabel) demoRoleLabel.textContent = role.charAt(0).toUpperCase() + role.slice(1);
  }
  roleButtons.forEach(function(b){
    b.addEventListener('click', function(){ setRole(b.getAttribute('data-role')); });
  });

  /* ---------------- Password visibility ---------------- */
  var pwToggle = document.getElementById('password-toggle');
  var pwInput = document.getElementById('login-password');
  if (pwToggle && pwInput) {
    pwToggle.addEventListener('click', function(){
      var show = pwInput.type === 'password';
      pwInput.type = show ? 'text' : 'password';
      pwToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      pwToggle.classList.toggle('is-active', show);
    });
  }

  /* ---------------- Validation ---------------- */
  var emailInput = document.getElementById('login-email');
  var emailField = document.getElementById('field-email');
  var emailHint = document.getElementById('hint-email');
  var passwordField = document.getElementById('field-password');
  var passwordHint = document.getElementById('hint-password');
  var errorBanner = document.getElementById('auth-error');
  var submitBtn = document.getElementById('login-submit');
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function validateEmail(show){
    var valid = EMAIL_RE.test(emailInput.value.trim());
    if (show) {
      emailField.classList.toggle('is-error', !valid);
      emailField.classList.toggle('is-success', valid);
      emailHint.textContent = valid ? '' : 'Enter a valid email address.';
    }
    return valid;
  }
  function validatePassword(show){
    var valid = pwInput.value.length >= 6;
    if (show) {
      passwordField.classList.toggle('is-error', !valid);
      passwordField.classList.toggle('is-success', valid);
      passwordHint.textContent = valid ? '' : 'Password must be at least 6 characters.';
    }
    return valid;
  }
  emailInput.addEventListener('blur', function(){ validateEmail(true); });
  pwInput.addEventListener('blur', function(){ validatePassword(true); });
  emailInput.addEventListener('input', function(){ if (emailField.classList.contains('is-error')) validateEmail(true); });
  pwInput.addEventListener('input', function(){ if (passwordField.classList.contains('is-error')) validatePassword(true); });

  form.addEventListener('submit', function(e){
    e.preventDefault();
    var eValid = validateEmail(true);
    var pValid = validatePassword(true);
    if (!eValid || !pValid) {
      errorBanner.hidden = false;
      (eValid ? pwInput : emailInput).focus();
      return;
    }
    errorBanner.hidden = true;
    submitBtn.classList.add('btn-loading');
    submitBtn.disabled = true;
    setTimeout(function(){
      window.location.href = 'dashboard.html';
    }, 900);
  });

  setRole('client');
})();
