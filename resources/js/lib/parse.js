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
