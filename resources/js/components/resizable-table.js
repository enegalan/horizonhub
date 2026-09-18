import { parseJson } from '../lib/parse';

(function () {
    /**
     * Storage prefix.
     * @type {string}
     */
    const STORAGE_PREFIX = 'horizon_table_';

    /**
     * Horizon drag overlay ID.
     * @type {string}
     */
    const HORIZON_DRAG_OVERLAY_ID = 'horizon-drag-overlay';

    /**
     * Horizon drag overlay visible class.
     * @type {string}
     */
    const HORIZON_DRAG_OVERLAY_VISIBLE_CLASS = 'horizon-drag-overlay--visible';

    /**
     * Horizon resize handle line class.
     * @type {string}
     */
    const HORIZON_RESIZE_HANDLE_LINE_CLASS = 'horizon-resize-handle-line';

    /**
     * Horizon resize handle class.
     * @type {string}
     */
    const HORIZON_RESIZE_HANDLE_CLASS = 'horizon-resize-handle';

    /**
     * Initted attribute.
     * @type {string}
     */
    const INITTED_ATTR = 'data-resizable-initted';

    /**
     * Column IDs attribute.
     * @type {string}
     */
    const COLUMN_IDS_ATTR = 'data-column-ids';

    /**
     * Column ID attribute.
     * @type {string}
     */
    const COLUMN_ID_ATTR = 'data-column-id';

    /**
     * Column min width attribute.
     * @type {string}
     */
    const COLUMN_MIN_WIDTH_ATTR = 'data-horizon-min-width';

    /**
     * Column max width attribute.
     * @type {string}
     */
    const COLUMN_MAX_WIDTH_ATTR = 'data-horizon-max-width';

    /**
     * Horizon resizing attribute.
     * @type {string}
     */
    const HORIZON_RESIZING_ATTR = 'data-horizon-resizing';

    /**
     * Interacting flag.
     * @type {boolean}
     */
    window.horizonTableInteracting = false;

    /**
     * Load the state from localStorage.
     * @param {string} storageKey
     * @param {string[]} columnIds
     * @returns {object}
     */
    function loadState(storageKey, columnIds) {
        try {
            var raw = localStorage.getItem(STORAGE_PREFIX + storageKey);
            if (!raw) {
                return { order: columnIds.slice(), widths: {} };
            }
            var data = parseJson(raw);
            var order = Array.isArray(data.order) ? data.order : columnIds.slice();
            var widths = data.widths && typeof data.widths === 'object' ? data.widths : {};
            order = order.filter(id => columnIds.indexOf(id) !== -1);
            columnIds.forEach(id => {
                if (order.indexOf(id) === -1) order.push(id);
            });
            return { order, widths };
        } catch (_e) {
            return { order: columnIds.slice(), widths: {} };
        }
    }

    /**
     * Save the state to localStorage.
     * @param {string} storageKey
     * @param {string[]} order
     * @param {object} widths
     * @returns {void}
     */
    function saveState(storageKey, order, widths) {
        try {
            localStorage.setItem(STORAGE_PREFIX + storageKey, JSON.stringify({ order: order, widths: widths || {} }));
        } catch (_e) {}
    }

    /**
     * Get the column IDs from the table.
     * @param {HTMLElement} table
     * @returns {string[]}
     */
    function getColumnIds(table) {
        return table.getAttribute(COLUMN_IDS_ATTR)?.split(',').map(s => s.trim()) || [];
    }

    /**
     * Read a stylesheet-assigned width bound (min/max) and cache it on the element
     * so it survives inline overwrites. 0 means no bound.
     * @param {HTMLElement} th
     * @param {string} attr
     * @param {string} cssProp 'minWidth' | 'maxWidth'
     * @returns {number}
     */
    function getColumnWidthBound(th, attr, cssProp) {
        var cached = th.getAttribute(attr);
        if (cached) {
            var cachedValue = parseFloat(cached);
            return isFinite(cachedValue) ? cachedValue : 0;
        }
        var css = window.getComputedStyle(th)[cssProp];
        var parsed = css ? parseFloat(css) : NaN;
        var bound = isFinite(parsed) && parsed > 0 ? parsed : 0;
        if (bound > 0) {
            th.setAttribute(attr, String(bound));
        }
        return bound;
    }

    /**
     * Clamp a column width within the stylesheet min-width/max-width.
     * Columns without an assigned bound in the stylesheet are not constrained.
     * @param {HTMLElement} th
     * @param {number} w
     * @returns {number}
     */
    function clampColumnWidth(th, w) {
        if (typeof w !== 'number' || !isFinite(w)) {
            return w;
        }
        var min = getColumnWidthBound(th, COLUMN_MIN_WIDTH_ATTR, 'minWidth');
        var max = getColumnWidthBound(th, COLUMN_MAX_WIDTH_ATTR, 'maxWidth');
        if (min > 0 && max > 0 && min > max) {
            min = max;
        }
        if (max > 0 && w > max) {
            return max;
        }
        if (min > 0 && w < min) {
            return min;
        }
        return w;
    }

    /**
     * Resolve the inline width/max-width strings for a column.
     * @param {HTMLElement} th
     * @param {number|undefined} w
     * @returns {{ width: string, maxWidth: string }}
     */
    function getColumnStyleWidths(th, w) {
        var resolved = typeof w === 'number' && isFinite(w) ? clampColumnWidth(th, w) : null;
        var max = getColumnWidthBound(th, COLUMN_MAX_WIDTH_ATTR, 'maxWidth');
        return {
            width: resolved === null ? '' : resolved + 'px',
            maxWidth: max > 0 ? max + 'px' : ''
        };
    }

    /**
     * Match the element order.
     * @param {Element[]} current
     * @param {Element[]} desired
     * @returns {boolean}
     */
    function matchElementOrder(current, desired) {
        if (current.length !== desired.length) {
            return false;
        }
        return current.every((element, index) => element === desired[index]);
    }

    /**
     * Direct th/td children carrying COLUMN_ID_ATTR.
     * @param {HTMLTableRowElement} row
     * @param {string} tag 'TH' | 'TD'
     * @returns {HTMLElement[]}
     */
    function getDirectColumnCells(row, tag) {
        var want = String(tag).toUpperCase();
        var out = [];
        for (let c = row.firstElementChild; c; c = c.nextElementSibling) {
            var tn = c.tagName?.toUpperCase() || '';
            if (tn === want && c.hasAttribute(COLUMN_ID_ATTR)) {
                out.push(c);
            }
        }
        return out;
    }

    /**
     * Whether thead order, widths, and tbody column order already match storage.
     * @param {HTMLElement} table
     * @param {object} state
     * @returns {boolean}
     */
    function tableLayoutMatchesStoredState(table, state) {
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) {
            return true;
        }
        var thsById = {};
        theadRow.querySelectorAll('th[' + COLUMN_ID_ATTR + ']').forEach(th => {
            thsById[th.getAttribute(COLUMN_ID_ATTR)] = th;
        });
        var expectedOrder = [];
        state.order.forEach(colId => {
            if (thsById[colId]) {
                expectedOrder.push(colId);
            }
        });
        var currentHead = getDirectColumnCells(theadRow, 'TH');
        if (currentHead.length !== expectedOrder.length) {
            return false;
        }
        if (currentHead.some((element, index) => element.getAttribute(COLUMN_ID_ATTR) !== expectedOrder[index])) {
            return false;
        }
        if (expectedOrder.some(colId => {
            var th = thsById[colId];
            if (!th) return true;
            var widths = getColumnStyleWidths(th, state.widths[colId]);
            return th.style.width !== widths.width || th.style.maxWidth !== widths.maxWidth;
        })) {
            return false;
        }
        var columnOrder = expectedOrder.slice();
        if (Array.from(table.querySelectorAll('tbody tr')).some(tr => {
            var cur = getDirectColumnCells(tr, 'TD').map(td => td.getAttribute(COLUMN_ID_ATTR));
            if (cur.length !== columnOrder.length) {
                return true;
            }
            return columnOrder.some((colId, index) => cur[index] !== colId);
        })) {
            return false;
        }
        return true;
    }

    /**
     * Apply the state to the table.
     * @param {HTMLElement} table
     * @param {object} state
     * @returns {void}
     */
    function applyState(table, state) {
        if (tableLayoutMatchesStoredState(table, state)) {
            return;
        }
        var theadRow = table.querySelector('thead tr');
        var bodyRows = table.querySelectorAll('tbody tr');
        if (!theadRow) return;

        var thsById = {};
        theadRow.querySelectorAll('th[' + COLUMN_ID_ATTR + ']').forEach(th => {
            thsById[th.getAttribute(COLUMN_ID_ATTR)] = th;
        });

        var desiredHeadCells = [];
        state.order.forEach(function (colId) {
            var th = thsById[colId];
            if (th) {
                desiredHeadCells.push(th);
            }
        });
        var currentHeadCells = getDirectColumnCells(theadRow, 'TH');
        if (!matchElementOrder(currentHeadCells, desiredHeadCells)) {
            desiredHeadCells.forEach(function (th) {
                theadRow.appendChild(th);
            });
        }

        state.order.forEach(colId => {
            var th = thsById[colId];
            if (!th) return;
            var widths = getColumnStyleWidths(th, state.widths[colId]);
            if (th.style.width !== widths.width || th.style.maxWidth !== widths.maxWidth) {
                th.style.width = widths.width;
                th.style.maxWidth = widths.maxWidth;
            }
        });

        var columnOrder = getDirectColumnCells(theadRow, 'TH').map(function (th) {
            return th.getAttribute(COLUMN_ID_ATTR);
        });

        bodyRows.forEach(tr => {
            var cellsById = {};
            getDirectColumnCells(tr, 'TD').forEach(td => {
                cellsById[td.getAttribute(COLUMN_ID_ATTR)] = td;
            });
            var desiredBodyCells = [];
            columnOrder.forEach(function (colId) {
                var td = cellsById[colId];
                if (td) {
                    desiredBodyCells.push(td);
                }
            });
            var currentBodyCells = getDirectColumnCells(tr, 'TD');
            if (!matchElementOrder(currentBodyCells, desiredBodyCells)) {
                desiredBodyCells.forEach(function (td) {
                    tr.appendChild(td);
                });
            }
        });
    }

    /**
     * Get the drag overlay.
     * @returns {HTMLElement}
     */
    function getDragOverlay() {
        var el = document.getElementById(HORIZON_DRAG_OVERLAY_ID);
        if (el) return el;

        el = document.createElement('div');
        el.id = HORIZON_DRAG_OVERLAY_ID;
        el.setAttribute('aria-hidden', 'true');
        document.body.appendChild(el);
        return el;
    }

    /**
     * Show the overlay over the th.
     * @param {HTMLElement} th
     * @returns {void}
     */
    function showOverlayOver(th) {
        var overlay = getDragOverlay();
        var r = th.getBoundingClientRect();
        overlay.style.left = r.left + 'px';
        overlay.style.top = r.top + 'px';
        overlay.style.width = r.width + 'px';
        overlay.style.height = r.height + 'px';
        overlay.classList.add(HORIZON_DRAG_OVERLAY_VISIBLE_CLASS);
    }

    /**
     * Hide the drag overlay.
     * @returns {void}
     */
    function hideDragOverlay() {
        var el = document.getElementById(HORIZON_DRAG_OVERLAY_ID);
        if (el) el.classList.remove(HORIZON_DRAG_OVERLAY_VISIBLE_CLASS);
    }

    /**
     * Setup the resize.
     * @param {HTMLElement} table
     * @param {string} storageKey
     * @param {object} state
     * @returns {void}
     */
    function setupResize(table, storageKey, state) {
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) return;

        theadRow.querySelectorAll('th[' + COLUMN_ID_ATTR + ']').forEach(th => {
            var colId = th.getAttribute(COLUMN_ID_ATTR);
            var existing = th.querySelector('.' + HORIZON_RESIZE_HANDLE_CLASS);
            if (existing) return;

            var handle = document.createElement('span');
            handle.className = HORIZON_RESIZE_HANDLE_CLASS;

            var line = document.createElement('span');
            line.className = HORIZON_RESIZE_HANDLE_LINE_CLASS;
            handle.appendChild(line);

            th.appendChild(handle);

            handle.addEventListener('mousedown', e => {
                e.preventDefault();
                window.horizonTableInteracting = true;
                th.setAttribute(HORIZON_RESIZING_ATTR, '1');
                th.draggable = false;
                var startX = e.clientX;
                var startWidth = th.offsetWidth;

                function onMove(eMove) {
                    var w = clampColumnWidth(th, startWidth + (eMove.clientX - startX));
                    state.widths[colId] = w;
                    var widths = getColumnStyleWidths(th, w);
                    th.style.width = widths.width;
                    th.style.maxWidth = widths.maxWidth;
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    th.removeAttribute(HORIZON_RESIZING_ATTR);
                    th.draggable = true;
                    saveState(storageKey, state.order, state.widths);
                    window.horizonTableInteracting = false;
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }

    /**
     * Setup the reorder.
     * @param {HTMLElement} table
     * @param {string} storageKey
     * @param {object} state
     * @returns {void}
     */
    function setupReorder(table, storageKey, state) {
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) return;

        theadRow.querySelectorAll('th[' + COLUMN_ID_ATTR + ']').forEach(th => {
            if (th.hasAttribute('data-column-fixed')) {
                th.removeAttribute('draggable');
                th.classList.remove('select-none', 'cursor-move');
                return;
            }

            th.setAttribute('draggable', 'true');
            th.classList.add('select-none', 'cursor-move');

            th.addEventListener('dragstart', e => {
                if (th.getAttribute(HORIZON_RESIZING_ATTR) === '1') {
                    e.preventDefault();
                    return;
                }
                window.horizonTableInteracting = true;
                e.dataTransfer.effectAllowed = 'move';
                var colId = th.getAttribute(COLUMN_ID_ATTR);
                e.dataTransfer.setData('text/plain', colId);
                table.setAttribute('data-drag-source-id', colId);
                th.classList.add('opacity-50');

                var dragImage = th.cloneNode(true);
                dragImage.style.cssText = '';
                dragImage.classList.add('horizon-drag-image');
                document.body.appendChild(dragImage);
                e.dataTransfer.setDragImage(dragImage, e.offsetX, e.offsetY);
                setTimeout(() => {
                    document.body.removeChild(dragImage);
                }, 0);
            });

            th.addEventListener('dragend', () => {
                th.classList.remove('opacity-50');
                table.removeAttribute('data-drag-source-id');
                window.horizonTableInteracting = false;
            });

            th.addEventListener('dragover', e => {
                if (!table.getAttribute('data-drag-source-id')) return;
                if (th.getAttribute(COLUMN_ID_ATTR) === table.getAttribute('data-drag-source-id')) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            th.addEventListener('drop', e => {
                e.preventDefault();
                var sourceId = e.dataTransfer.getData('text/plain');
                var targetId = th.getAttribute(COLUMN_ID_ATTR);
                if (!sourceId || sourceId === targetId) return;

                var order = state.order.slice();
                var si = order.indexOf(sourceId);
                var ti = order.indexOf(targetId);
                if (si === -1 || ti === -1) return;

                order.splice(si, 1);
                order.splice(ti, 0, sourceId);
                state.order = order;
                applyState(table, state);
                setupResize(table, storageKey, state);
                saveState(storageKey, state.order, state.widths);
            });
        });
    }

    /**
     * Apply layout styles that decouple each column from the others.
     * The table width is driven by its columns (max-content) but never drops
     * below the container width (min-width 100%), so there is never an empty
     * gap when the column widths sum to less than the container.
     * @param {HTMLElement} table
     * @returns {void}
     */
    function applyTableLayoutStyles(table) {
        table.style.tableLayout = 'fixed';
        table.style.width = 'max-content';
        table.style.minWidth = '100%';
    }

    /**
     * Fill missing stored widths with the column's current rendered width so
     * every column gets an explicit width (keeps them independent).
     * @param {HTMLElement} table
     * @param {object} state
     * @returns {boolean} Whether any width was added.
     */
    function normalizeStateWidths(table, state) {
        var changed = false;
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) return false;
        theadRow.querySelectorAll('th[' + COLUMN_ID_ATTR + ']').forEach(th => {
            var colId = th.getAttribute(COLUMN_ID_ATTR);
            if (Object.prototype.hasOwnProperty.call(state.widths, colId)) return;
            var w = th.offsetWidth;
            if (w > 0) {
                state.widths[colId] = w;
                changed = true;
            }
        });
        return changed;
    }

    /**
     * Initialize the table.
     * @param {HTMLElement} table
     * @returns {void}
     */
    function initTable(table) {
        var storageKey = table.getAttribute('data-resizable-table');
        if (!storageKey) return;

        var columnIds = getColumnIds(table);
        if (columnIds.length === 0) return;

        if (table.hasAttribute(INITTED_ATTR) && window.horizonTableInteracting) {
            return;
        }

        var state = loadState(storageKey, columnIds);
        applyTableLayoutStyles(table);
        if (normalizeStateWidths(table, state)) {
            saveState(storageKey, state.order, state.widths);
        }

        applyState(table, state);
        setupResize(table, storageKey, state);
        setupReorder(table, storageKey, state);
        table.setAttribute(INITTED_ATTR, '1');
    }

    /**
     * Re-apply stored column order and widths.
     * Does not attach duplicate resize/reorder listeners.
     * @param {HTMLElement} table
     * @returns {void}
     */
    function syncLayoutFromStorage(table) {
        var storageKey = table.getAttribute('data-resizable-table');
        if (!storageKey) return;

        var columnIds = getColumnIds(table);
        if (columnIds.length === 0) return;

        var state = loadState(storageKey, columnIds);
        applyTableLayoutStyles(table);
        if (normalizeStateWidths(table, state)) {
            saveState(storageKey, state.order, state.widths);
        }
        applyState(table, state);
    }

    /**
     * Ensure a table has resizable/reorderable columns, or refresh layout from storage.
     * @param {HTMLElement|string} tableOrSelector
     * @returns {void}
     */
    function ensureOrSyncTable(tableOrSelector) {
        var table = typeof tableOrSelector === 'string'
            ? document.querySelector(tableOrSelector)
            : tableOrSelector;
        if (!table) return;

        if (table.hasAttribute(INITTED_ATTR)) {
            syncLayoutFromStorage(table);
        } else {
            initTable(table);
        }
    }

    if (!window.horizonSyncResizableTableLayout) {
        window.horizonSyncResizableTableLayout = ensureOrSyncTable;
    }

    /**
     * Re-apply resizable column state for tables touched by a stream target subtree.
     * @param {Element} syncRoot
     * @returns {void}
     */
    function syncResizableTablesUnderRoot(syncRoot) {
        if (!syncRoot || typeof syncRoot.querySelectorAll !== 'function' || typeof window.horizonSyncResizableTableLayout !== 'function') {
            return;
        }
        var tables = [];
        function addTable(table) {
            if (!table || tables.indexOf(table) !== -1) {
                return;
            }
            tables.push(table);
        }
        if (syncRoot.matches && syncRoot.matches('table[data-resizable-table]')) {
            addTable(syncRoot);
        }
        var parentTable = syncRoot.closest && syncRoot.closest('table[data-resizable-table]');
        if (parentTable) {
            addTable(parentTable);
        }
        syncRoot.querySelectorAll('table[data-resizable-table]').forEach(addTable);
        tables.forEach(function (table) {
            window.horizonSyncResizableTableLayout(table);
        });
    }

    if (!window.horizonSyncResizableTablesUnderRoot) {
        window.horizonSyncResizableTablesUnderRoot = syncResizableTablesUnderRoot;
    }

    /**
     * Initialize the resizable tables.
     * @returns {void}
     */
    function init() {
        document.querySelectorAll('table[data-resizable-table]').forEach(initTable);
    }

    /**
     * Setup the delegated drag over.
     * @returns {void}
     */
    function setupDelegatedDragOver() {
        if (window._horizonDragOverDelegated) return;

        window._horizonDragOverDelegated = true;
        document.body.addEventListener('dragover', e => {
            var target = e.target.closest('th[' + COLUMN_ID_ATTR + ']');
            if (!target) {
                hideDragOverlay();
                return;
            }
            var table = target.closest('table[data-resizable-table]');
            if (!table) {
                hideDragOverlay();
                return;
            }
            if (!table.getAttribute('data-drag-source-id')) {
                hideDragOverlay();
                return;
            }
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (target.getAttribute(COLUMN_ID_ATTR) !== table.getAttribute('data-drag-source-id')) {
                showOverlayOver(target);
            } else {
                hideDragOverlay();
            }
        });
        document.body.addEventListener('dragleave', e => {
            var related = e.relatedTarget;
            if (related && related.closest && related.closest('thead')) return;

            hideDragOverlay();
        });
        document.body.addEventListener('dragend', () => {
            hideDragOverlay();
        });
    }

    /**
     * Initialize the resizable tables.
     * @returns {void}
     */
    if (!window.horizonInitResizableTables) {
        window.horizonInitResizableTables = init;
    }

    /**
     * Initialize the resizable tables.
     * @returns {void}
     */
    document.addEventListener('turbo:load', function () {
        setupDelegatedDragOver();
        init();
    });
})();
