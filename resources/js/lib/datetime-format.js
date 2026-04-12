/**
 * Pad a number with a leading zero.
 * @param {number} n
 * @returns {string}
 */
function pad(n) {
    return n < 10 ? '0' + n : '' + n;
}

/**
 * Format date time elements.
 * @param {Element} root
 * @returns {void}
 */
export function formatDateTimeElements(root) {
    var context = root || document;
    var els = context.querySelectorAll('[data-datetime]');
    if (!els || !els.length) return;

    els.forEach(el => {
        var iso = el.getAttribute('data-datetime');
        if (!iso) return;

        try {
            var d = new Date(iso);
            if (isNaN(d.getTime())) return;

            var year = d.getFullYear();
            var month = pad(d.getMonth() + 1);
            var day = pad(d.getDate());
            var hour = pad(d.getHours());
            var minute = pad(d.getMinutes());
            var second = pad(d.getSeconds());
            var formatted = year + '-' + month + '-' + day + ' ' + hour + ':' + minute + ':' + second;
            if (el.textContent.trim() === formatted) {
                return;
            }
            el.textContent = formatted;
        } catch (e) {}
    });
}

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
