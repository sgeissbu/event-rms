// Live-Vorschau der Vereinsfarben in «Verein & Design».
// Holt die berechneten Variablen vom Server (gleiche Logik wie die echte Seite) und setzt sie nur auf das
// Vorschau-Feld. Hell/Dunkel lässt sich umschalten, unabhängig von der Einstellung des Geräts.
(function () {
    'use strict';
    const box = document.getElementById('theme-preview');
    const primary = document.getElementById('color_primary');
    const secondary = document.getElementById('color_secondary');
    if (!box || !primary || !secondary) return;

    const warnings = box.querySelector('[data-theme-warnings]');
    const buttons = box.querySelectorAll('[data-mode-button]');
    let mode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    let data = null;
    let timer = null;
    let request = 0;

    function render() {
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.modeButton === mode)));
        box.dataset.mode = mode;
        if (!data) return;
        Object.entries(data[mode]).forEach(([name, value]) => box.style.setProperty(name, value));
        warnings.replaceChildren();
        data.warnings.forEach((text) => {
            const item = document.createElement('div');
            item.className = 'alert alert-warning small mb-0 mt-2';
            item.textContent = text;
            warnings.append(item);
        });
    }

    async function load() {
        const current = ++request;
        try {
            const url = box.dataset.url + '?primary=' + encodeURIComponent(primary.value) + '&secondary=' + encodeURIComponent(secondary.value);
            const response = await fetch(url, { cache: 'no-store' });
            if (!response.ok) return;
            const json = await response.json();
            if (current !== request) return; // inzwischen wurde eine neuere Farbe angefragt
            data = json;
            render();
        } catch (e) {
            // Vorschau ist nur eine Hilfe – bei Netzproblemen bleibt der letzte Stand stehen.
        }
    }

    const schedule = () => { clearTimeout(timer); timer = setTimeout(load, 120); };
    primary.addEventListener('input', schedule);
    secondary.addEventListener('input', schedule);
    buttons.forEach((button) => button.addEventListener('click', () => { mode = button.dataset.modeButton; render(); }));
    load();
})();
