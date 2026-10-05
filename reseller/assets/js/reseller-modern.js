(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var app = document.getElementById('resellerApp');
    var trigger = document.getElementById('resellerMenuTrigger');
    var overlay = document.getElementById('resellerOverlay');

    function closeMenu() {
      if (!app) return;
      app.classList.remove('menu-open');
      if (trigger) trigger.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }

    function toggleMenu() {
      if (!app) return;
      var willOpen = !app.classList.contains('menu-open');
      app.classList.toggle('menu-open', willOpen);
      if (trigger) trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      document.body.style.overflow = willOpen ? 'hidden' : '';
    }

    if (trigger) trigger.addEventListener('click', toggleMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);
    document.querySelectorAll('.rs-sidebar a').forEach(function (link) {
      link.addEventListener('click', closeMenu);
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 991) closeMenu();
    });
  });
})();
