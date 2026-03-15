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
