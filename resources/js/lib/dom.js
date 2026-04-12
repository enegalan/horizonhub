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
 * The textarea element used to decode HTML entities.
 * @type {HTMLTextAreaElement|null}
 */
var _textarea = null;
/**
 * Decode HTML entities from a string.
 * @param {string} value
 * @returns {string}
 */
export function decodeHtmlEntities(value) {
    if (typeof document === 'undefined') return value;
    if (!_textarea) {
        _textarea = document.createElement('textarea');
    }
    _textarea.innerHTML = value;

    return _textarea.value;
}
