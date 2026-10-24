// Kampfrichter-Erfassung: Suche nach Startnummer/Name und Eingabefelder je Disziplin.
// DOM wird ausschliesslich mit textContent aufgebaut (Namen sind Nutzereingaben).
(function () {
    'use strict';
    const form = document.getElementById('capture-form');
    if (!form) return;

    const search = document.getElementById('entry-search');
    const entryId = document.getElementById('entry-id');
    const entryLabel = document.getElementById('entry-label');
    const list = document.getElementById('entry-results');
    const disciplineSelect = document.getElementById('discipline');
    const valueFields = document.getElementById('value-fields');
    const disciplines = JSON.parse(form.dataset.disciplines || '[]');
    const searchUrl = form.dataset.searchUrl;

    const errorBox = document.getElementById('entry-error');
    let items = [];
    let active = -1;
    let timer = null;
    let requestNo = 0;

    // ---- Suche ---------------------------------------------------------------
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    function render() {
        list.replaceChildren();
        items.forEach((item, i) => {
            const li = el('li', i === active ? 'active' : '');
            li.id = 'entry-option-' + i;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', i === active ? 'true' : 'false');
            li.append(el('span', 'start-no', String(item.start_no)));
            const text = el('span', 'min-w-0');
            text.append(el('span', 'd-block fw-semibold text-truncate', item.name));
            if (item.members && item.members !== item.name) {
                text.append(el('span', 'd-block small text-muted text-truncate', item.members));
            }
            li.append(text);
            li.addEventListener('mousedown', (e) => { e.preventDefault(); choose(i); });
            list.append(li);
        });
        if (items.length === 0 && search.value.trim() !== '') {
            list.append(el('li', 'empty', 'Nichts gefunden'));
        }
        list.hidden = search.value.trim() === '';
        search.setAttribute('aria-expanded', String(!list.hidden));
        if (active >= 0 && !list.hidden) search.setAttribute('aria-activedescendant', 'entry-option-' + active);
        else search.removeAttribute('aria-activedescendant');
    }

    function choose(i) {
        const item = items[i];
        if (!item) return;
        entryId.value = String(item.id);
        entryLabel.value = item.start_no + ' – ' + item.name;
        search.value = entryLabel.value;
        list.hidden = true;
        clearError();
        search.setAttribute('aria-expanded', 'false');
        search.removeAttribute('aria-activedescendant');
        const first = valueFields.querySelector('.value-input');
        if (first) first.focus();
    }

    async function lookup() {
        const q = search.value.trim();
        const no = ++requestNo;
        if (q === '') { items = []; render(); return; }
        try {
            const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q), { cache: 'no-store' });
            if (res.status === 401) { window.location.reload(); return; }
            const data = await res.json();
            if (no !== requestNo) return; // veraltete Antwort
            items = Array.isArray(data) ? data : [];
            active = items.length ? 0 : -1;
            render();
        } catch (e) {
            items = [];
            list.replaceChildren(el('li', 'empty', 'Keine Verbindung – bitte nochmals versuchen'));
            list.hidden = false;
        }
    }

    function showError() {
        errorBox.hidden = false;
        search.classList.add('is-invalid');
        search.setAttribute('aria-invalid', 'true');
        search.setAttribute('aria-describedby', 'entry-hint entry-error');
    }

    function clearError() {
        errorBox.hidden = true;
        search.classList.remove('is-invalid');
        search.removeAttribute('aria-invalid');
        search.setAttribute('aria-describedby', 'entry-hint');
    }

    search.addEventListener('input', () => {
        clearError();
        entryId.value = '';
        entryLabel.value = '';
        clearTimeout(timer);
        timer = setTimeout(lookup, 150);
    });
    search.addEventListener('keydown', (e) => {
        if (list.hidden || items.length === 0) return;
        if (e.key === 'ArrowDown') { active = (active + 1) % items.length; render(); e.preventDefault(); }
        if (e.key === 'ArrowUp') { active = (active - 1 + items.length) % items.length; render(); e.preventDefault(); }
        if (e.key === 'Enter') { choose(Math.max(active, 0)); e.preventDefault(); }
        if (e.key === 'Escape') { list.hidden = true; }
    });
    search.addEventListener('blur', () => setTimeout(() => { list.hidden = true; search.setAttribute('aria-expanded', 'false'); }, 150));

    // Ohne Auswahl aus der Liste nicht absenden (verhindert Resultat beim falschen Team).
    form.addEventListener('submit', (e) => {
        if (entryId.value === '') {
            e.preventDefault();
            e.stopImmediatePropagation();
            search.focus();
            showError();
        }
    }, true);

    // ---- Felder je Disziplin -------------------------------------------------
    function buildFields(d) {
        const labels = d.parts && d.parts.length ? d.parts : ['Resultat'];
        valueFields.replaceChildren();
        labels.forEach((label, i) => {
            const wrap = el('div', 'mb-3');
            const lab = el('label', 'form-label', label);
            lab.htmlFor = 'value-' + i;
            const group = el('div', 'input-group input-group-lg');
            const input = el('input', 'form-control value-input');
            input.id = 'value-' + i;
            input.name = 'value[]';
            input.required = true;
            input.inputMode = d.format === 'time' ? 'text' : 'decimal';
            input.placeholder = d.format === 'time' ? '1:23.45 oder 83.45' : 'Wert eingeben';
            group.append(input);
            if (d.unit) group.append(el('span', 'input-group-text', d.unit));
            wrap.append(lab, group);
            valueFields.append(wrap);
        });
        if (labels.length > 1) {
            valueFields.append(el('p', 'form-text', 'Die Teilmessungen werden automatisch zusammengezählt.'));
        }
    }

    disciplineSelect.addEventListener('change', () => {
        const d = disciplines.find((x) => String(x.id) === disciplineSelect.value);
        if (d) buildFields(d);
        search.focus();
    });
})();
