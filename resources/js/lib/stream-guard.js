/**
 * Core Turbo Stream handling for the Horizon Hub SSE pipeline:
 * target resolution, redundant update/replace skipping, and incremental patching
 * of keyed direct children (tables, lists, or any opt-in container).
 *
 * Table rows: only direct td/th with data-column-id are compared and updated.
 * Use data-stream-preserve-client on a cell to keep the live DOM (e.g. CSRF tokens in forms).
 * Mark any subtree that client JS rewrites after load (e.g. JSON tree) with data-stream-preserve-client
 */

import { parseJson } from "./parse";

/**
 * DOM node addressed by a turbo-stream's `target` attribute.
 * @param {Element} streamElement
 * @returns {Element|null}
 */
export function getTurboStreamTargetElement(streamElement) {
    var targetAttr = String(streamElement.getAttribute('target') || '').trim();
    if (!targetAttr) {
        return null;
    }

    var raw = String(targetAttr).trim();
    if (!raw) {
        return null;
    }
    var targetId = raw.charAt(0) === '#' ? raw.slice(1) : raw;
    var byId = document.getElementById(targetId);
    if (byId) {
        return byId;
    }

    return document.querySelector(targetId);
}

/**
 * Render turbo stream with guards.
 * @param {Element} streamElement
 * @param {function(Element): void} originalRender
 * @returns {'incremental-changed'|'incremental-unchanged'|'skipped'|'rendered'}
 */
export function renderTurboStreamWithGuards(streamElement, originalRender) {
    preserveJobsSectionsOpenState(streamElement);
    var patchOutcome = tryApplyIncrementalStreamPatch(streamElement);
    if (patchOutcome === 'incremental-changed' || patchOutcome === 'incremental-unchanged') {
        return patchOutcome;
    }
    if (isStreamUpdateRedundant(streamElement)) {
        return 'skipped';
    }
    originalRender(streamElement);
    return 'rendered';
}

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

    var openBySection = {};
    try {
        var raw = window.localStorage ? window.localStorage.getItem('horizon_jobs_sections') : null;
        var parsed = parseJson(raw);
        if (parsed && typeof parsed === 'object') {
            Object.keys(parsed).forEach(function (key) {
                openBySection[String(key)] = !!parsed[key];
            });
        }
    } catch (e) {
    }

    var liveDetails = liveStack.querySelectorAll('details[data-section-key]');
    liveDetails.forEach(function (el) {
        var key = String(el.getAttribute('data-section-key') || '').trim();
        if (!key || typeof openBySection[key] === 'boolean') {
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
 * Remove whitespace-only text nodes so Blade/indented HTML compares to live DOM consistently.
 * @param {Node} node
 * @returns {void}
 */
function stripWhitespaceOnlyTextNodes(node) {
    var child = node.firstChild;
    while (child) {
        var next = child.nextSibling;
        if (child.nodeType === Node.TEXT_NODE) {
            if (/^\s*$/.test(String(child.textContent || ''))) {
                node.removeChild(child);
            }
        } else if (child.nodeType === Node.ELEMENT_NODE) {
            stripWhitespaceOnlyTextNodes(child);
        }
        child = next;
    }
}

/**
 * Clone subtree, drop client-expanded bodies under [data-stream-preserve-client], strip ignorable whitespace.
 * @param {Element} el
 * @returns {Element}
 */
function cloneNormalizedForStreamCompare(el) {
    var clone = el.cloneNode(true);
    clone.querySelectorAll('[data-stream-preserve-client]').forEach(function (host) {
        host.innerHTML = '';
    });
    stripWhitespaceOnlyTextNodes(clone);
    return clone;
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

    var wrap = document.createElement('div');
    wrap.innerHTML = incomingHtml;
    return (
        cloneNormalizedForStreamCompare(targetEl).innerHTML === cloneNormalizedForStreamCompare(wrap).innerHTML
    );
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
        var onlyChild = children[0];
        return (
            cloneNormalizedForStreamCompare(targetEl).outerHTML ===
            cloneNormalizedForStreamCompare(onlyChild).outerHTML
        );
    }
    var incoming = '';
    var i;
    for (i = 0; i < children.length; i++) {
        incoming += cloneNormalizedForStreamCompare(children[i]).outerHTML;
    }
    return cloneNormalizedForStreamCompare(targetEl).outerHTML === incoming;
}

/**
 * True when applying this turbo-stream would be redundant (DOM already matches).
 * Only `update` and `replace` are evaluated; other actions return false.
 * @param {Element} streamElement
 * @returns {boolean}
 */
function isStreamUpdateRedundant(streamElement) {
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
    var changed = false;
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
        if (String(etd.getAttribute('data-wait-seconds')) === String(itd.getAttribute('data-wait-seconds'))) {
            continue;
        }
        mergeGenericKeyedChild(etd, itd);
        changed = true;
    }
    return changed;
}

/**
 * Merge generic keyed child.
 * @param {Element} existing
 * @param {Element} incoming
 * @returns {void}
 */
function mergeGenericKeyedChild(existing, incoming) {
    existing.innerHTML = incoming.innerHTML;
    var attr = 'data-wait-seconds'
    if (incoming.hasAttribute(attr)) {
        existing.setAttribute(attr, String(incoming.getAttribute(attr)));
    } else {
        existing.removeAttribute(attr);
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
        return mergeTableRowCells(existingChild, incomingChild);
    }
    return mergeGenericKeyedChild(existingChild, incomingChild);
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
    var changed = false;
    if (domChild.innerHTML !== incChild.innerHTML) {
        domChild.innerHTML = incChild.innerHTML;
        changed = true;
    }
    if (incChild.hasAttribute('style')) {
        var ns = incChild.getAttribute('style') || '';
        if (domChild.getAttribute('style') !== ns) {
            domChild.setAttribute('style', ns);
            changed = true;
        }
    } else if (domChild.hasAttribute('style')) {
        domChild.removeAttribute('style');
        changed = true;
    }
    return changed;
}

/**
 * When the stream target opts in, patch keyed direct children in place instead of morphing the whole subtree.
 * Works for tbody, div lists, ul/ol, etc., as long as the server sends the same set of `data-stream-row-id` values.
 * @param {Element} streamElement
 * @returns {false|'incremental-unchanged'|'incremental-changed'}
 */
function tryApplyIncrementalStreamPatch(streamElement) {
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

    var anyChanged = false;

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
        if (syncStructuralChildById(domChild, node)) {
            anyChanged = true;
        }
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
        if (mergeKeyedChild(curChild, incChild)) {
            anyChanged = true;
        }
    }

    return anyChanged ? 'incremental-changed' : 'incremental-unchanged';
}
