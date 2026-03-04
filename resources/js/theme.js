// TODO: Deprecate legacy dark mode key
var THEME_KEY = 'horizonhub_theme';
var DARK_KEY = 'horizonhub_dark';

export function getResolvedDark() {
    var t = null;
    try {
        t = localStorage.getItem(THEME_KEY);
    } catch (e) {
        t = null;
    }
    if (!t) {
        var legacy = null;
        try {
            legacy = localStorage.getItem(DARK_KEY);
        } catch (e2) {
            legacy = null;
        }
        t = legacy === 'true' ? 'dark' : 'light';
    }
    if (t === 'system') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    return t === 'dark';
}

export function applyTheme() {
    document.documentElement.classList.toggle('dark', getResolvedDark());
}
