import { formatDateTimeElements, formatQueueWaitElements } from '../lib/datetime-format';
import { onHorizonHubRefresh, replaceTableTbodyFromDoc } from '../lib/dom';

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

            onHorizonHubRefresh(function (doc) {
                self.refreshServiceDashboard(doc);
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

            var currentRoot = document.querySelector('[data-horizon-service-dashboard-root="1"]');
            if (!currentRoot) return;

            var activeEl = document.activeElement;
            if (activeEl && currentRoot.contains(activeEl)) {
                var tag = activeEl.tagName;
                if (tag === 'SELECT' || tag === 'INPUT' || tag === 'TEXTAREA') {
                    return;
                }
                var role = activeEl.getAttribute && activeEl.getAttribute('role');
                if (role === 'listbox' || role === 'combobox' || role === 'option') {
                    return;
                }
                if (activeEl.getAttribute && activeEl.getAttribute('aria-expanded') === 'true') {
                    return;
                }
                if (activeEl.closest && (activeEl.closest('[role="listbox"]') || activeEl.closest('[role="combobox"]'))) {
                    return;
                }
            }

            var newRoot = preloadedDoc.querySelector('[data-horizon-service-dashboard-root="1"]');
            if (!newRoot) return;

            currentRoot.replaceWith(newRoot);
            formatDateTimeElements(newRoot);
            formatQueueWaitElements(newRoot);
            if (typeof window !== 'undefined' && window.Alpine && typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(newRoot);
            }
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

            onHorizonHubRefresh(function (doc) {
                self.refreshServiceList(doc);
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
            var table = replaceTableTbodyFromDoc(preloadedDoc, {
                tableSelector: '[data-resizable-table="horizon-service-list"]',
            });
            if (!table) return;
            formatDateTimeElements(table);
            if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                window.horizonInitResizableTables();
            }
        },
    };
}
