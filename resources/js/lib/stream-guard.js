/**
 * Resolve the DOM node a turbo-stream targets (id or CSS selector).
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
    if (!streamElement || !streamElement.getAttribute) {
        return false;
    }
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
