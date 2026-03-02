(function () {
    function getModal() {
        return document.getElementById('alert-log-modal');
    }

    function openModal(button) {
        var modal = getModal();
        if (!modal) return;

        var sentAtEl = document.getElementById('alert-log-sent-at');
        var serviceEl = document.getElementById('alert-log-service');
        var jobEl = document.getElementById('alert-log-job');
        var eventsWrapperEl = document.getElementById('alert-log-events-wrapper');
        var eventsCountEl = document.getElementById('alert-log-events-count');
        var jobIdsWrapperEl = document.getElementById('alert-log-job-ids-wrapper');
        var jobIdsEl = document.getElementById('alert-log-job-ids');
        var jobIdsMoreEl = document.getElementById('alert-log-job-ids-more');
        var statusEl = document.getElementById('alert-log-status');
        var failureWrapperEl = document.getElementById('alert-log-failure-wrapper');
        var failureMessageEl = document.getElementById('alert-log-failure-message');

        var sentAt = button.getAttribute('data-alert-sent-at') || '–';
        var serviceName = button.getAttribute('data-alert-service') || '–';
        var jobId = button.getAttribute('data-alert-job-id') || '';
        var jobUrl = button.getAttribute('data-alert-job-url') || '';
        var triggerCountRaw = button.getAttribute('data-alert-trigger-count') || '1';
        var triggerCount = parseInt(triggerCountRaw, 10);
        if (isNaN(triggerCount) || triggerCount < 1) triggerCount = 1;
        var jobIdsRaw = button.getAttribute('data-alert-job-ids') || '[]';
        var status = button.getAttribute('data-alert-status') || '';
        var failure = button.getAttribute('data-alert-failure') || '';
        var jobIds;
        try {
            jobIds = JSON.parse(jobIdsRaw);
        } catch (err) {
            jobIds = [];
        }

        if (sentAtEl) sentAtEl.textContent = sentAt || '–';
        if (serviceEl) serviceEl.textContent = serviceName || '–';

        if (jobEl) {
            if (jobId && jobUrl) {
                var link = document.createElement('a');
                link.href = jobUrl;
                link.className = 'link font-mono text-xs';
                link.textContent = String(jobId);
                link.setAttribute('data-navigate', 'true');
                jobEl.innerHTML = '';
                jobEl.appendChild(link);
            } else {
                jobEl.textContent = '–';
            }
        }

        if (eventsWrapperEl) {
            if (triggerCount > 1) {
                eventsWrapperEl.classList.remove('hidden');
                if (eventsCountEl) eventsCountEl.textContent = triggerCount;
            } else {
                eventsWrapperEl.classList.add('hidden');
                if (eventsCountEl) eventsCountEl.textContent = '';
            }
        }

        if (jobIdsEl) jobIdsEl.innerHTML = '';
        if (jobIdsMoreEl) jobIdsMoreEl.textContent = '';
        if (jobIdsWrapperEl) {
            if (Array.isArray(jobIds) && jobIds.length > 0) {
                var totals = {};
                for (var i = 0; i < jobIds.length; i++) {
                    var key = String(jobIds[i]);
                    if (!Object.prototype.hasOwnProperty.call(totals, key)) totals[key] = 0;
                    totals[key]++;
                }
                var keys = Object.keys(totals);
                var limitedKeys = keys.slice(0, 25);
                for (var j = 0; j < limitedKeys.length; j++) {
                    var jid = limitedKeys[j];
                    var count = totals[jid];
                    var chip = document.createElement('span');
                    chip.className = 'inline-flex items-center rounded border border-border px-1.5 py-0.5 text-[11px] font-mono text-muted-foreground bg-muted/40';
                    chip.innerHTML = jid + ' <span class="mx-1 text-xs text-foreground">×</span> ' + count;
                    jobIdsEl.appendChild(chip);
                }
                if (keys.length > limitedKeys.length) {
                    jobIdsMoreEl.textContent = '+' + (keys.length - limitedKeys.length) + ' more job types';
                }
                jobIdsWrapperEl.classList.remove('hidden');
            } else {
                jobIdsWrapperEl.classList.add('hidden');
            }
        }

        if (statusEl) {
            statusEl.innerHTML = '';
            var span = document.createElement('span');
            span.className = status === 'sent' ? 'badge-success' : 'badge-danger';
            span.textContent = status === 'sent' ? 'sent' : 'failed';
            statusEl.appendChild(span);
        }

        if (failureWrapperEl && failureMessageEl) {
            if (status === 'failed' && failure) {
                failureWrapperEl.classList.remove('hidden');
                failureMessageEl.textContent = failure;
            } else {
                failureWrapperEl.classList.add('hidden');
                failureMessageEl.textContent = '';
            }
        }

        modal.classList.remove('hidden');
    }

    function closeModal() {
        var modal = getModal();
        if (modal) modal.classList.add('hidden');
    }

    function init() {
        document.body.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-alert-log]');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                openModal(btn);
            }
            if (e.target.closest('[data-alert-log-close]')) {
                e.preventDefault();
                closeModal();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
