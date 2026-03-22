import { onHorizonHubRefresh, replaceTableTbodyFromDoc } from '../lib/dom';

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

            onHorizonHubRefresh(function (doc) {
                self.refreshQueueList(doc);
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
            var table = replaceTableTbodyFromDoc(preloadedDoc, {
                tableSelector: '[data-resizable-table="horizon-queue-list"]',
            });
            if (table && typeof window !== 'undefined' && window.horizonInitResizableTables) {
                window.horizonInitResizableTables();
            }
        },
    };
}
