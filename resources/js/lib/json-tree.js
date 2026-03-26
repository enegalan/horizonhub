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
 * Parse JSON sources that can arrive escaped/encoded.
 * @param {string} rawSource
 * @returns {unknown}
 */
function parseJsonSource(rawSource) {
    var candidate = decodeHtmlEntities(String(rawSource)).trim();
    var seen = new Set();

    for (;;) {
        if (seen.has(candidate)) {
            return candidate;
        }
        seen.add(candidate);

        try {
            var parsed = JSON.parse(candidate);

            if (typeof parsed !== 'string') {
                return parsed;
            }

            var nextCandidate = decodeHtmlEntities(parsed).trim();
            if (!nextCandidate) return '';

            var startsLikeJson =
                nextCandidate.startsWith('{') ||
                nextCandidate.startsWith('[') ||
                nextCandidate.startsWith('"');

            if (!startsLikeJson) {
                return parsed;
            }

            candidate = nextCandidate;
        } catch (error) {
            return candidate;
        }
    }
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
    valueEl.className = 'horizon-json-value';
    valueEl.innerHTML = highlightJsonValue(value);

    return valueEl;
}

/**
 * Build a JSON node recursively.
 * @param {string|null} key
 * @param {unknown} value
 * @param {string[]} pathSegments
 * @param {{ isCollapsed: function(string): boolean, setCollapsed: function(string, boolean): void }|null} state
 * @returns {HTMLElement}
 */
function buildJsonNode(key, value, pathSegments, state) {
    var wrapper = document.createElement('div');
    wrapper.className = 'horizon-json-node';

    var isArray = Array.isArray(value);
    var isObject = value !== null && typeof value === 'object' && !isArray;
    var isContainer = isArray || isObject;

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
        line.appendChild(buildJsonPrimitive(value));
        return wrapper;
    }

    var children = isArray ? value : Object.entries(value);
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
            childrenContainer.appendChild(buildJsonNode(String(index), childValue, currentPathSegments, state));
        });
    } else {
        children.forEach(function (entry) {
            childrenContainer.appendChild(buildJsonNode(entry[0], entry[1], currentPathSegments, state));
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
    wrapper.appendChild(closeLine);

    function setExpanded(expanded) {
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        closeToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        childrenContainer.classList.toggle('hidden', !expanded);
        closeLine.classList.toggle('hidden', !expanded);
        toggle.textContent = expanded ? openBrace : (openBrace + '...' + closeBrace);
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

    target.innerHTML = '';
    target.classList.add('horizon-json-tree');
    target.appendChild(buildJsonNode(null, parsed, [], state));
}
