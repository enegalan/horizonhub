var VALID_TYPES = ['success', 'error', 'info', 'warning'];

export function showToast(type, message) {
    if (!window.toast) return;

    var resolvedType = VALID_TYPES.indexOf(type) !== -1 ? type : 'success';
    var resolvedMessage = typeof message === 'string' && message ? message : 'Done.';
    window.toast[resolvedType](resolvedMessage);
}

export function registerToastEventListeners() {
    function onToast(e) {
        var d = e && e.detail;
        if (!d) return;
        if (typeof d === 'object' && ('type' in d || 'message' in d)) {
            showToast(d.type, d.message);
        } else if (typeof d === 'string') {
            showToast('success', d);
        }
    }

    var opts = { capture: true };
    window.addEventListener('toast', onToast, opts);
    window.addEventListener('service-created', () => { showToast('success', 'Service registered.'); }, opts);
    window.addEventListener('job-retried', () => { showToast('success', 'Job retried.'); }, opts);
    window.addEventListener('jobs-retried', function (e) {
        var d = e && e.detail;
        var succeeded = (d && d.succeeded) || 0;
        var failed = (d && d.failed) || 0;
        var messages = (d && d.messages) || [];
        if (failed === 0) {
            showToast('success', succeeded === 1 ? 'Job retried.' : succeeded + ' job(s) retried.');
        } else if (succeeded === 0) {
            showToast('error', messages.length ? messages[0] : failed + ' job(s) failed.');
        } else {
            showToast('warning', succeeded + ' retried, ' + failed + ' failed.');
        }
    }, opts);
    window.addEventListener('job-action-failed', e => {
        showToast('error', (e && e.detail && e.detail.message) || 'Action failed.');
    }, opts);
    window.addEventListener('queue-updated', () => { showToast('success', 'Queue updated.'); }, opts);
    window.addEventListener('alerts-saved', () => { showToast('success', 'Alerts saved.'); }, opts);
}
