// Formular-Helfer:
// - Buttons mit data-once werden nach dem Absenden gesperrt (kein Doppel-Absenden bei langsamem Netz).
(function () {
    'use strict';
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
    // Zurück-Navigation (bfcache): Buttons wieder freigeben.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-once][disabled]').forEach((button) => {
            button.disabled = false;
            if (button.dataset.label) button.textContent = button.dataset.label;
        });
    });
})();
