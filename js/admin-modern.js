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
    var main = document.getElementById('main');
    if (!side) return;

    /*
     * The legacy mikhmon-ui.*.min.js sets inline styles on #sidenav, #main,
     * .menu, .dropdown-btn, .dropdown-container (width:0, display:none, etc).
     * We must strip those inline styles so our CSS rules take effect.
     */
    function cleanInlineStyles() {
      side.removeAttribute('style');
      if (main) main.removeAttribute('style');
      var items = side.querySelectorAll('.menu, .dropdown-btn, .dropdown-container, .mk-sidebar-group-label');
      for (var i = 0; i < items.length; i++) {
        items[i].style.display = '';
      }
      if (open) open.style.display = '';
      if (close) close.style.display = '';
    }

    /* Run immediately to undo what legacy script already did */
    cleanInlineStyles();

    /* Run again after short delays (legacy script may fire after us) */
    setTimeout(cleanInlineStyles, 50);
    setTimeout(cleanInlineStyles, 200);
    setTimeout(cleanInlineStyles, 600);

    /* Watch for inline style mutations from legacy script and clean them */
    var observing = true;
    var observer = new MutationObserver(function (mutations) {
      if (!observing) return;
      for (var m = 0; m < mutations.length; m++) {
        if (mutations[m].attributeName === 'style') {
          cleanInlineStyles();
          break;
        }
      }
    });
    observer.observe(side, { attributes: true, attributeFilter: ['style'] });
    if (main) observer.observe(main, { attributes: true, attributeFilter: ['style'] });

    /* Create overlay for mobile drawer */
    var overlay = document.createElement('div');
    overlay.className = 'mk-admin-overlay';
    overlay.setAttribute('aria-hidden', 'true');
    body.appendChild(overlay);

    function isMobile() { return window.innerWidth <= 900; }

    function openMenu() {
      if (!isMobile()) return;
      observing = false;
      side.style.transform = 'translateX(0)';
      body.classList.add('mk-admin-nav-open');
      if (open) open.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
      side.style.transform = '';
      body.classList.remove('mk-admin-nav-open');
      if (open) open.setAttribute('aria-expanded', 'false');
      observing = true;
    }

    if (open) {
      open.setAttribute('aria-label', 'Buka menu');
      open.setAttribute('aria-expanded', 'false');
      open.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        openMenu();
      }, true);
    }
    if (close) {
      close.setAttribute('aria-label', 'Tutup menu');
      close.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        closeMenu();
      }, true);
    }

    overlay.addEventListener('click', closeMenu);
    side.addEventListener('click', function (e) {
      if (isMobile() && e.target.closest('a')) closeMenu();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu();
    });
    window.addEventListener('resize', function () {
      if (!isMobile()) closeMenu();
      cleanInlineStyles();
      setTimeout(cleanInlineStyles, 10);
    });
  });
})();
