/**
 * Turbo Stream guards for the Horizon Hub SSE pipeline:
 * skip no-op update/replace, incremental merge of keyed direct children, and
 * client-owned subtree preservation.
 *
 * Opt-in incremental targets: data-turbo-stream-patch-children + update/morph.
 * Keyed rows/lists: data-stream-row-id on direct children.
 * Table rows: direct td/th with data-column-id (same merge policy as generic nodes).
 * data-stream-preserve-client, data-horizon-stream-sig, and datetime attrs follow one merge path.
 */

import { parseJson } from "./parse";

/**
 * DOM node addressed by a turbo-stream's `target` attribute.
 * @param {Element} streamElement
 * @returns {Element|null}
 */
export function getTurboStreamTargetElement(streamElement) {
    var targetId = String(streamElement.getAttribute('target') || '').trim();
    if (!targetId) {
        return null;
    }
    if (targetId.charAt(0) === '#') {
        targetId = targetId.slice(1);
    }
    return document.getElementById(targetId) || document.querySelector(targetId);
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
    if (isStreamApplyRedundant(streamElement)) {
        return 'skipped';
    }
    originalRender(streamElement);
    return 'rendered';
}

/**
 * Read the context of a turbo stream element.
 * @param {Element} streamElement
 * @returns {{ action: string, method: string, targetEl: Element, templateEl: HTMLTemplateElement }|null}
 */
function readStreamContext(streamElement) {
    var templateEl = streamElement.querySelector('template');
    if (!templateEl || templateEl.tagName !== 'TEMPLATE') {
        return null;
    }
    var targetEl = getTurboStreamTargetElement(streamElement);
    if (!targetEl) {
        return null;
    }
    return {
        action: String(streamElement.getAttribute('action') || '').toLowerCase(),
        method: String(streamElement.getAttribute('method') || '').toLowerCase(),
        targetEl: targetEl,
        templateEl: templateEl,
    };
}

/**
 * Preserve open/closed state for jobs stack sections across replace/update streams.
 * @param {Element} streamElement
 * @returns {void}
 */
function preserveJobsSectionsOpenState(streamElement) {
    if (String(streamElement.getAttribute('target') || '').trim() !== 'horizon-jobs-stack') {
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
        var parsed = parseJson(window.localStorage ? window.localStorage.getItem('horizon_jobs_sections') : null);
        if (parsed && typeof parsed === 'object') {
            Object.keys(parsed).forEach(function (key) {
                openBySection[String(key)] = !!parsed[key];
            });
        }
    } catch (_e) {
    }

    liveStack.querySelectorAll('details[data-section-key]').forEach(function (el) {
        var key = String(el.getAttribute('data-section-key') || '').trim();
        if (key && typeof openBySection[key] !== 'boolean') {
            openBySection[key] = !!el.open;
        }
    });

    var holder = document.createElement('div');
    holder.innerHTML = templateEl.innerHTML;
    holder.querySelectorAll('details[data-section-key]').forEach(function (el) {
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
 * Clone subtree, drop client-expanded bodies under [data-stream-preserve-client], trim application/json script bodies, strip ignorable whitespace.
 * @param {Element} el
 * @returns {Element}
 */
function cloneNormalizedForStreamCompare(el) {
    var clone = el.cloneNode(true);
    clone.querySelectorAll('[data-stream-preserve-client]').forEach(function (host) {
        host.innerHTML = '';
    });
    if (String(clone.tagName || '').toUpperCase() === 'SCRIPT' && String(clone.getAttribute('type') || '') === 'application/json') {
        clone.textContent = String(clone.textContent || '').trim();
    }
    clone.querySelectorAll('script[type="application/json"]').forEach(function (script) {
        script.textContent = String(script.textContent || '').trim();
    });
    stripWhitespaceOnlyTextNodes(clone);
    return clone;
}

/**
 * Normalize markup for stream comparison.
 * @param {Element} el
 * @param {'inner'|'outer'} mode
 * @returns {string}
 */
function normalizedMarkup(el, mode) {
    var normalized = cloneNormalizedForStreamCompare(el);
    return mode === 'outer' ? normalized.outerHTML : normalized.innerHTML;
}

/**
 * Get the stream sig on an element.
 * @param {Element} el
 * @param {boolean} includeDescendants
 * @returns {string}
 */
function streamSig(el, includeDescendants) {
    var sig = String(el.getAttribute('data-horizon-stream-sig') || '');
    if (sig !== '' || !includeDescendants) {
        return sig;
    }
    var found = el.querySelector('[data-horizon-stream-sig]');
    return found ? String(found.getAttribute('data-horizon-stream-sig') || '') : '';
}

/**
 * Compare two subtrees to check if they are equivalent.
 * @param {Element} liveEl
 * @param {Element} incomingEl
 * @param {'inner'|'outer'} mode
 * @param {boolean} checkSubtreeSig
 * @returns {boolean}
 */
function subtreeAlreadyMatches(liveEl, incomingEl, mode, checkSubtreeSig) {
    var sigLive = streamSig(liveEl, checkSubtreeSig);
    var sigIncoming = streamSig(incomingEl, checkSubtreeSig);
    if (sigLive !== '' && sigLive === sigIncoming) {
        return true;
    }
    return normalizedMarkup(liveEl, mode) === normalizedMarkup(incomingEl, mode);
}

/**
 * Check if a stream apply is redundant.
 * @param {Element} streamElement
 * @returns {boolean}
 */
function isStreamApplyRedundant(streamElement) {
    var ctx = readStreamContext(streamElement);
    if (!ctx || (ctx.action !== 'update' && ctx.action !== 'replace')) {
        return false;
    }

    if (ctx.action === 'update') {
        if (ctx.targetEl.innerHTML === ctx.templateEl.innerHTML) {
            return true;
        }
        var scratch = document.createElement('div');
        scratch.innerHTML = ctx.templateEl.innerHTML;
        return subtreeAlreadyMatches(ctx.targetEl, scratch, 'inner', true);
    }

    var fragment = ctx.templateEl.content;
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
        return subtreeAlreadyMatches(ctx.targetEl, children[0], 'outer', false);
    }
    var incoming = '';
    for (let i = 0; i < children.length; i++) {
        incoming += normalizedMarkup(children[i], 'outer');
    }
    return normalizedMarkup(ctx.targetEl, 'outer') === incoming;
}

/**
 * Create a container element for parsing a stream template.
 * @param {Element} el
 * @returns {Element}
 */
function createParserContainerForElement(el) {
    var tag = el.tagName ? String(el.tagName).toUpperCase() : 'DIV';
    if (tag === 'TBODY' || tag === 'THEAD' || tag === 'TFOOT' || tag === 'UL' || tag === 'OL') {
        return document.createElement(tag);
    }
    return document.createElement('DIV');
}

/**
 * Map direct children by key attribute.
 * @param {Element} parent
 * @param {string} keyAttr
 * @param {function(Element): boolean} [isKeyedChild]
 * @returns {{ list: Element[], map: Map<string, Element> }}
 */
function collectDirectKeyed(parent, keyAttr, isKeyedChild) {
    var list = [];
    var map = new Map();
    for (let i = 0; i < parent.children.length; i++) {
        if (parent.children[i].nodeType !== 1 || (isKeyedChild && !isKeyedChild(parent.children[i])) || !parent.children[i].hasAttribute(keyAttr)) {
            continue;
        }
        var key = parent.children[i].getAttribute(keyAttr);
        if (!key) {
            continue;
        }
        list.push(parent.children[i]);
        map.set(key, parent.children[i]);
    }
    return { list: list, map: map };
}

/**
 * Apply incoming markup onto an existing node while preserving client-owned bits.
 * @param {Element} existing
 * @param {Element} incoming
 * @returns {boolean}
 */
function mergePreservedSubtree(existing, incoming) {
    if (incoming.hasAttribute('data-stream-preserve-client') || existing.hasAttribute('data-stream-preserve-client')) {
        return false;
    }
    if (streamSig(incoming, false) !== '' && streamSig(incoming, false) === streamSig(existing, false)) {
        return false;
    }

    var staged = incoming.cloneNode(true); // Clone incoming to avoid modifying the original
    var existingHosts = existing.querySelectorAll('[data-stream-preserve-client]');
    var stagedHosts = staged.querySelectorAll('[data-stream-preserve-client]');
    for (let i = 0; i < existingHosts.length; i++) {
        if (stagedHosts[i]) {
            stagedHosts[i].replaceWith(existingHosts[i].cloneNode(true));
        }
    }

    var datetimeAttrs = ['data-last-seen-at', 'data-wait-seconds'];
    for (let a = 0; a < datetimeAttrs.length; a++) {
        var existingNodes = existing.querySelectorAll('[' + datetimeAttrs[a] + ']');
        var stagedNodes = staged.querySelectorAll('[' + datetimeAttrs[a] + ']');
        for (let n = 0; n < existingNodes.length && n < stagedNodes.length; n++) {
            if (String(existingNodes[n].getAttribute(datetimeAttrs[a]) || '') !== String(stagedNodes[n].getAttribute(datetimeAttrs[a]) || '')) {
                continue;
            }
            if (String(existingNodes[n].textContent || '').trim() === '') {
                continue;
            }
            stagedNodes[n].textContent = existingNodes[n].textContent;
        }
    }
    if (existing.innerHTML === staged.innerHTML) {
        return false;
    }
    existing.innerHTML = staged.innerHTML;
    return true;
}

/**
 * Merge pairs of direct children that share the same key attribute.
 * @param {Element} existingParent
 * @param {Element} incomingParent
 * @param {string} keyAttr
 * @returns {boolean}
 */
function mergeDirectChildrenByKey(existingParent, incomingParent, keyAttr) {
    function isDirectTableCellWithColumnId(node) {
        var tn = node.tagName ? String(node.tagName).toUpperCase() : '';
        return (tn === 'TD' || tn === 'TH') && node.hasAttribute('data-column-id');
    }
    var existingByKey = collectDirectKeyed(existingParent, keyAttr, isDirectTableCellWithColumnId).map;
    var incomingList = collectDirectKeyed(incomingParent, keyAttr, isDirectTableCellWithColumnId).list;
    var changed = false;
    for (let i = 0; i < incomingList.length; i++) {
        var existingChild = existingByKey.get(incomingList[i].getAttribute(keyAttr));
        if (existingChild && mergePreservedSubtree(existingChild, incomingList[i])) {
            changed = true;
        }
    }
    return changed;
}

/**
 * Merge keyed child.
 * @param {Element} existingChild
 * @param {Element} incomingChild
 * @returns {boolean}
 */
function mergeKeyedChild(existingChild, incomingChild) {
    var tag = existingChild.tagName ? String(existingChild.tagName).toUpperCase() : '';
    if (tag === 'TR' && String(incomingChild.tagName || '').toUpperCase() === 'TR') {
        return mergeDirectChildrenByKey(existingChild, incomingChild, 'data-column-id');
    }
    return mergePreservedSubtree(existingChild, incomingChild);
}

/**
 * @param {Element[]} keyedChildren
 * @returns {boolean}
 */
function hasUniqueRowIds(keyedChildren) {
    var seen = new Set();
    for (let i = 0; i < keyedChildren.length; i++) {
        var id = keyedChildren[i].getAttribute('data-stream-row-id');
        if (!id || seen.has(id)) {
            return false;
        }
        seen.add(id);
    }
    return true;
}

/**
 * @param {Element[]} existingKeyed
 * @param {Element[]} incomingKeyed
 * @returns {boolean}
 */
function rowIdsAreValidAndMatching(existingKeyed, incomingKeyed) {
    if (existingKeyed.length !== incomingKeyed.length || existingKeyed.length === 0) {
        return false;
    }
    if (!hasUniqueRowIds(incomingKeyed) || !hasUniqueRowIds(existingKeyed)) {
        return false;
    }
    var incomingIds = new Set();
    for (let i = 0; i < incomingKeyed.length; i++) {
        incomingIds.add(incomingKeyed[i].getAttribute('data-stream-row-id'));
    }
    for (let j = 0; j < existingKeyed.length; j++) {
        if (!incomingIds.has(existingKeyed[j].getAttribute('data-stream-row-id'))) {
            return false;
        }
    }
    return true;
}

/**
 * Sync structural child by ID.
 * @param {Element} domChild
 * @param {Element} incChild
 * @returns {boolean}
 */
function syncStructuralChildById(domChild, incChild) {
    var changed = false;
    if (domChild.innerHTML !== incChild.innerHTML) {
        domChild.innerHTML = incChild.innerHTML;
        changed = true;
    }
    if (incChild.hasAttribute('style')) {
        var style = incChild.getAttribute('style') || '';
        if (domChild.getAttribute('style') !== style) {
            domChild.setAttribute('style', style);
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
    var ctx = readStreamContext(streamElement);
    if (!ctx || ctx.action !== 'update' || ctx.method !== 'morph') {
        return false;
    }
    if (!ctx.targetEl.hasAttribute('data-turbo-stream-patch-children')) {
        return false;
    }

    var holder = createParserContainerForElement(ctx.targetEl);
    holder.innerHTML = ctx.templateEl.innerHTML;

    var incomingKeyed = collectDirectKeyed(holder, 'data-stream-row-id').list;
    var existingKeyed = collectDirectKeyed(ctx.targetEl, 'data-stream-row-id').list;
    if (!rowIdsAreValidAndMatching(existingKeyed, incomingKeyed)) {
        return false;
    }

    var anyChanged = false;

    for (let i = 0; i < holder.children.length; i++) {
        if (holder.children[i].nodeType !== 1 || holder.children[i].hasAttribute('data-stream-row-id')) {
            continue;
        }
        var domChild = document.getElementById(holder.children[i].getAttribute('id'));
        if (!domChild || !ctx.targetEl.contains(domChild)) {
            continue;
        }
        if (syncStructuralChildById(domChild, holder.children[i])) {
            anyChanged = true;
        }
    }

    var existingByRowId = collectDirectKeyed(ctx.targetEl, 'data-stream-row-id').map;
    for (let r = 0; r < incomingKeyed.length; r++) {
        var existingChild = existingByRowId.get(incomingKeyed[r].getAttribute('data-stream-row-id'));
        if (!existingChild) {
            return false;
        }
        if (mergeKeyedChild(existingChild, incomingKeyed[r])) {
            anyChanged = true;
        }
    }

    return anyChanged ? 'incremental-changed' : 'incremental-unchanged';
}
