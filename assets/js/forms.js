// Formular- und Meldungs-Helfer (alle Seiten):
// - Buttons mit data-once werden nach dem Absenden gesperrt (kein Doppel-Absenden bei langsamem Netz).
// - Meldungen (.flash) werden Screenreadern angesagt (Fokus darauf); Erfolgsmeldungen mit data-autohide
//   blenden sich nach einer Weile aus – nicht, solange man sie liest (Maus darüber, Fokus darin).
// - Fehlerhafte Felder (.is-invalid) werden mit ihrem Fehlertext verknüpft (aria-invalid / aria-describedby);
//   das erste fehlerhafte Feld bekommt den Fokus.
(function () {
    'use strict';

    // ---- Doppel-Absenden verhindern ------------------------------------------------------------
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        form.querySelectorAll('[data-once]').forEach((button) => {
            if (button.disabled) {
                event.preventDefault();
                return;
            }
            // Erst nach dem Absenden sperren, sonst wird der Button-Wert nicht mitgeschickt.
            setTimeout(() => {
                button.disabled = true;
                button.dataset.label = button.textContent;
                button.textContent = 'Wird gesendet …';
            }, 0);
        });
    });

    // ---- Fehlerfelder verknüpfen ---------------------------------------------------------------
    let counter = 0;
    const invalidFields = [];
    document.querySelectorAll('.is-invalid').forEach((field) => {
        if (!field.matches('input, select, textarea')) return;
        // Der Fehlertext folgt im selben Container (Bootstrap: .invalid-feedback als Geschwister, ggf. nach Hilfetext).
        let feedback = field.nextElementSibling;
        while (feedback && !feedback.classList.contains('invalid-feedback')) feedback = feedback.nextElementSibling;
        if (!feedback) feedback = field.parentElement && field.parentElement.querySelector('.invalid-feedback');
        field.setAttribute('aria-invalid', 'true');
        if (feedback) {
            if (!feedback.id) feedback.id = 'err-' + (++counter);
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), feedback.id].filter(Boolean).join(' '));
        }
        invalidFields.push(field);
    });

    // ---- Meldungen -----------------------------------------------------------------------------
    const flashes = document.querySelectorAll('.flash');
    flashes.forEach((el) => {
        el.setAttribute('tabindex', '-1');
        const delay = parseInt(el.dataset.autohide || '0', 10);
        if (!delay) return;
        let timer = null;
        let hovering = false;
        const hide = () => {
            if (hovering || el.contains(document.activeElement)) return start(); // später nochmals versuchen
            el.classList.add('is-hiding');
            setTimeout(() => el.remove(), 450);
        };
        function start() { clearTimeout(timer); timer = setTimeout(hide, delay); }
        el.addEventListener('mouseenter', () => { hovering = true; clearTimeout(timer); });
        el.addEventListener('mouseleave', () => { hovering = false; start(); });
        el.addEventListener('focusin', () => clearTimeout(timer));
        el.addEventListener('focusout', start);
        start();
    });

    // Fokus: fehlerhaftes Feld hat Vorrang, sonst die erste Meldung (so wird sie vorgelesen).
    if (invalidFields.length > 0) {
        invalidFields[0].focus({ preventScroll: false });
    } else if (flashes.length > 0 && !document.activeElement.matches('input, select, textarea')) {
        flashes[0].focus({ preventScroll: true });
    }

    // ---- Zurück-Navigation (bfcache): Buttons wieder freigeben ---------------------------------
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-once][disabled]').forEach((button) => {
            button.disabled = false;
            if (button.dataset.label) button.textContent = button.dataset.label;
        });
    });
})();
