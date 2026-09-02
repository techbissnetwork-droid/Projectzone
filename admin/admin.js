// THE SUBJECT-ROW DROPDOWNS, WHICH NEVER GOT THE ACCOUNT CHIP'S TREATMENT.
//
// Same fault the account chip had before its own outside-click handler (see
// admin_footer() in _admin.php): a native <details> does not close itself
// when the click lands elsewhere on the page. There are four of these in the
// subject row rather than one, so unlike the account chip they also need to
// close each other - opening "Money" while "Coins & market" is still open
// should swap one for the other, not stack both over the page.
(function () {
  var drops = [].slice.call(document.querySelectorAll('.cat-drop'));
  if (!drops.length) { return; }
  drops.forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (!d.open) { return; }
      drops.forEach(function (o) { if (o !== d && o.open) { o.open = false; } });
    });
  });
  document.addEventListener('click', function (e) {
    drops.forEach(function (d) {
      if (d.open && !d.contains(e.target)) { d.open = false; }
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') { return; }
    drops.forEach(function (d) { if (d.open) { d.open = false; } });
  });
})();
