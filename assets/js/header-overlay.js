// Startseite: Der Kopf liegt durchsichtig über dem Foto und wird deckend, sobald man über das Foto hinaus
// scrollt oder das Handy-Menü öffnet (sonst stünde das Menü ohne Hintergrund auf dem Inhalt).
(function () {
    'use strict';
    var header = document.querySelector('.app-header[data-hero-overlay]');
    var hero = document.querySelector('.hero--home');
    if (!header || !hero) return;
    var menuOpen = false;

    function update() {
        var past = window.scrollY > hero.offsetHeight - header.offsetHeight - 8;
        header.classList.toggle('is-solid', past || menuOpen);
    }
    var menu = document.getElementById('mainNav');
    if (menu) {
        menu.addEventListener('show.bs.collapse', function () { menuOpen = true; update(); });
        menu.addEventListener('hidden.bs.collapse', function () { menuOpen = false; update(); });
    }
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
})();
