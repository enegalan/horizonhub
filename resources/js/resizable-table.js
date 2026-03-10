import { onDocumentReady, schedule } from './utils/init';
import { withLivewireInitialized, onLivewireNavigated, onLivewireRequestSuccess } from './utils/livewire';
import { parseJson } from './utils/parse';

(function () {
    var STORAGE_PREFIX = 'horizon_table_';
    var MIN_WIDTH = 60;
    var RESIZE_HANDLE_WIDTH = 8;
    var INITTED_ATTR = 'data-resizable-initted';

    window.horizonTableInteracting = false;

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

    function saveState(storageKey, order, widths) {
        try {
            localStorage.setItem(STORAGE_PREFIX + storageKey, JSON.stringify({ order: order, widths: widths || {} }));
        } catch (e) {}
    }

    function getColumnIds(table) {
        var raw = table.getAttribute('data-column-ids');
        if (raw) return raw.split(',').map(s => s.trim());

        var ths = table.querySelectorAll('thead tr th[data-column-id]');
        return Array.from(ths).map(th => th.getAttribute('data-column-id'));
    }

    function applyState(table, state) {
        var theadRow = table.querySelector('thead tr');
        var bodyRows = table.querySelectorAll('tbody tr');
        if (!theadRow) return;

        var thsById = {};
        theadRow.querySelectorAll('th[data-column-id]').forEach(th => {
            thsById[th.getAttribute('data-column-id')] = th;
        });

        state.order.forEach(colId => {
            var th = thsById[colId];
            if (th) {
                theadRow.appendChild(th);
                var w = state.widths[colId];
                if (w != null) {
                    th.style.width = th.style.minWidth = th.style.maxWidth = w + 'px';
                } else {
                    th.style.width = th.style.minWidth = th.style.maxWidth = '';
                }
            }
        });

        var columnOrder = Array.from(theadRow.querySelectorAll('th[data-column-id]')).map(function (th) {
            return th.getAttribute('data-column-id');
        });

        bodyRows.forEach(tr => {
            var cellsById = {};
            tr.querySelectorAll('td[data-column-id]').forEach(td => {
                cellsById[td.getAttribute('data-column-id')] = td;
            });
            columnOrder.forEach(colId => {
                var td = cellsById[colId];
                if (td) tr.appendChild(td);
            });
        });
    }

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

    function hideDragOverlay() {
        var el = document.getElementById('horizon-drag-overlay');
        if (el) el.style.display = 'none';
    }

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
                    th.style.width = th.style.minWidth = th.style.maxWidth = w + 'px';
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
                dragImage.style.cssText = 'position:absolute;left:-9999px;top:0;padding: 8px 4px;min-width:' + th.offsetWidth + 'px;' +
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

    function init() {
        document.querySelectorAll('table[data-resizable-table]').forEach(initTable);
    }

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

    onDocumentReady(() => {
        setupDelegatedDragOver();
        init();
    });

    onLivewireNavigated(() => {
        schedule(init);
    });

    withLivewireInitialized(() => {
        onLivewireRequestSuccess(() => {
            schedule(init);
        });
        var morphRafId;
        window.Livewire.hook('morph.updated', () => {
            cancelAnimationFrame(morphRafId);
            morphRafId = requestAnimationFrame(init);
        });
    });
})();
