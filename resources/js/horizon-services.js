import { formatDateTimeElements } from './datetime-format';

export function horizonServiceDashboard() {
    return {
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            window.addEventListener('horizonhub-refresh', function () {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshServiceDashboard();
            });
        },
        refreshServiceDashboard() {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            var url = window.location.href;
            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(function (response) {
                if (!response.ok) return null;
                return response.text();
            }).then(function (html) {
                if (!html) return;
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newRoot = doc.querySelector('[data-horizon-service-dashboard-root="1"]');
                var currentRoot = document.querySelector('[data-horizon-service-dashboard-root="1"]');
                if (!newRoot || !currentRoot) return;

                currentRoot.replaceWith(newRoot);
                formatDateTimeElements(newRoot);
                if (typeof window !== 'undefined' && window.formatQueueWaitElements) {
                    window.formatQueueWaitElements(newRoot);
                }
                if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                    window.horizonInitResizableTables();
                }
                if (typeof window !== 'undefined' && window.formatQueueWaitElements) {
                    window.formatQueueWaitElements(newRoot);
                }
            }).catch(function () {
            });
        },
    };
}

export function horizonServiceList() {
    return {
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            window.addEventListener('horizonhub-refresh', function () {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshServiceList();
            });
        },
        refreshServiceList() {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            var url = window.location.href;
            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(function (response) {
                if (!response.ok) return null;
                return response.text();
            }).then(function (html) {
                if (!html) return;
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newTable = doc.querySelector('[data-resizable-table="horizon-service-list"]');
                var currentTable = document.querySelector('[data-resizable-table="horizon-service-list"]');
                if (!newTable || !currentTable) return;

                var newTbody = newTable.querySelector('tbody');
                var currentTbody = currentTable.querySelector('tbody');
                if (newTbody && currentTbody) {
                    currentTbody.replaceWith(newTbody);
                    formatDateTimeElements(currentTable);
                    if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                        window.horizonInitResizableTables();
                    }
                }
            }).catch(function () {
            });
        },
    };
}
