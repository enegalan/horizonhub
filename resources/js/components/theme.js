var THEME_KEY = 'horizonhub_theme';

/**
 * Initialize theme functionality.
 * @returns {Object}
 */
export function initTheme() {
    return {
        getStoredTheme: getStoredTheme,
        resolveDark: resolveDark,
        /**
         * Apply theme from preference.
         * @param {'light'|'dark'|'system'} theme
         * @returns {void}
         */
        applyFromPreference: function (theme) {
            document.documentElement.classList.toggle('dark', resolveDark(theme));
        },
        /**
         * Apply theme.
         * @returns {void}
         */
        applyTheme: function () {
            document.documentElement.classList.toggle('dark', resolveDark(getStoredTheme()));
        },
        /**
         * Set theme.
         * @param {'light'|'dark'|'system'} theme
         * @returns {void}
         */
        setTheme: function (theme) {
            localStorage.setItem(THEME_KEY, theme);
            window.dispatchEvent(new CustomEvent('apply-theme'));
            return theme;
        },
    };
}

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
