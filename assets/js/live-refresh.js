// Live-Rangliste: lädt die Abschnitte periodisch nach, ohne die Seite neu zu laden.
// - Nur ersetzen, wenn sich etwas geändert hat (sonst gehen Fokus und Vorlese-Position grundlos verloren).
// - Nicht ersetzen, solange der Fokus in der Rangliste liegt (Tastatur- und Screenreader-Nutzung).
// - Aufgeklappte Karten (Mobile) bleiben aufgeklappt; bei Netzfehlern oder fremden Antworten (z.B. Fehlerseite) passiert nichts.
(function () {
    'use strict';
    const container = document.querySelector('[data-refresh-url]');
    if (!container) return;

    const url = container.dataset.refreshUrl;
    const seconds = Math.max(10, parseInt(container.dataset.refreshSeconds || '30', 10));
    let lastHtml = container.innerHTML.trim();

    async function refresh() {
        if (document.hidden) return; // Hintergrund-Tabs nicht belasten
        if (container.contains(document.activeElement)) return; // nicht unter dem Fokus wegnehmen
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'fetch' }, cache: 'no-store' });
            // Nur die erwartete Teilseite übernehmen (nicht z.B. eine Login- oder Fehlerseite nach einem Redirect).
            if (!response.ok || response.redirected || response.headers.get('X-Partial') !== 'ranking') return;
            const html = (await response.text()).trim();
            if (html === lastHtml || container.contains(document.activeElement)) return;

            const open = new Set();
            container.querySelectorAll('details[open] .ranking-name').forEach((n) => open.add(n.textContent));
            container.innerHTML = html;
            lastHtml = html;
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
