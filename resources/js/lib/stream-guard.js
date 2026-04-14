/**
 * Core Turbo Stream handling for the Horizon Hub SSE pipeline:
 * target resolution, redundant update/replace skipping, and incremental patching
 * of keyed direct children (tables, lists, or any opt-in container).
 *
 * Table rows: only direct td/th with data-column-id are compared and updated.
 * Use data-stream-preserve-client on a cell to keep the live DOM (e.g. CSRF tokens in forms).
 */

/**
 * Preserve open/closed state for jobs stack sections across replace/update streams.
 * @param {Element} streamElement
 * @returns {void}
 */
function preserveJobsSectionsOpenState(streamElement) {
    var target = String(streamElement.getAttribute('target') || '').trim();
    if (target !== 'horizon-jobs-stack') {
        return;
    }
    var action = String(streamElement.getAttribute('action') || '').toLowerCase();
    if (action !== 'replace' && action !== 'update') {
        return;
    }

    var liveStack = document.getElementById('horizon-jobs-stack');
    var templateEl = streamElement.querySelector('template');
    if (!liveStack || !templateEl) {
        return;
    }

    var liveDetails = liveStack.querySelectorAll('details[data-section-key]');
    var openBySection = {};
    liveDetails.forEach(function (el) {
        var key = String(el.getAttribute('data-section-key') || '').trim();
        if (!key) {
            return;
        }
        openBySection[key] = !!el.open;
    });

    var holder = document.createElement('div');
    holder.innerHTML = templateEl.innerHTML;
    var incomingDetails = holder.querySelectorAll('details[data-section-key]');
    incomingDetails.forEach(function (el) {
        var key = String(el.getAttribute('data-section-key') || '').trim();
        if (!key || typeof openBySection[key] !== 'boolean') {
            return;
        }
        if (openBySection[key]) {
            el.setAttribute('open', '');
        } else {
            el.removeAttribute('open');
        }
    });
    templateEl.innerHTML = holder.innerHTML;
}

/**
 * Resolve a stream target element by its attribute value.
 * @param {string} targetAttr
 * @returns {Element|null}
 */
function resolveStreamTargetElement(targetAttr) {
    var raw = String(targetAttr || '').trim();
    if (!raw) {
        return null;
    }
    if (raw.charAt(0) === '#') {
        return document.querySelector(raw);
    }
    var byId = document.getElementById(raw);
    if (byId) {
        return byId;
    }
    try {
        return document.querySelector(raw);
    } catch (e) {
        return null;
    }
}

/**
 * Clone subtree and reset client-only display mutations so it matches server stream markup.
 * @param {Element} root
 * @returns {string}
 */
function innerHtmlNormalizedForStreamCompare(root) {
    var clone = root.cloneNode(true);
    var dt = clone.querySelectorAll('[data-datetime]');
    var i;
    for (i = 0; i < dt.length; i++) {
        dt[i].textContent = '-';
    }
    return clone.innerHTML;
}

/**
 * Whether an incoming `update` would produce the same effective markup as the live target.
 * @param {Element} targetEl
 * @param {HTMLTemplateElement} templateEl
 * @returns {boolean}
 */
function isRedundantUpdate(targetEl, templateEl) {
    var incomingHtml = templateEl.innerHTML;
    if (targetEl.innerHTML === incomingHtml) {
        return true;
    }

    var scratch = document.createElement('div');
    scratch.innerHTML = incomingHtml;

    var incomingStreamSig = scratch.querySelector('[data-horizon-stream-sig]');
    var existingStreamSig = targetEl.querySelector('[data-horizon-stream-sig]');
    if (incomingStreamSig && existingStreamSig) {
        var sigA = String(incomingStreamSig.getAttribute('data-horizon-stream-sig') || '');
        var sigB = String(existingStreamSig.getAttribute('data-horizon-stream-sig') || '');
        if (sigA !== '' && sigA === sigB) {
            return true;
        }
    }

    var incomingSources = scratch.querySelectorAll('[data-json-source]');
    var existingSources = targetEl.querySelectorAll('[data-json-source]');
    if (incomingSources.length === 1 && existingSources.length === 1) {
        var a = String(incomingSources[0].getAttribute('data-json-source') || '');
        var b = String(existingSources[0].getAttribute('data-json-source') || '');
        return a === b;
    }

    return innerHtmlNormalizedForStreamCompare(targetEl) === innerHtmlNormalizedForStreamCompare(scratch);
}

/**
 * Whether an incoming `replace` would swap in an equivalent subtree.
 * @param {Element} targetEl
 * @param {HTMLTemplateElement} templateEl
 * @returns {boolean}
 */
function isRedundantReplace(targetEl, templateEl) {
    var fragment = templateEl.content;
    if (!fragment || !fragment.childNodes.length) {
        return false;
    }
    var holder = document.createElement('div');
    holder.appendChild(fragment.cloneNode(true));
    var children = holder.children;
    if (children.length === 0) {
        return false;
    }
    if (children.length === 1) {
        return targetEl.outerHTML === children[0].outerHTML;
    }
    var incoming = '';
    var i;
    for (i = 0; i < children.length; i++) {
        incoming += children[i].outerHTML;
    }
    return targetEl.outerHTML === incoming;
}

/**
 * DOM node addressed by a turbo-stream's `target` attribute.
 * @param {Element} streamElement
 * @returns {Element|null}
 */
export function getTurboStreamTargetElement(streamElement) {
    if (!streamElement || !streamElement.getAttribute) {
        return null;
    }
    var targetAttr = String(streamElement.getAttribute('target') || '').trim();
    if (!targetAttr) {
        return null;
    }

    return resolveStreamTargetElement(targetAttr);
}

/**
 * True when applying this turbo-stream would be redundant (DOM already matches).
 * Only `update` and `replace` are evaluated; other actions return false.
 * @param {Element} streamElement
 * @returns {boolean}
 */
export function isStreamUpdateRedundant(streamElement) {
    var action = String(streamElement.getAttribute('action') || '').toLowerCase();
    if (action !== 'update' && action !== 'replace') {
        return false;
    }
    var templateEl = streamElement.querySelector('template');
    if (!templateEl || templateEl.tagName !== 'TEMPLATE') {
        return false;
    }
    var targetEl = getTurboStreamTargetElement(streamElement);
    if (!targetEl) {
        return false;
    }

    if (action === 'update') {
        return isRedundantUpdate(targetEl, templateEl);
    }

    return isRedundantReplace(targetEl, templateEl);
}

/**
 * Create a parser container for an element.
 * @param {Element} el
 * @returns {Element}
 */
function createParserContainerForElement(el) {
    var tag = el.tagName ? String(el.tagName).toUpperCase() : 'DIV';
    if (tag === 'TBODY' || tag === 'THEAD' || tag === 'TFOOT') {
        return document.createElement(tag);
    }
    if (tag === 'UL' || tag === 'OL') {
        return document.createElement(tag);
    }
    return document.createElement('DIV');
}

/**
 * Get the keyed direct children of a container.
 * @param {Element} container
 * @returns {Element[]}
 */
function getKeyedDirectChildren(container) {
    var out = [];
    var ch = container.children;
    var i;
    for (i = 0; i < ch.length; i++) {
        if (ch[i].nodeType === 1 && ch[i].hasAttribute('data-stream-row-id')) {
            out.push(ch[i]);
        }
    }
    return out;
}

/**
 * Get the inner HTML normalized for compare.
 * @param {HTMLTableCellElement} td
 * @returns {string}
 */
function cellInnerHtmlNormalizedForCompare(td) {
    var clone = td.cloneNode(true);
    var dt = clone.querySelectorAll('[data-datetime]');
    var i;
    for (i = 0; i < dt.length; i++) {
        dt[i].textContent = '-';
    }
    return clone.innerHTML;
}

/**
 * Includes host cell stream attributes so last_seen updates when only data-datetime changes.
 * @param {Element} td
 * @returns {string}
 */
function cellStreamEquivalenceSignature(td) {
    var norm = cellInnerHtmlNormalizedForCompare(td);
    if (td.hasAttribute('data-datetime')) {
        norm += '\0dt=' + String(td.getAttribute('data-datetime') || '');
    }
    if (td.hasAttribute('data-wait-seconds')) {
        norm += '\0ws=' + String(td.getAttribute('data-wait-seconds') || '');
    }
    return norm;
}

/**
 * Direct table cells (td/th) on a row with data-column-id. Avoids `:scope` on rows parsed
 * outside the document, which can return no matches and force a whole-row fallback.
 * @param {HTMLTableRowElement} tr
 * @returns {HTMLTableCellElement[]}
 */
function getDirectTableCellsWithColumnId(tr) {
    var out = [];
    var ch = tr.children;
    var i;
    for (i = 0; i < ch.length; i++) {
        var cell = ch[i];
        var tn = cell.tagName ? String(cell.tagName).toUpperCase() : '';
        if ((tn === 'TD' || tn === 'TH') && cell.hasAttribute('data-column-id')) {
            out.push(cell);
        }
    }
    return out;
}

/**
 * Find a direct table cell by column ID.
 * @param {HTMLTableRowElement} tr
 * @param {string} columnId
 * @returns {HTMLTableCellElement|null}
 */
function findDirectTableCellByColumnId(tr, columnId) {
    var cells = getDirectTableCellsWithColumnId(tr);
    var i;
    for (i = 0; i < cells.length; i++) {
        if (cells[i].getAttribute('data-column-id') === columnId) {
            return cells[i];
        }
    }
    return null;
}

/**
 * Merge table row cells.
 * @param {HTMLTableRowElement} existingRow
 * @param {HTMLTableRowElement} incomingRow
 * @returns {void}
 */
function mergeTableRowCells(existingRow, incomingRow) {
    var incomingTds = getDirectTableCellsWithColumnId(incomingRow);
    var j;
    for (j = 0; j < incomingTds.length; j++) {
        var itd = incomingTds[j];
        var col = itd.getAttribute('data-column-id');
        if (!col) {
            continue;
        }
        if (itd.hasAttribute('data-stream-preserve-client')) {
            continue;
        }
        var etd = findDirectTableCellByColumnId(existingRow, col);
        if (!etd) {
            continue;
        }
        if (etd.hasAttribute('data-stream-preserve-client')) {
            continue;
        }
        if (cellStreamEquivalenceSignature(etd) === cellStreamEquivalenceSignature(itd)) {
            continue;
        }
        etd.innerHTML = itd.innerHTML;
        var attrs = ['data-datetime', 'data-wait-seconds'];
        var a;
        for (a = 0; a < attrs.length; a++) {
            var attr = attrs[a];
            if (itd.hasAttribute(attr)) {
                etd.setAttribute(attr, String(itd.getAttribute(attr) || ''));
            } else {
                etd.removeAttribute(attr);
            }
        }
    }
}

/**
 * Merge generic keyed child.
 * @param {Element} existing
 * @param {Element} incoming
 * @returns {void}
 */
function mergeGenericKeyedChild(existing, incoming) {
    if (innerHtmlNormalizedForStreamCompare(existing) === innerHtmlNormalizedForStreamCompare(incoming)) {
        return;
    }
    existing.innerHTML = incoming.innerHTML;
    var attrs = ['data-datetime', 'data-wait-seconds'];
    var a;
    for (a = 0; a < attrs.length; a++) {
        var attr = attrs[a];
        if (incoming.hasAttribute(attr)) {
            existing.setAttribute(attr, String(incoming.getAttribute(attr) || ''));
        } else {
            existing.removeAttribute(attr);
        }
    }
}

/**
 * Merge keyed child.
 * @param {Element} existingChild
 * @param {Element} incomingChild
 * @returns {void}
 */
function mergeKeyedChild(existingChild, incomingChild) {
    const isExistingTableRow = existingChild && existingChild.tagName && String(existingChild.tagName).toUpperCase() === 'TR';
    const isIncomingTableRow = incomingChild && incomingChild.tagName && String(incomingChild.tagName).toUpperCase() === 'TR';
    if (isExistingTableRow && isIncomingTableRow) {
        mergeTableRowCells(existingChild, incomingChild);
        return;
    }
    mergeGenericKeyedChild(existingChild, incomingChild);
}

/**
 * Find a keyed direct child.
 * @param {Element} container
 * @param {string} rowId
 * @returns {Element|null}
 */
function findKeyedDirectChild(container, rowId) {
    var keyed = getKeyedDirectChildren(container);
    var i;
    for (i = 0; i < keyed.length; i++) {
        if (keyed[i].getAttribute('data-stream-row-id') === rowId) {
            return keyed[i];
        }
    }
    return null;
}

/**
 * Sync structural child by ID.
 * @param {Element} domChild
 * @param {Element} incChild
 * @returns {void}
 */
function syncStructuralChildById(domChild, incChild) {
    if (domChild.innerHTML !== incChild.innerHTML) {
        domChild.innerHTML = incChild.innerHTML;
    }
    if (incChild.hasAttribute('style')) {
        var ns = incChild.getAttribute('style') || '';
        if (domChild.getAttribute('style') !== ns) {
            domChild.setAttribute('style', ns);
        }
    } else if (domChild.hasAttribute('style')) {
        domChild.removeAttribute('style');
    }
}

/**
 * When the stream target opts in, patch keyed direct children in place instead of morphing the whole subtree.
 * Works for tbody, div lists, ul/ol, etc., as long as the server sends the same set of `data-stream-row-id` values.
 * @param {Element} streamElement
 * @returns {boolean} true when the default turbo-stream render must be skipped
 */
export function tryApplyIncrementalStreamPatch(streamElement) {
    var action = String(streamElement.getAttribute('action') || '').toLowerCase();
    var method = String(streamElement.getAttribute('method') || '').toLowerCase();
    if (action !== 'update' || method !== 'morph') {
        return false;
    }
    var templateEl = streamElement.querySelector('template');
    if (!templateEl || templateEl.tagName !== 'TEMPLATE') {
        return false;
    }
    var targetEl = getTurboStreamTargetElement(streamElement);
    if (!targetEl || !targetEl.hasAttribute('data-turbo-stream-patch-children')) {
        return false;
    }

    var holder = createParserContainerForElement(targetEl);
    holder.innerHTML = templateEl.innerHTML;

    var incomingKeyed = getKeyedDirectChildren(holder);
    if (incomingKeyed.length === 0) {
        return false;
    }

    var incomingKeySet = new Set();
    var i;
    for (i = 0; i < incomingKeyed.length; i++) {
        var rk = incomingKeyed[i].getAttribute('data-stream-row-id');
        if (rk) {
            incomingKeySet.add(rk);
        }
    }
    if (incomingKeySet.size !== incomingKeyed.length) {
        return false;
    }

    var existingKeyed = getKeyedDirectChildren(targetEl);
    var existingKeySet = new Set();
    for (i = 0; i < existingKeyed.length; i++) {
        var ek = existingKeyed[i].getAttribute('data-stream-row-id');
        if (ek) {
            existingKeySet.add(ek);
        }
    }

    if (incomingKeySet.size !== existingKeySet.size) {
        return false;
    }
    for (var k of incomingKeySet) {
        if (!existingKeySet.has(k)) {
            return false;
        }
    }

    var ch = holder.children;
    for (i = 0; i < ch.length; i++) {
        var node = ch[i];
        if (node.nodeType !== 1 || node.hasAttribute('data-stream-row-id')) {
            continue;
        }
        var sid = node.getAttribute('id');
        if (!sid) {
            continue;
        }
        var domChild = document.getElementById(sid);
        if (!domChild || !targetEl.contains(domChild)) {
            continue;
        }
        syncStructuralChildById(domChild, node);
    }

    for (i = 0; i < incomingKeyed.length; i++) {
        var incChild = incomingKeyed[i];
        var rowId = incChild.getAttribute('data-stream-row-id');
        if (!rowId) {
            continue;
        }
        var curChild = findKeyedDirectChild(targetEl, rowId);
        if (!curChild) {
            return false;
        }
        mergeKeyedChild(curChild, incChild);
    }

    return true;
}

/**
 * Render turbo stream with guards.
 * @param {Element} streamElement
 * @param {function(Element): void} originalRender
 * @returns {'patched'|'skipped'|'rendered'}
 */
export function renderTurboStreamWithGuards(streamElement, originalRender) {
    preserveJobsSectionsOpenState(streamElement);
    if (tryApplyIncrementalStreamPatch(streamElement)) {
        return 'patched';
    }
    if (isStreamUpdateRedundant(streamElement)) {
        return 'skipped';
    }
    originalRender(streamElement);
    return 'rendered';
}
