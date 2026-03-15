/**
 * Horizon queue list.
 * @returns {object}
 */
export function horizonQueueList() {
    return {
        /**
         * Initialize the queue list.
         * @returns {void}
         */
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            window.addEventListener('horizonhub-refresh', function (e) {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshQueueList(e.detail && e.detail.document);
            });
        },
        /**
         * Refresh the queue list.
         * @param {Document} preloadedDoc
         * @returns {void}
         */
        refreshQueueList(preloadedDoc) {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            if (!preloadedDoc) return;
            var newTable = preloadedDoc.querySelector('[data-resizable-table="horizon-queue-list"]');
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
        },
    };
}
