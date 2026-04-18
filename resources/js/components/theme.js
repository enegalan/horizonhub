var THEME_KEY = 'horizonhub_theme';

/**
 * Validated theme value from localStorage.
 * @returns {'light'|'dark'|'system'}
 */
function getStoredTheme() {
    return localStorage.getItem(THEME_KEY);
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

if (typeof window !== 'undefined') {
    window.horizonHubTheme = {
        getStoredTheme: getStoredTheme,
        resolveDark: resolveDark,
        applyFromPreference: function (theme) {
            document.documentElement.classList.toggle('dark', resolveDark(theme));
        },
        applyTheme: function () {
            document.documentElement.classList.toggle('dark', resolveDark(getStoredTheme()));
        },
        setTheme: function (theme) {
            localStorage.setItem(THEME_KEY, theme);
            window.dispatchEvent(new CustomEvent('apply-theme'));
            return theme;
        },
    };
}
