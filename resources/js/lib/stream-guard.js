/**
 * Turbo Stream guards for the Horizon Hub SSE pipeline.
 *
 * Contract (server + markup):
 * 1. PHP omits unchanged turbo-stream payloads per target (StreamController fingerprints).
 * 2. List/table targets use data-turbo-stream-patch-children + stable data-stream-row-id on direct children.
 * 3. Client-owned UI inside streamed rows uses data-stream-preserve-client.
 *
 * Client flow: incremental row patch when opted-in → otherwise Turbo render (unchanged payloads omitted in PHP).
 */

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
 * @returns {'incremental-changed'|'incremental-unchanged'|'rendered'}
 */
export function renderTurboStreamWithGuards(streamElement, originalRender) {
    var patchOutcome = tryApplyIncrementalStreamPatch(streamElement);
    if (patchOutcome === 'incremental-changed' || patchOutcome === 'incremental-unchanged') {
        return patchOutcome;
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
 * Normalize markup for stream comparison.
 * @param {Element} el
 * @returns {string}
 */
function streamSig(el) {
    return String(el.getAttribute('data-horizon-stream-sig') || '');
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
    if (streamSig(incoming) !== '' && streamSig(incoming) === streamSig(existing)) {
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
 * Try to apply an incremental stream patch to a stream element.
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
