/**
 * Get the HSL value of a CSS variable.
 * @param {string} varName
 * @returns {string}
 */
export function getCssHsl(varName) {
    var val = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
    if (!val) return null;
    return 'hsl(' + val.replace(/\s+/g, ', ') + ')';
}

/**
 * Decode HTML entities from a string.
 * @param {string} value
 * @returns {string}
 */
export function decodeHtmlEntities(value) {
    if (typeof document === 'undefined') return value;
    var doc = new DOMParser().parseFromString(String(value), 'text/html');
    return doc.documentElement.textContent || '';
}
