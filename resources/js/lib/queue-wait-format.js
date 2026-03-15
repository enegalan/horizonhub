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
        el.textContent = text;
    });
}

/**
 * Observe the document for new nodes with data-wait-seconds and format them.
 * @returns {void}
 */
export function observeQueueWaitElements() {
    if (typeof MutationObserver === 'undefined' || typeof document === 'undefined') return;
    if (document.__queueWaitObserverInitialized) return;

    var target = document.documentElement || document.body;
    if (!target) return;

    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (!mutation.addedNodes || !mutation.addedNodes.length) return;
            mutation.addedNodes.forEach(function (node) {
                if (!node || node.nodeType !== 1) return;
                if (node.hasAttribute && node.hasAttribute('data-wait-seconds')) {
                    formatQueueWaitElements(node);
                } else if (typeof node.querySelectorAll === 'function') {
                    formatQueueWaitElements(node);
                }
            });
        });
    });

    observer.observe(target, { childList: true, subtree: true });
    document.__queueWaitObserverInitialized = true;
}
