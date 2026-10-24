// Anlass-Formular: Mitglieder-Zeilen und Auswahllisten passen sich der Teamgrösse an,
// Handicap-Felder werden nur bei aktiviertem Handicap gezeigt.
(function () {
    'use strict';
    const sizeSelect = document.querySelector('[data-team-size]');
    if (!sizeSelect) return;

    const rows = document.querySelectorAll('[data-member-row]');
    const memberSelects = document.querySelectorAll('[data-member-select]');
    const noun = document.querySelector('[data-member-noun]');

    function applySize() {
        const size = parseInt(sizeSelect.value, 10) || 1;
        rows.forEach((row) => {
            const visible = parseInt(row.dataset.memberRow, 10) <= size;
            row.hidden = !visible;
            // Versteckte Zeilen nicht mitsenden (Server ignoriert sie ohnehin).
            row.querySelectorAll('input, select').forEach((el) => { el.disabled = !visible; });
        });
        memberSelects.forEach((select) => {
            Array.from(select.options).forEach((option) => {
                option.hidden = option.disabled = parseInt(option.dataset.pos, 10) > size;
            });
            if (parseInt(select.value, 10) > size) select.value = String(size);
        });
        if (noun) noun.textContent = size === 1 ? 'Person' : 'Teammitglied';
    }

    sizeSelect.addEventListener('change', applySize);
    applySize();

    document.querySelectorAll('[data-toggle-target]').forEach((box) => {
        const target = document.querySelector(box.dataset.toggleTarget);
        if (!target) return;
        const apply = () => { target.hidden = !box.checked; };
        box.addEventListener('change', apply);
        apply();
    });
})();
