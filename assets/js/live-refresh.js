// Live-Rangliste: lädt die Abschnitte periodisch nach, ohne die Seite neu zu laden.
// Aufgeklappte Karten (Mobile) bleiben aufgeklappt; bei Netzfehlern wird still weiter versucht.
(function () {
    'use strict';
    const container = document.querySelector('[data-refresh-url]');
    if (!container) return;

    const url = container.dataset.refreshUrl;
    const seconds = Math.max(10, parseInt(container.dataset.refreshSeconds || '30', 10));

    async function refresh() {
        if (document.hidden) return; // Hintergrund-Tabs nicht belasten
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' });
            if (!response.ok) return;
            const html = await response.text();
            const open = new Set();
            container.querySelectorAll('details[open] .ranking-name').forEach((n) => open.add(n.textContent));
            container.innerHTML = html;
            container.querySelectorAll('details').forEach((d) => {
                const name = d.querySelector('.ranking-name');
                if (name && open.has(name.textContent)) d.open = true;
            });
        } catch (e) {
            // offline o.ä. – beim nächsten Intervall erneut
        }
    }

    setInterval(refresh, seconds * 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
