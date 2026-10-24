// Admin-Helfer: QR-Codes (qrcode-generator, lokal), Link kopieren, Drucken.
(function () {
    'use strict';

    document.querySelectorAll('[data-qr]').forEach((el) => {
        if (typeof qrcode !== 'function') return;
        const qr = qrcode(0, 'M');
        qr.addData(el.dataset.qr);
        qr.make();
        // createSvgTag erzeugt nur Pfade/Rechtecke – keine Nutzerdaten im Markup.
        el.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
    });

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', () => {
            navigator.clipboard.writeText(button.dataset.copy).then(() => {
                const label = button.textContent;
                button.textContent = 'Kopiert ✓';
                setTimeout(() => { button.textContent = label; }, 1500);
            });
        });
    });

    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });
})();
