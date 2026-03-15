import { formatDateTimeElements } from '../lib/datetime-format';
import { formatQueueWaitElements } from "../lib/queue-wait-format";

/**
 * Horizon service dashboard.
 * @returns {object}
 */
export function horizonServiceDashboard() {
    return {
        /**
         * Initialize the service dashboard.
         * @returns {void}
         */
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            window.addEventListener('horizonhub-refresh', function (e) {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshServiceDashboard(e.detail && e.detail.document);
            });
        },
        /**
         * Refresh the service dashboard.
         * @param {Document} preloadedDoc
         * @returns {void}
         */
        refreshServiceDashboard(preloadedDoc) {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            if (!preloadedDoc) return;
            var newRoot = preloadedDoc.querySelector('[data-horizon-service-dashboard-root="1"]');
            var currentRoot = document.querySelector('[data-horizon-service-dashboard-root="1"]');
            if (!newRoot || !currentRoot) return;
            currentRoot.replaceWith(newRoot);
            formatDateTimeElements(newRoot);
            formatQueueWaitElements(newRoot);
            if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                window.horizonInitResizableTables();
            }
        },
    };
}

/**
 * Horizon service list.
 * @returns {object}
 */
export function horizonServiceList() {
    return {
        /**
         * Initialize the service list.
         * @returns {void}
         */
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            window.addEventListener('horizonhub-refresh', function (e) {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshServiceList(e.detail && e.detail.document);
            });
        },
        /**
         * Refresh the service list.
         * @param {Document} preloadedDoc
         * @returns {void}
         */
        refreshServiceList(preloadedDoc) {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            if (!preloadedDoc) return;
            var newTable = preloadedDoc.querySelector('[data-resizable-table="horizon-service-list"]');
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
        },
    };
}
