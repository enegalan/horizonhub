export function horizonQueueList() {
    return {
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            window.addEventListener('horizonhub-refresh', function () {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshQueueList();
            });
        },
        refreshQueueList() {
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
                var newTable = doc.querySelector('[data-resizable-table="horizon-queue-list"]');
                var currentTable = document.querySelector('[data-resizable-table="horizon-queue-list"]');
                if (!newTable || !currentTable) return;

                var newTbody = newTable.querySelector('tbody');
                var currentTbody = currentTable.querySelector('tbody');
                if (newTbody && currentTbody) {
                    currentTbody.replaceWith(newTbody);
                    if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                        window.horizonInitResizableTables();
                    }
                }
            }).catch(function () {
            });
        },
    };
}
