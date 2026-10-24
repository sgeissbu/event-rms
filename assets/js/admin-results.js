// Resultat nachtragen: zeigt nur die Eingabefelder der gewählten Disziplin.
// Felder anderer Disziplinen werden deaktiviert, damit sie nicht mitgesendet werden.
(function () {
    'use strict';
    const select = document.querySelector('[data-discipline-select]');
    if (!select) return;
    const groups = document.querySelectorAll('[data-discipline-fields]');

    function apply() {
        groups.forEach((group) => {
            const active = group.dataset.disciplineFields === select.value;
            group.hidden = !active;
            group.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
        });
    }
    select.addEventListener('change', apply);
    apply();
})();
