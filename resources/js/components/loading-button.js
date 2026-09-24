/**
 * Reveal the loading spinner on HTTP-triggering buttons.
 *
 * Turbo already disables the submit button while a form submission is in
 * flight, so form buttons only need the `data-loading` attribute toggled.
 * Form-drawer links are handled the same way until their frame loads.
 */

const FRAME_LINK_SELECTOR = 'a.btn-loadable[data-turbo-frame]';

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
 * Set loading when Turbo follows a frame link. `turbo:click` only fires for
 * clicks Turbo will intercept, so modifier-clicks (new tab, etc.) are ignored.
 * An already-loading link cancels the navigation to avoid a duplicate request.
 * @param {CustomEvent} event
 * @returns {void}
 */
document.addEventListener('turbo:click', function (event) {
    const link = event.target?.closest?.(FRAME_LINK_SELECTOR);
    if (!link) return;

    if (link.hasAttribute('data-loading')) {
        event.preventDefault();
        event.detail?.originalEvent?.preventDefault();
        return;
    }

    setLoading(link, true);
});

/**
 * Clear only the frame links whose request just finished.
 * @param {CustomEvent} event
 * @returns {void}
 */
function clearFrameLoading(event) {
    const frameId = event.target?.id;
    if (!frameId) return;

    document.querySelectorAll('a.btn-loadable[data-loading][data-turbo-frame="' + CSS.escape(frameId) + '"]').forEach(function (el) {
        setLoading(el, false);
    });
}

['turbo:frame-load', 'turbo:frame-missing', 'turbo:fetch-request-error'].forEach(function (name) {
    document.addEventListener(name, clearFrameLoading);
});

/**
 * Toggle the loading state of a button-like element.
 * @param {HTMLElement|null} el
 * @param {boolean} isLoading
 * @returns {void}
 */
export function setLoading(el, isLoading) {
    if (!el || !el.classList || !el.classList.contains('btn-loadable')) return;

    if (isLoading) {
        el.setAttribute('data-loading', '');
        el.setAttribute('aria-busy', 'true');
    } else {
        el.removeAttribute('data-loading');
        el.removeAttribute('aria-busy');
    }
}
