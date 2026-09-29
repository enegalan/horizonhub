/**
 * Attribute name for the last seen at.
 * @type {string}
 */
export const LAST_SEEN_AT_ATTR = 'data-last-seen-at';

/**
 * Attribute name for the wait seconds.
 * @type {string}
 */
export const WAIT_SECONDS_ATTR = 'data-wait-seconds';

/**
 * Format datetime elements in the DOM.
 * @param {Element|null} root
 * @returns {void}
 */
export function formatDatetimeElements(root) {
    if (typeof window.moment === 'undefined') return;

    var context = root && typeof root.querySelectorAll === 'function' ? root : document;
    if (!context) return;

    private__formatLastSeenElements(context);
    private__formatQueueWaitElements(context);
}

/**
 * Format last seen at as humanized duration and observe DOM for new elements.
 * @param {Element} root
 * @returns {void}
 */
function private__formatLastSeenElements(root) {
    root.querySelectorAll('[' + LAST_SEEN_AT_ATTR + ']').forEach(function (el) {
        var m = window.moment(el.getAttribute(LAST_SEEN_AT_ATTR));
        if (m.isValid()) {
            el.textContent = m.fromNow();
        }
    });
}

/**
 * Format queue wait seconds as humanized duration and observe DOM for new elements.
 * @param {Element} root
 * @returns {void}
 */
function private__formatQueueWaitElements(root) {
    root.querySelectorAll('[' + WAIT_SECONDS_ATTR + ']').forEach(function (el) {
        var raw = el.getAttribute(WAIT_SECONDS_ATTR);
        if (!raw) return;

        var seconds = parseFloat(raw);
        if (!isFinite(seconds) || seconds < 0) return;

        var text = window.moment.duration(seconds, 'seconds').humanize();
        if (!text) return;

        text = text.replace(/^(.)/g, function ($1) { return $1.toUpperCase(); });
        el.textContent = text;
    });
}
