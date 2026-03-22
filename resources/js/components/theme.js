var THEME_KEY = 'horizonhub_theme';

/**
 * Apply the theme to the document.
 * @returns {void}
 */
export function applyTheme() {
    document.documentElement.classList.toggle('dark', getResolvedDark());
}

/**
 * Get the resolved dark theme.
 * @returns {boolean}
 */
function getResolvedDark() {
    var t = localStorage.getItem(THEME_KEY) || 'light';
    if (t === 'system') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    return t === 'dark';
}
