/**
 * Format queue wait seconds as humanized duration and observe DOM for new elements.
 * @param {Element} root
 * @returns {void}
 */
export function formatQueueWaitElements(root) {
    if (typeof window.moment === 'undefined') return;

    var context = root && typeof root.querySelectorAll === 'function' ? root : document;
    if (!context) return;

    var nodes = context.querySelectorAll('[data-wait-seconds]');
    if (!nodes || !nodes.length) return;

    nodes.forEach(function (el) {
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
