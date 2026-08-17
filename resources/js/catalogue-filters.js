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

    // requestSubmit(), not submit(): the latter navigates without firing the
    // submit event, which would skip the cleanup below and leave "?price=" on
    // every URL produced by the sort dropdown.
    sort.addEventListener('change', () => form.requestSubmit());

    // "Any price" and "All" are empty values, and an unsearched grid has no
    // query — submitted as-is they leave "?price=&q=" trailing on every URL.
    // Disabling them drops them from serialisation, which happens after this
    // handler runs. Without a script the URL simply carries the empties; they
    // read as absent server-side either way.
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[name], select[name]').forEach((field) => {
            const empty = field.type === 'radio' ? field.checked && field.value === '' : !field.value;

            if (empty) {
                field.disabled = true;
            }
        });
    });
}
