(function () {
    var STORAGE_PREFIX = 'horizon_table_';
    var MIN_WIDTH = 60;
    var RESIZE_HANDLE_WIDTH = 8;
    var DRAG_OVER_CLASS = 'horizon-drag-over';

    window.horizonTableInteracting = false;

    function injectDragOverStyle() {
        if (document.getElementById('horizon-drag-over-style')) return;
        var style = document.createElement('style');
        style.id = 'horizon-drag-over-style';
        style.textContent = '.horizon-drag-over{background-color:hsl(var(--primary)/0.2);}';
        document.head.appendChild(style);
    }

    function loadState(storageKey, columnIds) {
        try {
            var raw = localStorage.getItem(STORAGE_PREFIX + storageKey);
            if (!raw) return { order: columnIds.slice(), widths: {} };
            var data = JSON.parse(raw);
            var order = Array.isArray(data.order) ? data.order : columnIds.slice();
            var widths = data.widths && typeof data.widths === 'object' ? data.widths : {};
            order = order.filter(function (id) { return columnIds.indexOf(id) !== -1; });
            columnIds.forEach(function (id) {
                if (order.indexOf(id) === -1) order.push(id);
            });
            return { order: order, widths: widths };
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
        if (raw) return raw.split(',').map(function (s) { return s.trim(); });
        var ths = table.querySelectorAll('thead tr th[data-column-id]');
        return Array.from(ths).map(function (th) { return th.getAttribute('data-column-id'); });
    }

    function applyState(table, storageKey, state) {
        var theadRow = table.querySelector('thead tr');
        var bodyRows = table.querySelectorAll('tbody tr');
        if (!theadRow) return;

        var thsById = {};
        theadRow.querySelectorAll('th[data-column-id]').forEach(function (th) {
            thsById[th.getAttribute('data-column-id')] = th;
        });

        state.order.forEach(function (colId) {
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

        bodyRows.forEach(function (tr) {
            var cellsById = {};
            tr.querySelectorAll('td[data-column-id]').forEach(function (td) {
                cellsById[td.getAttribute('data-column-id')] = td;
            });
            state.order.forEach(function (colId) {
                var td = cellsById[colId];
                if (td) tr.appendChild(td);
            });
        });
    }

    function applyStateToBodyOnly(table, state) {
        var bodyRows = table.querySelectorAll('tbody tr');
        bodyRows.forEach(function (tr) {
            var cellsById = {};
            tr.querySelectorAll('td[data-column-id]').forEach(function (td) {
                cellsById[td.getAttribute('data-column-id')] = td;
            });
            state.order.forEach(function (colId) {
                var td = cellsById[colId];
                if (td) tr.appendChild(td);
            });
        });
    }

    function setupResize(table, storageKey, state, columnIds) {
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) return;

        theadRow.querySelectorAll('th[data-column-id]').forEach(function (th) {
            var colId = th.getAttribute('data-column-id');
            var existing = th.querySelector('.horizon-resize-handle');
            if (existing) return;

            var handle = document.createElement('span');
            handle.className = 'horizon-resize-handle';
            handle.title = 'Resize column';
            handle.style.cssText = 'position:absolute;right:0;top:0;bottom:0;width:' + RESIZE_HANDLE_WIDTH + 'px;cursor:col-resize;margin-right:-' + (RESIZE_HANDLE_WIDTH / 2) + 'px;background-color:transparent;';
            th.style.position = 'relative';

            var line = document.createElement('span');
            line.style.cssText = 'position:absolute;top:0;bottom:0;right:' + (RESIZE_HANDLE_WIDTH / 2 - 0.5) + 'px;width:1px;background-color:hsl(var(--primary) / .15);';
            handle.appendChild(line);

            th.appendChild(handle);

            handle.addEventListener('mousedown', function (e) {
                e.preventDefault();
                window.horizonTableInteracting = true;
                th.setAttribute('data-horizon-resizing', '1');
                th.draggable = false;
                var startX = e.clientX;
                var startWidth = th.offsetWidth;

                function onMove(e) {
                    var w = Math.max(MIN_WIDTH, startWidth + (e.clientX - startX));
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

    function getDragOverBackground() {
        var root = document.documentElement;
        var primary = getComputedStyle(root).getPropertyValue('--primary').trim();
        if (primary) return 'hsl(' + primary + ' / 0.25)';
        return 'rgba(0,0,0,0.1)';
    }

    function clearDragOverAll(theadRow) {
        if (!theadRow) return;
        theadRow.querySelectorAll('th[data-column-id]').forEach(function (t) {
            t.classList.remove(DRAG_OVER_CLASS);
            t.style.removeProperty('background-color');
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
        el.style.backgroundColor = getDragOverBackground();
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
        if (el) {
            el.style.display = 'none';
        }
    }

    function setupReorder(table, storageKey, state, columnIds) {
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) return;

        injectDragOverStyle();

        theadRow.querySelectorAll('th[data-column-id]').forEach(function (th) {
            th.setAttribute('draggable', 'true');
            th.style.cursor = 'move';
            th.classList.add('select-none');

            th.addEventListener('dragstart', function (e) {
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
                setTimeout(function () { document.body.removeChild(dragImage); }, 0);
            });
            th.addEventListener('dragend', function () {
                th.classList.remove('opacity-50');
                table.removeAttribute('data-drag-source-id');
                window.horizonTableInteracting = false;
            });
            th.addEventListener('drop', function (e) {
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
                applyState(table, storageKey, state);
                setupResize(table, storageKey, state, columnIds);
                saveState(storageKey, state.order, state.widths);
            });
        });
    }

    var INITTED_ATTR = 'data-resizable-initted';

    function initTable(table) {
        var storageKey = table.getAttribute('data-resizable-table');
        if (!storageKey) return;
        var columnIds = getColumnIds(table);
        if (columnIds.length === 0) return;

        var state = loadState(storageKey, columnIds);
        table.style.tableLayout = 'fixed';

        if (table.hasAttribute(INITTED_ATTR)) {
            if (window.horizonTableInteracting) return;
            applyStateToBodyOnly(table, state);
            return;
        }

        applyState(table, storageKey, state);
        setupResize(table, storageKey, state, columnIds);
        setupReorder(table, storageKey, state, columnIds);
        table.setAttribute(INITTED_ATTR, '1');
    }

    function init() {
        document.querySelectorAll('table[data-resizable-table]').forEach(initTable);
    }

    function scheduleInit() {
        setTimeout(init, 0);
    }

    document.addEventListener('livewire:initialized', function () {
        if (typeof window.Livewire === 'undefined') return;
        window.Livewire.hook('request', function (_ref) {
            var succeed = _ref.succeed;
            succeed(function () {
                setTimeout(init, 0);
            });
        });
    });

    window.horizonApplyTableBodyOrder = function () {
        document.querySelectorAll('table[' + INITTED_ATTR + ']').forEach(function (table) {
            var storageKey = table.getAttribute('data-resizable-table');
            if (!storageKey) return;
            var columnIds = getColumnIds(table);
            if (columnIds.length === 0) return;
            var state = loadState(storageKey, columnIds);
            applyStateToBodyOnly(table, state);
        });
    };

    function setupDelegatedDragOver() {
        if (window._horizonDragOverDelegated) return;
        window._horizonDragOverDelegated = true;
        document.body.addEventListener('dragover', function (e) {
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
        document.body.addEventListener('dragleave', function (e) {
            var related = e.relatedTarget;
            if (related && related.closest && related.closest('thead')) return;
            hideDragOverlay();
        });
        document.body.addEventListener('dragend', function () {
            hideDragOverlay();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupDelegatedDragOver();
        init();
    });
    document.addEventListener('livewire:navigated', scheduleInit);
})();
