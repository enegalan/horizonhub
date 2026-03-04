export function parseJson(raw, defaultValue = null) {
    if (!raw) return defaultValue;

    try {
        var parsed = JSON.parse(raw);
        return typeof parsed === 'object' ? parsed : defaultValue;
    } catch (err) {
        console.error('Failed to parse JSON', err);
        return defaultValue;
    }
}

export function parseJsonFromElement(elementId) {
    var el = document.getElementById(elementId);
    if (!el) return null;

    return parseJson(el.textContent);
}