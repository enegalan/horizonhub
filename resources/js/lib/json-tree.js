import { decodeHtmlEntities } from './dom';
import hljs from 'highlight.js/lib/core';
import jsonLanguage from 'highlight.js/lib/languages/json';

hljs.registerLanguage('json', jsonLanguage);

/**
 * Cache raw JSON source when the attribute is present so re-renders (HMR, etc.)
 * still work if the DOM attribute is missing or trimmed.
 * @type {WeakMap<HTMLElement, string>}
 */
var jsonTreeSourceCache = new WeakMap();

/**
 * Last JSON source string used to render the tree for a target.
 * @type {WeakMap<HTMLElement, string>}
 */
var jsonTreeLastRenderedSource = new WeakMap();

/**
 * Read JSON source from data-json-source or the last cached value for this element.
 * @param {HTMLElement} target
 * @returns {string}
 */
function getJsonTreeSource(target) {
    if (!target || !target.getAttribute) return '';
    var fromAttr = target.getAttribute('data-json-source');
    if (typeof fromAttr === 'string' && fromAttr !== '') {
        jsonTreeSourceCache.set(target, fromAttr);
        return fromAttr;
    }
    var cached = jsonTreeSourceCache.get(target);
    return typeof cached === 'string' ? cached : '';
}

/**
 * Check whether a string can represent JSON input.
 * @param {string} value
 * @returns {boolean}
 */
function startsLikeJson(value) {
    return value.startsWith('{') || value.startsWith('[') || value.startsWith('"');
}

/**
 * Parse and unwrap JSON candidates iteratively.
 * @param {string} initialCandidate
 * @param {(parsed: unknown, currentCandidate: string) => unknown} onParsedValue
 * @param {(currentCandidate: string) => unknown} onCycle
 * @param {(currentCandidate: string) => unknown} onParseError
 * @returns {unknown}
 */
function parseJsonCandidateLoop(initialCandidate, onParsedValue, onCycle, onParseError) {
    var candidate = initialCandidate;
    var seen = new Set();

    for (;;) {
        if (seen.has(candidate)) {
            return onCycle(candidate);
        }
        seen.add(candidate);

        try {
            var parsed = JSON.parse(candidate);
            var next = onParsedValue(parsed, candidate);
            if (typeof next === 'string') {
                candidate = next;
                continue;
            }
            return next;
        } catch (error) {
            return onParseError(candidate);
        }
    }
}

/**
 * Parse JSON sources that can arrive escaped/encoded.
 * @param {string} rawSource
 * @returns {unknown}
 */
function parseJsonSource(rawSource) {
    var candidate = decodeHtmlEntities(String(rawSource)).trim();

    return parseJsonCandidateLoop(candidate, function (parsed) {
        if (typeof parsed !== 'string') {
            return parsed;
        }

        var nextCandidate = decodeHtmlEntities(parsed).trim();
        if (!nextCandidate) {
            return '';
        }

        if (!startsLikeJson(nextCandidate)) {
            return parsed;
        }

        return nextCandidate;
    }, function (currentCandidate) {
        return currentCandidate;
    }, function (currentCandidate) {
        return currentCandidate;
    });
}

/**
 * Parse embedded JSON strings when they represent containers.
 * @param {unknown} value
 * @returns {unknown}
 */
function parseEmbeddedJsonContainer(value) {
    if (typeof value !== 'string') {
        return value;
    }

    var candidate = decodeHtmlEntities(value).trim();
    if (!candidate) {
        return value;
    }

    if (!startsLikeJson(candidate)) {
        return value;
    }

    return parseJsonCandidateLoop(candidate, function (parsed) {
        if (parsed !== null && typeof parsed === 'object') {
            return parsed;
        }

        if (typeof parsed !== 'string') {
            return value;
        }

        var nextCandidate = decodeHtmlEntities(parsed).trim();
        if (!nextCandidate) {
            return value;
        }

        if (!startsLikeJson(nextCandidate)) {
            return value;
        }

        return nextCandidate;
    }, function () {
        return value;
    }, function () {
        return value;
    });
}

/**
 * Highlight JSON inline value.
 * @param {unknown} value
 * @returns {string}
 */
function highlightJsonValue(value) {
    var jsonValue;
    try {
        jsonValue = JSON.stringify(value);
    } catch (error) {
        jsonValue = JSON.stringify(String(value));
    }

    if (typeof jsonValue !== 'string') {
        jsonValue = 'null';
    }

    return hljs.highlight(jsonValue, { language: 'json' }).value;
}

/**
 * Normalize string values for readable tree output.
 * @param {string} value
 * @returns {string}
 */
function normalizeStringValueForDisplay(value) {
    var normalized = String(value).replace(/\r\n|\r/g, '\n');
    if (normalized.indexOf('\n') === -1) {
        return normalized;
    }

    return normalized
        .replace(/\n+/g, ' ')
        .replace(/\t+/g, ' ');
}

/**
 * Build a JSON key span.
 * @param {string} key
 * @returns {HTMLElement}
 */
function buildJsonKey(key) {
    var keyEl = document.createElement('span');
    keyEl.className = 'horizon-json-key';
    keyEl.innerHTML = hljs.highlight(JSON.stringify(key), { language: 'json' }).value;

    return keyEl;
}

/**
 * Build a primitive JSON value span.
 * @param {unknown} value
 * @returns {HTMLElement}
 */
function buildJsonPrimitive(value) {
    var valueEl = document.createElement('span');
    if (typeof value === 'string') {
        value = normalizeStringValueForDisplay(value);
    }
    value = highlightJsonValue(value);
    valueEl.innerHTML = value;
    valueEl.className = 'horizon-json-value';
    return valueEl;
}

/**
 * Build a JSON node recursively.
 * @param {string|null} key
 * @param {unknown} value
 * @param {string[]} pathSegments
 * @param {{ isCollapsed: function(string): boolean, setCollapsed: function(string, boolean): void }|null} state
 * @param {boolean=} isLast
 * @returns {HTMLElement}
 */
function buildJsonNode(key, value, pathSegments, state, isLast) {
    var wrapper = document.createElement('div');
    wrapper.className = 'horizon-json-node';

    var resolvedValue = parseEmbeddedJsonContainer(value);
    var isArray = Array.isArray(resolvedValue);
    var isObject = resolvedValue !== null && typeof resolvedValue === 'object' && !isArray;
    var isContainer = isArray || isObject;
    var hasTrailingComma = isLast !== true;

    var line = document.createElement('div');
    line.className = 'horizon-json-line';
    wrapper.appendChild(line);

    var currentPathSegments = Array.isArray(pathSegments) ? pathSegments.slice() : [];
    if (key !== null) {
        currentPathSegments.push(String(key));
    }

    if (key !== null) {
        line.appendChild(buildJsonKey(key));
        var colon = document.createElement('span');
        colon.className = 'horizon-json-colon';
        colon.textContent = ': ';
        line.appendChild(colon);
    }

    if (!isContainer) {
        line.appendChild(buildJsonPrimitive(resolvedValue));
        if (hasTrailingComma) {
            var primitiveComma = document.createElement('span');
            primitiveComma.textContent = ',';
            line.appendChild(primitiveComma);
        }
        return wrapper;
    }

    var children = isArray ? resolvedValue : Object.entries(resolvedValue);
    var openBrace = isArray ? '[' : '{';
    var closeBrace = isArray ? ']' : '}';

    var pointer = '/' + currentPathSegments.map(function (segment) {
        return String(segment).replace(/~/g, '~0').replace(/\//g, '~1');
    }).join('/');

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'horizon-json-toggle';
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('no-ring', 'true');
    toggle.textContent = openBrace;
    line.appendChild(toggle);

    var childrenContainer = document.createElement('div');
    childrenContainer.className = 'horizon-json-children';

    if (isArray) {
        children.forEach(function (childValue, index) {
            childrenContainer.appendChild(buildJsonNode(String(index), childValue, currentPathSegments, state, index === children.length - 1));
        });
    } else {
        children.forEach(function (entry, index) {
            childrenContainer.appendChild(buildJsonNode(entry[0], entry[1], currentPathSegments, state, index === children.length - 1));
        });
    }

    wrapper.appendChild(childrenContainer);

    var closeLine = document.createElement('div');
    closeLine.className = 'horizon-json-line horizon-json-close';
    var closeToggle = document.createElement('button');
    closeToggle.type = 'button';
    closeToggle.className = 'horizon-json-toggle';
    closeToggle.setAttribute('aria-expanded', 'true');
    closeToggle.setAttribute('no-ring', 'true');
    closeToggle.textContent = closeBrace;
    closeLine.appendChild(closeToggle);
    if (hasTrailingComma) {
        var closeComma = document.createElement('span');
        closeComma.textContent = ',';
        closeLine.appendChild(closeComma);
    }
    wrapper.appendChild(closeLine);

    function setExpanded(expanded) {
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        closeToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        childrenContainer.classList.toggle('hidden', !expanded);
        closeLine.classList.toggle('hidden', !expanded);
        var collapsedText = openBrace + '...' + closeBrace + (hasTrailingComma ? ',' : '');
        toggle.textContent = expanded ? openBrace : collapsedText;
    }

    toggle.addEventListener('click', function () {
        var expanded = toggle.getAttribute('aria-expanded') === 'true';
        var nextExpanded = !expanded;
        setExpanded(nextExpanded);
        if (state) state.setCollapsed(pointer, !nextExpanded);
    });
    closeToggle.addEventListener('click', function () {
        var expanded = closeToggle.getAttribute('aria-expanded') === 'true';
        var nextExpanded = !expanded;
        setExpanded(nextExpanded);
        if (state) state.setCollapsed(pointer, !nextExpanded);
    });

    if (state && state.isCollapsed(pointer)) {
        setExpanded(false);
    }

    return wrapper;
}

/**
 * Create a persisted JSON tree state store.
 * @param {string} storageKey
 * @returns {{ isCollapsed: function(string): boolean, setCollapsed: function(string, boolean): void }|null}
 */
function createJsonTreeStateStore(storageKey) {
    if (typeof window === 'undefined') return null;
    if (!storageKey) return null;

    var cache = new Set();
    try {
        var raw = window.localStorage ? window.localStorage.getItem(storageKey) : null;
        if (raw) {
            var parsed = JSON.parse(raw);
            if (Array.isArray(parsed)) {
                parsed.forEach(function (p) {
                    if (typeof p === 'string' && p) cache.add(p);
                });
            }
        }
    } catch (e) {
    }

    function persist() {
        try {
            if (!window.localStorage) return;
            window.localStorage.setItem(storageKey, JSON.stringify(Array.from(cache)));
        } catch (e) {
        }
    }

    return {
        isCollapsed: function (pointer) {
            return cache.has(pointer);
        },
        setCollapsed: function (pointer, collapsed) {
            if (!pointer) return;
            if (collapsed) cache.add(pointer);
            else cache.delete(pointer);
            persist();
        },
    };
}

/**
 * Render JSON tree inside a target element.
 * @param {HTMLElement} target
 * @param {{ storageKey?: string }=} options
 * @returns {void}
 */
export function renderJsonTree(target, options) {
    if (!target || !target.getAttribute) return;

    var source = getJsonTreeSource(target);
    var parsed = null;
    if (typeof source === 'string' && source !== '') {
        parsed = parseJsonSource(source);
    }

    var state = null;
    if (options && typeof options.storageKey === 'string' && options.storageKey) {
        state = createJsonTreeStateStore(options.storageKey);
    }

    var previousRenderedSource = jsonTreeLastRenderedSource.get(target);
    var existingRoot = target.firstElementChild;
    if (
        previousRenderedSource === source &&
        existingRoot &&
        existingRoot.classList.contains('horizon-json-node')
    ) {
        target.classList.add('horizon-json-tree');
        return;
    }

    var jsonTree = buildJsonNode(null, parsed, [], state, true);
    target.innerHTML = '';
    target.classList.add('horizon-json-tree');
    target.appendChild(jsonTree);
    jsonTreeLastRenderedSource.set(target, source);
}
