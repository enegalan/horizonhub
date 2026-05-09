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
        /**
         * Advance stored preference: light → dark → system → light.
         * @returns {'light'|'dark'|'system'}
         */
        cycleTheme: function () {
            var current = getStoredTheme();
            var next = current === 'light' ? 'dark' : current === 'dark' ? 'system' : 'light';

            return this.setTheme(next);
        },
    };
}

/**
 * Validated theme value from localStorage.
 * @returns {'light'|'dark'|'system'}
 */
function getStoredTheme() {
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
