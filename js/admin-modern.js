(function () {
  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    var body = document.body;
    if (!body.classList.contains('mk-admin-body')) return;

    /* The legacy sidebar is intentionally disabled. Reseller navigation uses tabs. */
    body.classList.remove('mk-admin-nav-open');
    var open = document.getElementById('openNav');
    var close = document.getElementById('closeNav');
    if (open) open.setAttribute('aria-hidden', 'true');
    if (close) close.setAttribute('aria-hidden', 'true');
  });
})();
