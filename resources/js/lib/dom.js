/**
 * Dispatch horizonhub-refresh with a parsed document.
 * @param {Document} doc
 * @returns {void}
 */
export function dispatchHorizonHubRefreshWithDocument(doc) {
    if (typeof window === 'undefined') return;
    if (typeof requestAnimationFrame !== 'undefined') {
        requestAnimationFrame(function () {
            window.dispatchEvent(new CustomEvent('horizonhub-refresh', { detail: { document: doc } }));
        });
    } else {
        window.dispatchEvent(new CustomEvent('horizonhub-refresh', { detail: { document: doc } }));
    }
}

/**
 * Subscribe to horizonhub-refresh when the tab is visible and a document is present.
 * @param {function(Document): void} onRefresh
 * @param {{ shouldSkip?: function(CustomEvent): boolean }} options
 * @returns {void}
 */
export function onHorizonHubRefresh(onRefresh, options) {
    if (typeof window === 'undefined') return;
    var shouldSkip = options && typeof options.shouldSkip === 'function' ? options.shouldSkip : null;
    window.addEventListener('horizonhub-refresh', function (e) {
        if (shouldSkip && shouldSkip(e)) return;
        if (typeof document === 'undefined') return;
        if (document.visibilityState !== 'visible') return;
        var d = e.detail && e.detail.document;
        if (!d) return;
        onRefresh(d);
    });
}

/**
 * Replace the tbody of a table in the live document using a matching table from a fetched document.
 * @param {Document} preloadedDoc
 * @param {{ tableSelector: string, currentTableSelector?: string }} opts
 * @returns {HTMLTableElement|null} The live table element after replacement, or null if skipped.
 */
export function replaceTableTbodyFromDoc(preloadedDoc, opts) {
    if (typeof window === 'undefined' || typeof document === 'undefined') return null;
    if (!preloadedDoc || !opts || !opts.tableSelector) return null;
    var currentSel = opts.currentTableSelector || opts.tableSelector;
    var newTable = preloadedDoc.querySelector(opts.tableSelector);
    var currentTable = document.querySelector(currentSel);
    if (!newTable || !currentTable) return null;
    var newTbody = newTable.querySelector('tbody');
    var currentTbody = currentTable.querySelector('tbody');
    if (!newTbody || !currentTbody) return null;
    currentTbody.replaceWith(newTbody);
    return currentTable;
}

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

/**
 * The textarea element used to decode HTML entities.
 * @type {HTMLTextAreaElement|null}
 */
var _textarea = null;
/**
 * Decode HTML entities from a string.
 * @param {string} value
 * @returns {string}
 */
export function decodeHtmlEntities(value) {
    if (typeof document === 'undefined') return value;
    if (!_textarea) {
        _textarea = document.createElement('textarea');
    }
    _textarea.innerHTML = value;

    return _textarea.value;
}
