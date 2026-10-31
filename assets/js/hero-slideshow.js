// Hero-Diashow (Option «Fotos als Diashow wechseln» in «Verein & Design»).
// Zufällige Reihenfolge ohne Wiederholung pro Durchgang, Überblendung, zufälliger Zoom und Schwenk.
// Der Schwenk nutzt den ganzen Überhang des Fotos – bei breiten Fenstern (flacher Kopfbereich)
// wandert das Bild also weit nach oben/unten, damit z. B. Gesichter nicht dauernd abgeschnitten sind.
// Ohne JavaScript oder bei «Bewegung reduzieren» bleibt das erste Foto als Standbild (vom Server gesetzt).
(function () {
    'use strict';
    var box = document.querySelector('.hero-photo[data-slides]');
    if (!box) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var urls;
    try { urls = JSON.parse(box.getAttribute('data-slides')); } catch (e) { return; }
    if (!Array.isArray(urls) || urls.length < 2) return;

    var HOLD = 7000;              // ms voll sichtbar
    var FADE = 2000;              // ms Überblendung (muss zur CSS-Transition passen)
    var DUR = (HOLD + 2 * FADE) / 1000 + 1; // s Zoom/Schwenk – läuft über beide Überblendungen
    var queue = [urls[0]];        // erstes Foto = das Standbild vom Server, damit es nahtlos weitergeht
    var last = null;
    var current = null;           // {el, src, img}
    var timer = null;

    function rnd(a, b) { return a + Math.random() * (b - a); }

    function next() {
        if (!queue.length) {
            queue = urls.slice();
            for (var i = queue.length - 1; i > 0; i--) {
                var j = Math.floor(Math.random() * (i + 1)), t = queue[i]; queue[i] = queue[j]; queue[j] = t;
            }
            if (queue[0] === last) queue.push(queue.shift()); // nie zweimal dasselbe hintereinander
        }
        return (last = queue.shift());
    }

    function load(src) {
        return new Promise(function (resolve) {
            var img = new Image();
            img.onload = function () { resolve(img.naturalWidth > 0 ? img : null); };
            img.onerror = function () { resolve(null); };
            img.src = src;
        });
    }

    function show(src, img) {
        var bw = box.clientWidth, bh = box.clientHeight;
        if (!bw || !bh) return;
        // Element in «cover»-Grösse des Fotos, zentriert; der Überhang ist der Spielraum für den Schwenk.
        var base = Math.max(bw / img.naturalWidth, bh / img.naturalHeight);
        var sw = img.naturalWidth * base, sh = img.naturalHeight * base;
        var el = document.createElement('div');
        el.className = 'hero-slide';
        el.style.left = '50%';
        el.style.top = '50%';
        el.style.width = sw.toFixed(1) + 'px';
        el.style.height = sh.toFixed(1) + 'px';
        el.style.margin = (-sh / 2).toFixed(1) + 'px 0 0 ' + (-sw / 2).toFixed(1) + 'px';
        el.style.backgroundSize = '100% 100%';
        el.style.backgroundImage = "url('" + src.replace(/'/g, '%27') + "')";

        // Zoom rein oder raus; Schwenk bleibt innerhalb des Überhangs beim kleineren Zoom (keine leeren Ränder).
        var s0 = rnd(1.05, 1.12), s1 = rnd(1.20, 1.35);
        if (Math.random() < 0.5) { var t = s0; s0 = s1; s1 = t; }
        var sm = Math.min(s0, s1);
        var limX = Math.max(0, (sw * sm - bw) / 2 - 1);
        var limY = Math.max(0, (sh * sm - bh) / 2 - 1);
        var dirY = Math.random() < 0.5 ? 1 : -1; // vertikal von einem Rand zum anderen
        var vars = {
            '--kb-s0': s0.toFixed(3),
            '--kb-s1': s1.toFixed(3),
            '--kb-x0': rnd(-limX, limX).toFixed(1) + 'px',
            '--kb-x1': rnd(-limX, limX).toFixed(1) + 'px',
            '--kb-y0': (dirY * limY * rnd(0.6, 1)).toFixed(1) + 'px',
            '--kb-y1': (-dirY * limY * rnd(0.6, 1)).toFixed(1) + 'px',
            '--kb-dur': DUR + 's'
        };
        for (var k in vars) el.style.setProperty(k, vars[k]);

        box.appendChild(el);
        void el.offsetWidth; // Startzustand rendern, damit die Überblendung greift
        el.classList.add('is-kb', 'is-current');

        if (current) {
            var old = current.el;
            old.classList.remove('is-current');
            setTimeout(function () { old.remove(); }, FADE + 200);
        } else {
            // Erstes Dia liegt deckend über dem Standbild: danach Standbild entfernen,
            // sonst schimmert es bei späteren Überblendungen durch.
            setTimeout(function () { box.style.backgroundImage = 'none'; }, FADE + 200);
        }
        current = { el: el, src: src, img: img };
    }

    function step() {
        var src = next();
        load(src).then(function (img) {
            if (img) show(src, img);
            timer = setTimeout(step, img ? HOLD + FADE : 0);
        });
    }

    // Fenstergrösse geändert: aktuelles Foto mit neuer Geometrie einblenden (sonst evtl. leere Ränder).
    var resizeTimer = null, lastW = box.clientWidth, lastH = box.clientHeight;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (!current || (box.clientWidth === lastW && box.clientHeight === lastH)) return;
            lastW = box.clientWidth; lastH = box.clientHeight;
            clearTimeout(timer);
            show(current.src, current.img);
            timer = setTimeout(step, HOLD + FADE);
        }, 250);
    });

    step();
})();
