// Countdown (Info-Leiste der Startseite): zählt bis data-countdown (ISO-Zeitpunkt) und lädt beim Start neu,
// damit der Server die Live-Ansicht rendert.
(function () {
    'use strict';
    const el = document.querySelector('[data-countdown]');
    if (!el) return;

    const target = Date.parse(el.dataset.countdown);
    if (Number.isNaN(target)) return;

    const parts = {};
    el.querySelectorAll('[data-unit]').forEach((node) => { parts[node.dataset.unit] = node; });
    const pad = (n) => String(n).padStart(2, '0');
    // Nur neu laden, wenn der Start während des Anschauens erreicht wird – sonst
    // (Geräteuhr geht vor) entstünde eine Reload-Schleife.
    const startedBeforeLoad = target <= Date.now();

    function tick() {
        const diff = target - Date.now();
        if (diff <= 0) {
            clearInterval(timer);
            Object.values(parts).forEach((node) => { node.textContent = '00'; });
            if (!startedBeforeLoad) setTimeout(() => window.location.reload(), 2000);
            return;
        }
        const s = Math.floor(diff / 1000);
        const values = { days: Math.floor(s / 86400), hours: Math.floor((s % 86400) / 3600), minutes: Math.floor((s % 3600) / 60), seconds: s % 60 };
        // Nur die vorhandenen Einheiten füllen (die Info-Leiste der Startseite zeigt keine Sekunden).
        Object.keys(parts).forEach((unit) => { parts[unit].textContent = unit === 'days' ? String(values.days) : pad(values[unit]); });
    }

    const timer = setInterval(tick, 1000);
    tick();
})();
