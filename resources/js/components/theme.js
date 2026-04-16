var THEME_KEY = 'horizonhub_theme';

/**
 * Validated theme value from localStorage.
 * @returns {'light'|'dark'|'system'}
 */
export function getStoredTheme() {
    return localStorage.getItem(THEME_KEY);
}

/**
 * Whether the given theme preference resolves to dark mode for the document.
 * @param {'light'|'dark'|'system'} theme
 * @returns {boolean}
 */
export function resolveDark(theme) {
    if (theme === 'system') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    return theme === 'dark';
}

/**
 * Set theme and dispatch `apply-theme` event.
 * @param {'light'|'dark'|'system'} theme
 * @returns {'light'|'dark'|'system'}
 */
export function setTheme(theme) {
    localStorage.setItem(THEME_KEY, theme);
    if (typeof window !== 'undefined') {
        window.__horizonhub_theme = theme;
    }
    window.dispatchEvent(new CustomEvent('apply-theme'));
    return theme;
}

/**
 * Apply the theme to the document from localStorage.
 * @returns {void}
 */
export function applyTheme() {
    if (typeof window !== 'undefined') {
        window.__horizonhub_theme = getStoredTheme();
    }
    document.documentElement.classList.toggle('dark', resolveDark(getStoredTheme()));
}

if (typeof window !== 'undefined') {
    window.horizonHubTheme = {
        getStoredTheme: getStoredTheme,
        resolveDark: resolveDark,
        applyFromPreference: function (theme) {
            document.documentElement.classList.toggle('dark', resolveDark(theme));
        },
        setTheme: setTheme,
    };
}
