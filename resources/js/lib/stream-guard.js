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

import { LAST_SEEN_AT_ATTR, WAIT_SECONDS_ATTR } from "./datetime-format";

/**
 * Attribute name for the stream signature.
 * @type {string}
 */
const STREAM_SIG_ATTR = 'data-horizon-stream-sig';

/**
 * Attribute name for the stream patch children flag.
 * @type {string}
 */
const STREAM_PATCH_CHILDREN_ATTR = 'data-turbo-stream-patch-children';

/**
 * Attribute name for the stream row id.
 * @type {string}
 */
const STREAM_ROW_ID_ATTR = 'data-stream-row-id';

/**
 * Attribute name for the stream column id.
 * @type {string}
 */
const STREAM_COLUMN_ID_ATTR = 'data-column-id';

/**
 * Attribute name for the stream preserve client flag.
 * @type {string}
 */
const STREAM_PRESERVE_CLIENT_ATTR = 'data-stream-preserve-client';

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
    var templateEl = streamElement.querySelector('template');
    var targetEl = templateEl
        ? getTurboStreamTargetElement(streamElement)
        : null;
    var patchable = !!targetEl
        && String(streamElement.getAttribute('action') || '').toLowerCase() === 'update'
        && String(streamElement.getAttribute('method') || '').toLowerCase() === 'morph'
        && targetEl.hasAttribute(STREAM_PATCH_CHILDREN_ATTR);

    if (patchable) {
        var holder = document.createElement(String(targetEl.tagName).toUpperCase() || 'DIV');
        holder.innerHTML = templateEl.innerHTML;

        var incomingKeyed = collectDirectKeyed(holder, STREAM_ROW_ID_ATTR).list;
        var existingKeyed = collectDirectKeyed(targetEl, STREAM_ROW_ID_ATTR).list;

        if (rowIdsAreValidAndMatching(existingKeyed, incomingKeyed)) {
            var anyChanged = false;
            var existingByRowId = collectDirectKeyed(targetEl, STREAM_ROW_ID_ATTR).map;
            var allMatched = true;
            for (let r = 0; r < incomingKeyed.length; r++) {
                var existingChild = existingByRowId.get(incomingKeyed[r].getAttribute(STREAM_ROW_ID_ATTR));
                if (!existingChild) {
                    allMatched = false;
                    break;
                }
                if (mergeKeyedChild(existingChild, incomingKeyed[r])) {
                    anyChanged = true;
                }
            }
            if (allMatched) {
                return anyChanged ? 'incremental-changed' : 'incremental-unchanged';
            }
        }
    }

    originalRender(streamElement);
    return 'rendered';
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
        if (parent.children[i].nodeType !== 1 || isKeyedChild && !isKeyedChild(parent.children[i])) {
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
    if (!existing || !incoming) {
        return false;
    }
    if (incoming.hasAttribute(STREAM_PRESERVE_CLIENT_ATTR) || existing.hasAttribute(STREAM_PRESERVE_CLIENT_ATTR)) {
        return false;
    }
    const incomingSig = incoming.getAttribute(STREAM_SIG_ATTR);
    const existingSig = existing.getAttribute(STREAM_SIG_ATTR);
    if (incomingSig !== '' && incomingSig === existingSig) {
        return false;
    }

    var staged = incoming.cloneNode(true); // Clone incoming to avoid modifying the original
    var existingHosts = existing.querySelectorAll('[' + STREAM_PRESERVE_CLIENT_ATTR + ']');
    var stagedHosts = staged.querySelectorAll('[' + STREAM_PRESERVE_CLIENT_ATTR + ']');
    for (let i = 0; i < existingHosts.length; i++) {
        if (stagedHosts[i]) {
            stagedHosts[i].replaceWith(existingHosts[i].cloneNode(true));
        }
    }

    var datetimeAttrs = [LAST_SEEN_AT_ATTR, WAIT_SECONDS_ATTR];
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
 * Merge keyed child.
 * @param {Element} existingChild
 * @param {Element} incomingChild
 * @returns {boolean}
 */
function mergeKeyedChild(existingChild, incomingChild) {
    var tag = existingChild.tagName ? String(existingChild.tagName).toUpperCase() : '';
    if (tag === 'TR' && String(incomingChild.tagName || '').toUpperCase() === 'TR') {
        function isDirectTableCellWithColumnId(node) {
            var tn = node.tagName ? String(node.tagName).toUpperCase() : '';
            return (tn === 'TD' || tn === 'TH') && node.hasAttribute(STREAM_COLUMN_ID_ATTR);
        }
        var existingByColumnId = collectDirectKeyed(existingChild, STREAM_COLUMN_ID_ATTR, isDirectTableCellWithColumnId).map;
        var incomingCells = collectDirectKeyed(incomingChild, STREAM_COLUMN_ID_ATTR, isDirectTableCellWithColumnId).list;
        var cellsChanged = false;
        for (let i = 0; i < incomingCells.length; i++) {
            var existingCell = existingByColumnId.get(incomingCells[i].getAttribute(STREAM_COLUMN_ID_ATTR));
            if (mergePreservedSubtree(existingCell, incomingCells[i])) {
                cellsChanged = true;
            }
        }
        return cellsChanged;
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
        var id = keyedChildren[i].getAttribute(STREAM_ROW_ID_ATTR);
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
        incomingIds.add(incomingKeyed[i].getAttribute(STREAM_ROW_ID_ATTR));
    }
    for (let j = 0; j < existingKeyed.length; j++) {
        if (!incomingIds.has(existingKeyed[j].getAttribute(STREAM_ROW_ID_ATTR))) {
            return false;
        }
    }
    return true;
}
