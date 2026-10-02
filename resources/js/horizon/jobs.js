import { renderJsonTree } from '../lib/json-tree';

/**
 * Render JSON trees on the job detail page.
 * @returns {void}
 */
export function renderJsonTrees() {
    var rootEl = document.querySelector('[data-horizon-job-detail-root="1"]');
    if (!rootEl) return;
    var jobUuid = String(rootEl.getAttribute('data-horizon-job-uuid') || '').trim();
    rootEl.querySelectorAll('[data-json-tree]').forEach(function (target) {
        var treeName = target.getAttribute('data-json-tree');
        var storageKey = null;
        if (jobUuid && treeName) {
            storageKey = 'horizonhub:job:' + jobUuid + ':json-tree:' + treeName;
        }
        renderJsonTree(target, { storageKey: storageKey });
    });
}
