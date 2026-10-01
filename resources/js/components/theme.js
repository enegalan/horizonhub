const THEME_KEY = 'horizonhub_theme';

/**
 * Get the stored theme from localStorage.
 * @returns {'light'|'dark'|'system'}
 */
export function getTheme() {
    var raw = localStorage.getItem(THEME_KEY);

    if (raw === 'light' || raw === 'dark' || raw === 'system') {
        return raw;
    }

    return 'light';
}

/**
 * Whether the given theme preference resolves to dark mode for the document.
 * @param {'light'|'dark'|'system'} theme
 * @returns {boolean}
 */
function resolveDark(theme) {
    if (theme === 'system') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    return theme === 'dark';
}

/**
 * Apply theme.
 * @returns {void}
 */
export function applyTheme() {
    const theme = getTheme();
    const isDark = resolveDark(theme);

    document.documentElement.classList.toggle('light', !isDark);
    document.documentElement.classList.toggle('dark', isDark);
}

/**
 * Cycle the theme preference: light → dark → system → light.
 * @returns {'light'|'dark'|'system'}
 */
export function cycleTheme() {
    var current = getTheme();
    var next = current === 'light' ? 'dark' : current === 'dark' ? 'system' : 'light';

    return setTheme(next);
}

/**
    * Set theme.
    * @param {'light'|'dark'|'system'} theme
    * @returns {'light'|'dark'|'system'}
    */
function setTheme(theme) {
    localStorage.setItem(THEME_KEY, theme);
    window.dispatchEvent(new CustomEvent('apply-theme'));
    return theme;
}
