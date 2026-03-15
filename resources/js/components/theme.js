var THEME_KEY = 'horizonhub_theme';

export function getResolvedDark() {
    var t = localStorage.getItem(THEME_KEY) || 'light';
    if (t === 'system') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    return t === 'dark';
}

export function applyTheme() {
    document.documentElement.classList.toggle('dark', getResolvedDark());
}
