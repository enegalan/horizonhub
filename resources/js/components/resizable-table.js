import { parseJson } from '../lib/parse';

(function () {
    /**
     * Storage prefix.
     * @type {string}
     */
    var STORAGE_PREFIX = 'horizon_table_';

    /**
     * Minimum width.
     * @type {number}
     */
    var MIN_WIDTH = 60;

    /**
     * Resize handle width.
     * @type {number}
     */
    var RESIZE_HANDLE_WIDTH = 8;

    /**
     * Initted attribute.
     * @type {string}
     */
    var INITTED_ATTR = 'data-resizable-initted';

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
        } catch (e) {
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
        } catch (e) {}
    }

    /**
     * Get the column IDs from the table.
     * @param {HTMLElement} table
     * @returns {string[]}
     */
    function getColumnIds(table) {
        var raw = table.getAttribute('data-column-ids');
        return raw ? raw.split(',').map(s => s.trim()) : [];
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
        var i;
        for (i = 0; i < current.length; i++) {
            if (current[i] !== desired[i]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Direct th/td children carrying data-column-id.
     * @param {HTMLTableRowElement} row
     * @param {string} tag 'TH' | 'TD'
     * @returns {HTMLElement[]}
     */
    function getDirectColumnCells(row, tag) {
        var want = String(tag).toUpperCase();
        var out = [];
        for (var c = row.firstElementChild; c; c = c.nextElementSibling) {
            var tn = c.tagName ? c.tagName.toUpperCase() : '';
            if (tn === want && c.hasAttribute('data-column-id')) {
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
        theadRow.querySelectorAll('th[data-column-id]').forEach(function (th) {
            thsById[th.getAttribute('data-column-id')] = th;
        });
        var expectedOrder = [];
        state.order.forEach(function (colId) {
            if (thsById[colId]) {
                expectedOrder.push(colId);
            }
        });
        var currentHead = getDirectColumnCells(theadRow, 'TH');
        if (currentHead.length !== expectedOrder.length) {
            return false;
        }
        var i;
        for (i = 0; i < expectedOrder.length; i++) {
            if (currentHead[i].getAttribute('data-column-id') !== expectedOrder[i]) {
                return false;
            }
        }
        for (i = 0; i < expectedOrder.length; i++) {
            var colId = expectedOrder[i];
            var th = thsById[colId];
            var w = state.widths[colId];
            var widthStr = typeof w === 'number' && isFinite(w) ? w + 'px' : '';
            if (th.style.width !== widthStr || th.style.maxWidth !== widthStr) {
                return false;
            }
        }
        var columnOrder = expectedOrder.slice();
        var bodyRows = table.querySelectorAll('tbody tr');
        for (var r = 0; r < bodyRows.length; r++) {
            var tr = bodyRows[r];
            var cur = getDirectColumnCells(tr, 'TD').map(function (td) {
                return td.getAttribute('data-column-id');
            });
            if (cur.length !== columnOrder.length) {
                return false;
            }
            for (var c = 0; c < columnOrder.length; c++) {
                if (cur[c] !== columnOrder[c]) {
                    return false;
                }
            }
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
        theadRow.querySelectorAll('th[data-column-id]').forEach(th => {
            thsById[th.getAttribute('data-column-id')] = th;
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
            var w = state.widths[colId];
            var widthStr = typeof w === 'number' && isFinite(w) ? w + 'px' : '';
            if (th.style.width !== widthStr || th.style.maxWidth !== widthStr) {
                th.style.width = th.style.maxWidth = widthStr;
            }
        });

        var columnOrder = getDirectColumnCells(theadRow, 'TH').map(function (th) {
            return th.getAttribute('data-column-id');
        });

        bodyRows.forEach(tr => {
            var cellsById = {};
            getDirectColumnCells(tr, 'TD').forEach(td => {
                cellsById[td.getAttribute('data-column-id')] = td;
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
        var id = 'horizon-drag-overlay';
        var el = document.getElementById(id);
        if (el) return el;

        el = document.createElement('div');
        el.id = id;
        el.setAttribute('aria-hidden', 'true');
        el.style.cssText = 'position:fixed;pointer-events:none;z-index:9999;border-radius:4px;transition:opacity 0.1s;display:none;box-sizing:border-box;';
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
        overlay.style.display = 'block';
        overlay.style.opacity = '1';
    }

    /**
     * Hide the drag overlay.
     * @returns {void}
     */
    function hideDragOverlay() {
        var el = document.getElementById('horizon-drag-overlay');
        if (el) el.style.display = 'none';
    }

    /**
     * Setup the resize.
     * @param {HTMLElement} table
     * @param {string} storageKey
     * @param {object} state
     * @param {string[]} columnIds
     * @returns {void}
     */
    function setupResize(table, storageKey, state, columnIds) {
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) return;

        theadRow.querySelectorAll('th[data-column-id]').forEach(th => {
            var colId = th.getAttribute('data-column-id');
            var existing = th.querySelector('.horizon-resize-handle');
            if (existing) return;

            var handle = document.createElement('span');
            handle.className = 'horizon-resize-handle absolute right-0 top-0 bottom-0 cursor-col-resize bg-transparent';
            handle.style.cssText = 'width:' + RESIZE_HANDLE_WIDTH + 'px;margin-right:-' + (RESIZE_HANDLE_WIDTH / 2) + 'px;';
            handle.title = 'Resize column';

            var line = document.createElement('span');
            line.className = 'absolute top-0 bottom-0 w-px bg-primary/15';
            line.style.cssText = 'right:' + (RESIZE_HANDLE_WIDTH / 2 - 0.5) + 'px;';
            handle.appendChild(line);

            th.appendChild(handle);

            handle.addEventListener('mousedown', e => {
                e.preventDefault();
                window.horizonTableInteracting = true;
                th.setAttribute('data-horizon-resizing', '1');
                th.draggable = false;
                var startX = e.clientX;
                var startWidth = th.offsetWidth;

                function onMove(eMove) {
                    var w = Math.max(MIN_WIDTH, startWidth + (eMove.clientX - startX));
                    state.widths[colId] = w;
                    th.style.width = th.style.maxWidth = w + 'px';
                }

                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.body.style.cursor = '';
                    document.body.style.userSelect = '';
                    th.removeAttribute('data-horizon-resizing');
                    th.draggable = true;
                    saveState(storageKey, state.order, state.widths);
                    window.horizonTableInteracting = false;
                }

                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';
            });
        });
    }

    /**
     * Setup the reorder.
     * @param {HTMLElement} table
     * @param {string} storageKey
     * @param {object} state
     * @param {string[]} columnIds
     * @returns {void}
     */
    function setupReorder(table, storageKey, state, columnIds) {
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) return;

        theadRow.querySelectorAll('th[data-column-id]').forEach(th => {
            th.setAttribute('draggable', 'true');
            th.style.cursor = 'move';
            th.classList.add('select-none');

            th.addEventListener('dragstart', e => {
                if (th.getAttribute('data-horizon-resizing') === '1') {
                    e.preventDefault();
                    return;
                }
                window.horizonTableInteracting = true;
                e.dataTransfer.effectAllowed = 'move';
                var colId = th.getAttribute('data-column-id');
                e.dataTransfer.setData('text/plain', colId);
                table.setAttribute('data-drag-source-id', colId);
                th.classList.add('opacity-50');

                var dragImage = th.cloneNode(true);
                dragImage.style.cssText = 'position:absolute;left:-9999px;top:0;padding: 8px 4px;' +
                    'background:hsl(var(--card) / 0.92);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);' +
                    'box-shadow:0 4px 20px rgba(0,0,0,0.12);border-radius:6px;border:1px solid hsl(var(--border));pointer-events:none;';
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
                if (th.getAttribute('data-column-id') === table.getAttribute('data-drag-source-id')) return;
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            th.addEventListener('drop', e => {
                e.preventDefault();
                var sourceId = e.dataTransfer.getData('text/plain');
                var targetId = th.getAttribute('data-column-id');
                if (!sourceId || sourceId === targetId) return;

                var order = state.order.slice();
                var si = order.indexOf(sourceId);
                var ti = order.indexOf(targetId);
                if (si === -1 || ti === -1) return;

                order.splice(si, 1);
                order.splice(ti, 0, sourceId);
                state.order = order;
                applyState(table, state);
                setupResize(table, storageKey, state, columnIds);
                saveState(storageKey, state.order, state.widths);
            });
        });
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

        var state = loadState(storageKey, columnIds);
        table.style.tableLayout = 'fixed';

        if (table.hasAttribute(INITTED_ATTR)) {
            if (window.horizonTableInteracting) return;

            applyState(table, state);
            setupResize(table, storageKey, state, columnIds);
            setupReorder(table, storageKey, state, columnIds);
            return;
        }

        applyState(table, state);
        setupResize(table, storageKey, state, columnIds);
        setupReorder(table, storageKey, state, columnIds);
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
            var target = e.target.closest('th[data-column-id]');
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
            if (target.getAttribute('data-column-id') !== table.getAttribute('data-drag-source-id')) {
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
