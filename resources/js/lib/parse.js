/**
 * Parse a JSON string.
 * @param {string} raw
 * @param {any} defaultValue
 * @returns {any}
 */
export function parseJson(raw, defaultValue = null) {
    if (!raw) return defaultValue;

    try {
        var parsed = JSON.parse(raw);
        return typeof parsed === 'object' ? parsed : defaultValue;
    } catch (err) {
        console.error('Failed to parse JSON', err);
        return defaultValue;
    }
}

/**
 * Parse a JSON string from an element.
 * @param {string} elementId
 * @returns {any}
 */
export function parseJsonFromElement(elementId) {
    var el = document.getElementById(elementId);
    if (!el) return null;

    return parseJson(el.textContent);
}

/**
 * Split retry modal "failed at" range field into API params.
 * @param {string} rangeValue
 * @returns {{ dateFrom: string, dateTo: string }}
 */
export function parseFailedAtRange(rangeValue) {
    var v = typeof rangeValue === 'string' ? rangeValue.trim() : '';
    if (!v) {
        return { dateFrom: '', dateTo: '' };
    }
    var parts = v.split(/\s+to\s+/i).map(function (s) {
        return s.trim();
    }).filter(Boolean);
    if (parts.length === 0) {
        return { dateFrom: '', dateTo: '' };
    }
    if (parts.length === 1) {
        return { dateFrom: parts[0], dateTo: '' };
    }

    return { dateFrom: parts[0], dateTo: parts[1] };
}
