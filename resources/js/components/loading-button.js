/**
 * Reveal the loading spinner on HTTP-triggering buttons.
 *
 * Turbo already disables the submit button while a form submission is in
 * flight, so form buttons only need the `data-loading` attribute toggled.
 * Form-drawer links are handled the same way until their frame loads.
 */

const LOADING_SELECTOR = '.btn-loadable[data-loading]';

/**
 * Set loading when form submission starts
 * @param {CustomEvent} event
 * @returns {void}
 */
document.addEventListener('turbo:submit-start', function (event) {
    setLoading(event.detail?.formSubmission?.submitter, true);
});

/**
 * Remove loading when form submission ends
 * @param {CustomEvent} event
 * @returns {void}
 */
document.addEventListener('turbo:submit-end', function (event) {
    setLoading(event.detail?.formSubmission?.submitter, false);
});

/**
 * Set loading when button is clicked
 * @param {CustomEvent} event
 * @returns {void}
 */
document.addEventListener('click', function (event) {
    setLoading(event.target?.closest('.btn-loadable[data-turbo-frame]'), true);
});

/**
 * Remove loading when frame loads, missing, or fetch request error
 * @param {string} name
 * @returns {void}
 */
['turbo:frame-load', 'turbo:frame-missing', 'turbo:fetch-request-error'].forEach(function (name) {
    document.addEventListener(name, () => document.querySelectorAll(LOADING_SELECTOR).forEach(function (el) {
        setLoading(el, false);
    }));
});

/**
 * Toggle the loading state of a button-like element.
 * @param {HTMLElement|null} el
 * @param {boolean} isLoading
 * @returns {void}
 */
export function setLoading(el, isLoading) {
    if (!el || !el.classList || !el.classList.contains('btn-loadable')) return;
    isLoading ? el.setAttribute('data-loading', '') : el.removeAttribute('data-loading');
}
