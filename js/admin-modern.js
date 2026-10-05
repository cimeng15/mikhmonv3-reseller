(function () {
  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    var body = document.body;
    if (!body.classList.contains('mk-admin-body')) return;

    var open = document.getElementById('openNav');
    var close = document.getElementById('closeNav');
    var side = document.getElementById('sidenav');
    if (!side) return;

    /* Create overlay for mobile drawer */
    var overlay = document.createElement('div');
    overlay.className = 'mk-admin-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    document.body.appendChild(overlay);

    function setMenu(show) {
      body.classList.toggle('mk-admin-nav-open', show);
      if (open) open.setAttribute('aria-expanded', show ? 'true' : 'false');
      if (close) close.setAttribute('aria-expanded', show ? 'true' : 'false');
    }

    if (open) {
      open.setAttribute('aria-label', 'Buka menu');
      open.setAttribute('aria-expanded', 'false');
      open.addEventListener('click', function (event) {
        if (window.innerWidth <= 900) {
          event.preventDefault();
          event.stopImmediatePropagation();
          setMenu(true);
        }
      }, true);
    }
    if (close) {
      close.setAttribute('aria-label', 'Tutup menu');
      close.addEventListener('click', function (event) {
        if (window.innerWidth <= 900) {
          event.preventDefault();
          event.stopImmediatePropagation();
          setMenu(false);
        }
      }, true);
    }

    overlay.addEventListener('click', function () { setMenu(false); });
    side.addEventListener('click', function (event) {
      if (window.innerWidth <= 900 && event.target.closest('a')) setMenu(false);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') setMenu(false);
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 900) setMenu(false);
    });
  });
})();
