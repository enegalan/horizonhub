/**
 * Initialize the document.
 * @param {function} callback
 * @returns {void}
 */
export function onDocumentReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
}

/**
 * Schedule a callback.
 * @param {function} callback
 * @returns {void}
 */
export function schedule(callback) {
    setTimeout(callback, 0);
}
