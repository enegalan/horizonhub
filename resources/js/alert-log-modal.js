import { onDocumentReady } from './utils/init';
import { parseJson } from './utils/parse';

(function () {

    function populateHeader(sentAtEl, serviceEl, sentAt, serviceName) {
        if (sentAtEl) sentAtEl.textContent = sentAt || '–';
        if (serviceEl) serviceEl.textContent = serviceName || '–';
    }

    function populateEventsCount(jobEl, triggerCount) {
        if (!jobEl) return;

        jobEl.innerHTML = '';
        if (triggerCount >= 1) {
            jobEl.textContent = triggerCount === 1 ? '1 event' : triggerCount + ' events';
        } else {
            jobEl.textContent = '–';
        }
    }

    function populateEvents(eventsWrapperEl, eventsCountEl, triggerCount) {
        if (!eventsWrapperEl) return;

        if (triggerCount > 1) {
            eventsWrapperEl.classList.remove('hidden');
            if (eventsCountEl) eventsCountEl.textContent = triggerCount;
        } else {
            eventsWrapperEl.classList.add('hidden');
            if (eventsCountEl) eventsCountEl.textContent = '';
        }
    }

    function populateJobIds(jobIdsWrapperEl, jobIdsEl, jobIdsMoreEl, jobIds) {
        if (jobIdsEl) {
            jobIdsEl.innerHTML = '';
        }
        if (jobIdsMoreEl) {
            jobIdsMoreEl.textContent = '';
        }

        if (!jobIdsWrapperEl) return;

        if (!Array.isArray(jobIds) || jobIds.length === 0) {
            jobIdsWrapperEl.classList.add('hidden');
            return;
        }

        var totals = {};
        for (var i = 0; i < jobIds.length; i++) {
            var key = String(jobIds[i]);
            if (!Object.prototype.hasOwnProperty.call(totals, key)) {
                totals[key] = 0;
            }
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
            if (jobIdsEl) jobIdsEl.appendChild(chip);
        }
        if (keys.length > limitedKeys.length && jobIdsMoreEl) {
            jobIdsMoreEl.textContent = '+' + (keys.length - limitedKeys.length) + ' more job types';
        }
        jobIdsWrapperEl.classList.remove('hidden');
    }

    function populateStatus(statusEl, status) {
        if (!statusEl) return;

        statusEl.innerHTML = '';
        var span = document.createElement('span');
        span.className = status === 'sent' ? 'badge-success' : 'badge-danger';
        span.textContent = status === 'sent' ? 'sent' : 'failed';
        statusEl.appendChild(span);
    }

    function populateFailure(failureWrapperEl, failureMessageEl, status, failure) {
        if (!failureWrapperEl || !failureMessageEl) return;

        if (status === 'failed' && failure) {
            failureWrapperEl.classList.remove('hidden');
            failureMessageEl.textContent = failure;
        } else {
            failureWrapperEl.classList.add('hidden');
            failureMessageEl.textContent = '';
        }
    }

    function openModal(button) {
        var modal = document.getElementById('alert-log-modal');
        if (!modal) return;

        var triggerCount = parseInt(button.getAttribute('data-alert-trigger-count', '1'), 10);
        if (isNaN(triggerCount) || triggerCount < 1) triggerCount = 1;

        var status = button.getAttribute('data-alert-status');
        var jobIds = parseJson(button.getAttribute('data-alert-job-ids'), []);
        if (!Array.isArray(jobIds)) jobIds = [];

        populateHeader(document.getElementById('alert-log-sent-at'), document.getElementById('alert-log-service'), button.getAttribute('data-alert-sent-at'), button.getAttribute('data-alert-service'));
        populateEventsCount(document.getElementById('alert-log-job'), triggerCount);
        populateEvents(document.getElementById('alert-log-events-wrapper'), document.getElementById('alert-log-events-count'), triggerCount);
        populateJobIds(document.getElementById('alert-log-job-ids-wrapper'), document.getElementById('alert-log-job-ids'), document.getElementById('alert-log-job-ids-more'), jobIds);
        populateStatus(document.getElementById('alert-log-status'), status);
        populateFailure(document.getElementById('alert-log-failure-wrapper'), document.getElementById('alert-log-failure-message'), status, button.getAttribute('data-alert-failure'));

        modal.classList.remove('hidden');
    }

    function closeModal() {
        var modal = document.getElementById('alert-log-modal');
        if (modal) modal.classList.add('hidden');
    }

    function init() {
        if (!document.body) return;

        document.body.addEventListener('click', e => {
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

    onDocumentReady(init);
})();
