/**
 * Progressive enhancement for the collection toolbar.
 *
 * The toolbar is a working GET form on its own — every control submits, and the
 * Apply buttons are real. All this does is remove the step of pressing Apply
 * after changing the sort order, which is the one place the extra click is pure
 * friction rather than a confirmation.
 *
 * The Apply button beside the sort select is only hidden once this runs, so
 * without a script it stays on screen and keeps working.
 */
export default function initCatalogueFilters() {
    const form = document.querySelector('[data-catalogue-filters]');

    if (!form) {
        return;
    }

    const sort = form.querySelector('select[name="sort"]');
    const submit = form.querySelector('[data-filters-submit]');

    if (!sort || !submit) {
        return;
    }

    submit.hidden = true;
    submit.classList.add('hidden');

    sort.addEventListener('change', () => form.submit());
}
