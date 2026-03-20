import { formatDateTimeElements } from '../lib/datetime-format';
import { initAlertDetailCharts } from '../charts/metrics-charts';

/**
 * Horizon alerts list.
 * @returns {object}
 */
export function horizonAlertsList() {
    return {
        bulkEvaluationInProgress: false,

        /**
         * Initialize the alerts list.
         * @returns {void}
         */
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            window.__horizonAlertsListEvaluationInstance = self;

            window.addEventListener('horizonhub-refresh', function (e) {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshAlertsList(e.detail && e.detail.document);
            });

            // Event delegation for evaluation buttons (table rows may be replaced on refresh).
            // Attach the document click listener only once to avoid duplicate toasts.
            if (!window.__horizonAlertsListEvaluationClickListenerAttached) {
                window.__horizonAlertsListEvaluationClickListenerAttached = true;
                window.__horizonAlertsListEvaluationClickListener = function (e) {
                    if (typeof document === 'undefined') return;
                    var instance = window.__horizonAlertsListEvaluationInstance;
                    if (!instance) return;

                    var evaluateAllBtn = e.target && e.target.closest ? e.target.closest('[data-alert-evaluate-all-button="1"]') : null;
                    if (evaluateAllBtn) {
                        e.preventDefault();
                        instance.private__handleEvaluateAllClick(evaluateAllBtn);
                        return;
                    }

                    var evalBtn = e.target && e.target.closest ? e.target.closest('[data-alert-evaluate-button="1"]') : null;
                    if (evalBtn) {
                        e.preventDefault();
                        instance.private__handleEvaluateAlertClick(evalBtn);
                    }
                };
                document.addEventListener('click', window.__horizonAlertsListEvaluationClickListener);
            }
        },

        private__setEvaluateButtonLoading(buttonEl, isLoading) {
            if (!buttonEl) return;
            var iconEl = buttonEl.querySelector('.alert-evaluate-btn-icon');
            var spinnerEl = buttonEl.querySelector('.alert-evaluate-btn-spinner');
            var initialDisabled = buttonEl.getAttribute && buttonEl.getAttribute('data-alert-evaluate-initial-disabled') === '1';
            buttonEl.disabled = !!isLoading ? true : !!initialDisabled;
            buttonEl.setAttribute('aria-busy', isLoading ? 'true' : 'false');
            if (iconEl) {
                iconEl.classList.toggle('hidden', !!isLoading);
            }
            if (spinnerEl) {
                spinnerEl.classList.toggle('hidden', !isLoading);
            }
        },

        private__setAllEvaluateButtonsDisabled(disabled) {
            if (typeof document === 'undefined') return;
            var buttons = document.querySelectorAll('[data-alert-evaluate-button="1"], [data-alert-evaluate-all-button="1"]');
            if (!buttons || !buttons.length) return;
            buttons.forEach(function (b) {
                if (!!disabled) {
                    b.disabled = true;
                    b.setAttribute('aria-busy', 'true');
                    return;
                }
                var initialDisabled = b.getAttribute && b.getAttribute('data-alert-evaluate-initial-disabled') === '1';
                b.disabled = !!initialDisabled;
                b.setAttribute('aria-busy', 'false');
            });
        },

        private__handleEvaluateAlertClick(btnEl) {
            var self = this;
            if (!window.horizon || !window.horizon.http) return;
            if (!btnEl) return;
            var alreadyRunning = btnEl.getAttribute && btnEl.getAttribute('data-alert-evaluation-running') === '1';
            if (alreadyRunning) return;
            if (btnEl.disabled) return;
            if (btnEl.getAttribute && btnEl.getAttribute('aria-busy') === 'true') return;
            if (self.bulkEvaluationInProgress) return;
            var url = btnEl.getAttribute('data-alert-evaluate-url');
            var alertId = btnEl.getAttribute('data-alert-id');
            if (!url || !alertId) return;
            btnEl.setAttribute('data-alert-evaluation-running', '1');
            self.private__setEvaluateButtonLoading(btnEl, true);

            window.horizon.http.post(url, {}).then(function (data) {
                var errorMessage = data && data.error_message ? data.error_message : null;
                var triggered = !!(data && data.triggered);
                var triggeredServiceId = data && data.triggered_service_id ? data.triggered_service_id : null;
            var delivered = !!(data && data.delivered);

                if (errorMessage) {
                    if (window.toast && window.toast.error) {
                        window.toast.error(errorMessage);
                    }
                    return;
                }

                if (triggered) {
                if (delivered && window.toast && window.toast.success) {
                    window.toast.success('Alert #' + alertId + ' triggered and delivery sent' + (triggeredServiceId ? ' (service ' + triggeredServiceId + ')' : '') + '.');
                } else if (window.toast && window.toast.info) {
                    window.toast.info('Alert #' + alertId + ' triggered' + (triggeredServiceId ? ' (service ' + triggeredServiceId + ')' : '') + '; delivery batched.');
                    }
                } else {
                if (delivered && window.toast && window.toast.info) {
                    window.toast.info('Alert #' + alertId + ' delivery flushed.');
                } else if (window.toast && window.toast.info) {
                    window.toast.info('Alert #' + alertId + ' did not trigger.');
                    }
                }
            }).catch(function (err) {
                // defaultApiErrorHandler already shows a toast.
            }).finally(function () {
                btnEl.removeAttribute('data-alert-evaluation-running');
                self.private__setEvaluateButtonLoading(btnEl, false);
            });
        },

        private__handleEvaluateAllClick(bulkBtnEl) {
            var self = this;
            if (!window.horizon || !window.horizon.http) return;
            if (!bulkBtnEl) return;
            var alreadyRunning = bulkBtnEl.getAttribute && bulkBtnEl.getAttribute('data-alert-evaluation-running') === '1';
            if (alreadyRunning) return;
            if (bulkBtnEl.disabled) return;
            if (self.bulkEvaluationInProgress) return;
            var bulkUrl = bulkBtnEl.getAttribute('data-alert-evaluate-all-url');
            var statusUrlTemplate = bulkBtnEl.getAttribute('data-alert-evaluate-all-status-url');
            if (!bulkUrl || !statusUrlTemplate) return;

            self.bulkEvaluationInProgress = true;
            bulkBtnEl.setAttribute('data-alert-evaluation-running', '1');
            self.private__setAllEvaluateButtonsDisabled(true);
            self.private__setEvaluateButtonLoading(bulkBtnEl, true);

            var labelEl = bulkBtnEl.querySelector('[data-alert-evaluate-all-label]');
            if (labelEl) labelEl.textContent = 'Evaluating...';

            window.toast && window.toast.info && window.toast.info('Evaluation started for all alerts.');

            window.horizon.http.post(bulkUrl, {}).then(function (data) {
                var evaluationId = data && data.evaluation_id ? data.evaluation_id : null;
                var totalAlerts = data && typeof data.total_alerts === 'number' ? data.total_alerts : null;
                if (!evaluationId) {
                    window.toast && window.toast.error && window.toast.error('Unable to start evaluation.');
                    return;
                }

                self.private__pollEvaluationStatus({
                    evaluationId: evaluationId,
                    statusUrlTemplate: statusUrlTemplate,
                    bulkBtnEl: bulkBtnEl,
                    totalAlerts: totalAlerts
                });
            }).catch(function (err) {
                // defaultApiErrorHandler already shows a toast.
                self.bulkEvaluationInProgress = false;
                bulkBtnEl.removeAttribute('data-alert-evaluation-running');
                self.private__setAllEvaluateButtonsDisabled(false);
                self.private__setEvaluateButtonLoading(bulkBtnEl, false);
                if (labelEl) labelEl.textContent = 'Evaluate all alerts';
            });
        },

        private__pollEvaluationStatus(params) {
            var self = this;
            if (!params || !params.evaluationId || !params.statusUrlTemplate) return;
            var evaluationId = params.evaluationId;
            var statusUrlTemplate = params.statusUrlTemplate;
            var bulkBtnEl = params.bulkBtnEl;

            var statusUrl = statusUrlTemplate.replace('__EVALUATION_ID__', encodeURIComponent(evaluationId));
            var intervalMs = 2000;
            var startTs = Date.now();
            var maxPollMs = 180000; // 3 minutes

            var intervalId = null;
            var stopped = false;

            function stop() {
                if (stopped) return;
                stopped = true;
                if (intervalId) clearInterval(intervalId);
                self.bulkEvaluationInProgress = false;
                self.private__setAllEvaluateButtonsDisabled(false);
                self.private__setEvaluateButtonLoading(bulkBtnEl, false);
                var labelEl = bulkBtnEl.querySelector('[data-alert-evaluate-all-label]');
                if (labelEl) labelEl.textContent = 'Evaluate all alerts';
            }

            intervalId = window.setInterval(function () {
                if (Date.now() - startTs > maxPollMs) {
                    stop();
                    if (window.toast && window.toast.error) {
                        window.toast.error('Bulk evaluation timed out. Check queue workers and retry.');
                    }
                    return;
                }

                window.horizon.http.get(statusUrl).then(function (data) {
                    if (!data) return;
                    var status = data.status || 'running';
                    var totalAlerts = typeof data.total_alerts === 'number' ? data.total_alerts : 0;
                    var evaluatedCount = typeof data.evaluated_count === 'number' ? data.evaluated_count : 0;

                    var labelEl = bulkBtnEl.querySelector('[data-alert-evaluate-all-label]');
                    if (labelEl) labelEl.textContent = 'Evaluating ' + evaluatedCount + '/' + totalAlerts;

                    if (status === 'completed' || evaluatedCount >= totalAlerts) {
                        stop();

                        var triggeredCount = typeof data.triggered_count === 'number' ? data.triggered_count : 0;
                        var deliveredCount = typeof data.delivered_count === 'number' ? data.delivered_count : 0;
                        var errorCount = typeof data.error_count === 'number' ? data.error_count : 0;
                        var firstErrorMessage = data.first_error_message || data.error_message || null;

                        if (errorCount > 0 && window.toast && window.toast.error) {
                            window.toast.error(errorCount + ' alert(s) failed during evaluation' + (firstErrorMessage ? ': ' + firstErrorMessage : '.'));
                            return;
                        }

                        if (triggeredCount > 0) {
                            if (deliveredCount > 0 && window.toast && window.toast.success) {
                                window.toast.success(triggeredCount + ' alert(s) triggered (' + deliveredCount + ' delivered) during evaluation.');
                            } else if (window.toast && window.toast.info) {
                                window.toast.info(triggeredCount + ' alert(s) triggered during evaluation; delivery batched.');
                            }
                            return;
                        }

                        if (deliveredCount > 0 && window.toast && window.toast.warning) {
                            window.toast.warning('No alerts triggered, but ' + deliveredCount + ' delivery batch(es) flushed during evaluation.');
                            return;
                        }

                        if (window.toast && window.toast.warning) {
                            window.toast.warning('No alerts triggered during evaluation.');
                        }
                    }

                    if (status === 'failed') {
                        stop();
                        var msg = data.error_message || 'Bulk evaluation failed.';
                        if (window.toast && window.toast.error) window.toast.error(msg);
                    }
                }).catch(function () {
                    // Keep polling even on transient errors.
                });
            }, intervalMs);
        },
        /**
         * Refresh the alerts list.
         * @param {Document} preloadedDoc
         * @returns {void}
         */
        refreshAlertsList(preloadedDoc) {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            if (!preloadedDoc) return;
            var newTable = preloadedDoc.querySelector('[data-resizable-table="horizon-alerts-list"]');
            var currentTable = document.querySelector('[data-resizable-table="horizon-alerts-list"]');
            if (!newTable || !currentTable) return;
            var newTbody = newTable.querySelector('tbody');
            var currentTbody = currentTable.querySelector('tbody');
            if (newTbody && currentTbody) {
                currentTbody.replaceWith(newTbody);
                if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                    window.horizonInitResizableTables();
                }
                if (typeof window !== 'undefined') {
                    formatDateTimeElements(currentTable);
                }
            }
        },
    };
}

/**
 * Horizon alert detail.
 * @returns {object}
 */
export function horizonAlertDetail(config) {
    return {
        showDeliveryLogModal: false,
        deliveryLogModalMounted: false,
        deliveryLog: null,
        normalizeDeliveryLog(logData) {
            if (!logData || typeof logData !== 'object') {
                return null;
            }
            var normalized = Object.assign({}, logData);
            var eventsCount = Number.parseInt(String(normalized.events_count ?? 0), 10);
            if (!Number.isFinite(eventsCount) || eventsCount < 0) {
                eventsCount = 0;
            }
            var jobItems = Array.isArray(normalized.job_items) ? normalized.job_items : [];
            var visibleJobTypesCount = jobItems.length;
            var incomingMore = Number.parseInt(String(normalized.job_ids_more ?? 0), 10);
            if (!Number.isFinite(incomingMore) || incomingMore < 0) {
                incomingMore = 0;
            }
            var incomingTotalJobTypesCount = visibleJobTypesCount + incomingMore;
            var effectiveTotalJobTypesCount = Math.min(incomingTotalJobTypesCount, eventsCount);
            normalized.job_ids_more = Math.max(0, effectiveTotalJobTypesCount - visibleJobTypesCount);
            normalized.job_items = jobItems;
            normalized.events_count = eventsCount;
            return normalized;
        },
        /**
         * Initialize the alert detail.
         * @returns {void}
         */
        init() {
            var self = this;
            if (typeof window === 'undefined') return;

            if (config && config.initialDeliveryLog) {
                self.openDeliveryLogModal(self.normalizeDeliveryLog(config.initialDeliveryLog));
            }

            window.addEventListener('horizonhub-refresh', function (e) {
                if (typeof document === 'undefined') return;
                if (document.visibilityState !== 'visible') return;
                self.refreshAlertDetail(e.detail && e.detail.document);
            });
        },
        openDeliveryLogModal(logData) {
            if (logData) {
                this.deliveryLog = this.normalizeDeliveryLog(logData);
            }
            if (!this.deliveryLog) {
                return;
            }
            this.deliveryLogModalMounted = true;
            this.showDeliveryLogModal = false;
            requestAnimationFrame(() => {
                this.showDeliveryLogModal = true;
            });
        },
        closeDeliveryLogModal() {
            this.showDeliveryLogModal = false;
            window.setTimeout(() => {
                if (!this.showDeliveryLogModal) {
                    this.deliveryLogModalMounted = false;
                    this.deliveryLog = null;
                }
            }, 220);
        },
        refreshAlertDetail(preloadedDoc) {
            if (typeof window === 'undefined' || typeof document === 'undefined') return;
            if (!preloadedDoc) return;

            var newRoot = preloadedDoc.querySelector('[data-horizon-alert-detail-root="1"]');
            var currentRoot = document.querySelector('[data-horizon-alert-detail-root="1"]');
            if (!newRoot || !currentRoot) return;

            var modalOpen = this.deliveryLogModalMounted;

            function replaceSection(selector) {
                var cur = currentRoot.querySelector(selector);
                var neu = newRoot.querySelector(selector);
                if (cur && neu) cur.replaceWith(neu);
            }

            replaceSection('[data-alert-detail-breadcrumb]');
            replaceSection('[data-alert-detail-stats]');
            replaceSection('[data-alert-detail-rule]');
            if (!modalOpen) {
                replaceSection('[data-alert-detail-after-charts]');
            }

            var chartData = null;
            var scriptEl = document.getElementById('alert-detail-chart-data');
            if (scriptEl && scriptEl.textContent) {
                try {
                    chartData = JSON.parse(scriptEl.textContent.trim());
                } catch (e) {}
            }

            if (typeof window !== 'undefined' && window.horizonInitResizableTables) {
                window.horizonInitResizableTables();
            }

            formatDateTimeElements(currentRoot);

            var chartIds = ['alert-detail-chart-24h', 'alert-detail-chart-7d', 'alert-detail-chart-30d'];
            if (chartData && window.echarts) {
                chartIds.forEach(function (id) {
                    var el = currentRoot.querySelector('#' + id);
                    if (el) {
                        var instance = window.echarts.getInstanceByDom(el);
                        if (instance) instance.dispose();
                    }
                });
                requestAnimationFrame(function () {
                    initAlertDetailCharts(chartData);
                    requestAnimationFrame(function () {
                        chartIds.forEach(function (id) {
                            var el = document.getElementById(id);
                            if (el) {
                                var inst = window.echarts.getInstanceByDom(el);
                                if (inst) inst.resize();
                            }
                        });
                    });
                });
            }
        },
    };
}
