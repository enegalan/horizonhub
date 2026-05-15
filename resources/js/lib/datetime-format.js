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
    root.querySelectorAll('[data-last-seen-at]').forEach(function (el) {
        var m = window.moment(el.getAttribute('data-last-seen-at'));
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
    root.querySelectorAll('[data-wait-seconds]').forEach(function (el) {
        var raw = el.getAttribute('data-wait-seconds');
        if (!raw) return;

        var seconds = parseFloat(raw);
        if (!isFinite(seconds) || seconds < 0) return;

        var text = window.moment.duration(seconds, 'seconds').humanize();
        if (!text) return;

        text = text.replace(/^(.)/g, function ($1) { return $1.toUpperCase(); });
        if (el.textContent.trim() === text) {
            return;
        }
        el.textContent = text;
    });
}
