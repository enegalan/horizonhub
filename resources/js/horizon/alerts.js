import { getChartColors, applyChartOptions } from '../charts/metrics-charts';
import { parseJsonFromElement } from '../lib/parse';
import { isHotReloadEnabled } from '../lib/sse';

/**
 * Alert detail charts.
 * @type {object}
 */
var ALERT_DETAIL_CHARTS = [
    { key: 'chart24h', id: 'alert-detail-chart-24h' },
    { key: 'chart7d', id: 'alert-detail-chart-7d' },
    { key: 'chart30d', id: 'alert-detail-chart-30d' }
];

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

            window.__horizonAlertsListEvaluationInstance = self;
            // Attach the document click listener only once to avoid duplicate toasts.
            if (!window.__horizonAlertsListEvaluationClickListenerAttached) {
                window.__horizonAlertsListEvaluationClickListenerAttached = true;
                window.__horizonAlertsListEvaluationClickListener = function (e) {
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
                        return;
                    }

                    var enabledToggleBtn = e.target && e.target.closest ? e.target.closest('[data-alert-enabled-toggle="1"]') : null;
                    if (enabledToggleBtn) {
                        e.preventDefault();
                        instance.private__handleEnabledToggleClick(enabledToggleBtn);
                    }
                };
                document.addEventListener('click', window.__horizonAlertsListEvaluationClickListener);
            }
        },

        /**
         * Resolve the current evaluate button node (stream patches may replace the clicked element).
         * @param {string|null} alertId
         * @param {HTMLElement|null} fallbackEl
         * @returns {HTMLElement|null}
         */
        private__resolveEvaluateButton(alertId, fallbackEl) {
            if (!alertId || typeof document === 'undefined') {
                return fallbackEl || null;
            }
            var selector = '[data-alert-evaluate-button="1"][data-alert-id="' + String(alertId).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]';
            var resolved = document.querySelector(selector);
            return resolved || fallbackEl || null;
        },

        /**
         * Set evaluate button loading state.
         * @param {HTMLElement} buttonEl
         * @param {boolean} isLoading
         * @returns {void}
         */
        private__setEvaluateButtonLoading(buttonEl, isLoading) {
            if (!buttonEl) return;
            var iconEl = buttonEl.querySelector('.alert-evaluate-btn-icon');
            var spinnerEl = buttonEl.querySelector('.alert-evaluate-btn-spinner');
            var initialDisabled = buttonEl.getAttribute && buttonEl.getAttribute('data-alert-evaluate-initial-disabled') === '1';
            buttonEl.disabled = isLoading ? true : initialDisabled;
            buttonEl.setAttribute('aria-busy', isLoading ? 'true' : 'false');
            if (iconEl) {
                iconEl.classList.toggle('hidden', !!isLoading);
            }
            if (spinnerEl) {
                spinnerEl.classList.toggle('hidden', !isLoading);
            }
        },

        /**
         * Set all evaluate buttons disabled state.
         * @param {boolean} disabled
         * @returns {void}
         */
        private__setAllEvaluateButtonsDisabled(disabled) {
            if (typeof document === 'undefined') return;
            var buttons = document.querySelectorAll('[data-alert-evaluate-button="1"], [data-alert-evaluate-all-button="1"]');
            if (!buttons || !buttons.length) return;
            buttons.forEach(function (b) {
                if (disabled) {
                    b.disabled = true;
                    b.setAttribute('aria-busy', 'true');
                    return;
                }
                var initialDisabled = b.getAttribute && b.getAttribute('data-alert-evaluate-initial-disabled') === '1';
                b.disabled = !!initialDisabled;
                b.setAttribute('aria-busy', 'false');
            });
        },

        /**
         * Set the evaluate-all button label.
         * @param {HTMLElement} bulkBtnEl
         * @param {string} label
         * @returns {void}
         */
        private__setEvaluateAllLabel(bulkBtnEl, label) {
            var labelEl = bulkBtnEl && bulkBtnEl.querySelector
                ? bulkBtnEl.querySelector('[data-alert-evaluate-all-label]')
                : null;
            if (labelEl) {
                labelEl.textContent = label;
            }
        },

        /**
         * Reset bulk evaluation button and list state.
         * @param {HTMLElement} bulkBtnEl
         * @returns {void}
         */
        private__resetEvaluateAllButton(bulkBtnEl) {
            this.bulkEvaluationInProgress = false;
            if (bulkBtnEl && bulkBtnEl.removeAttribute) {
                bulkBtnEl.removeAttribute('data-alert-evaluation-running');
            }
            this.private__setAllEvaluateButtonsDisabled(false);
            this.private__setEvaluateButtonLoading(bulkBtnEl, false);
            this.private__setEvaluateAllLabel(bulkBtnEl, 'Evaluate all alerts');
        },

        /**
         * Apply enabled state to an alert card.
         * @param {HTMLElement} articleEl
         * @param {boolean} enabled
         * @returns {void}
         */
        private__applyAlertEnabledState(articleEl, enabled) {
            if (!articleEl) return;

            var hoverBorderClasses = [
                'hover:border-emerald-500/45',
                'dark:hover:border-emerald-400/50',
                'hover:border-amber-500/45',
                'dark:hover:border-amber-400/50',
            ];
            articleEl.classList.remove.apply(articleEl.classList, hoverBorderClasses);
            if (enabled) {
                articleEl.classList.add('hover:border-emerald-500/45', 'dark:hover:border-emerald-400/50');
            } else {
                articleEl.classList.add('hover:border-amber-500/45', 'dark:hover:border-amber-400/50');
            }

            var accentEl = articleEl.querySelector('[data-alert-enabled-accent="1"]');
            if (accentEl) {
                accentEl.classList.remove(
                    'bg-gradient-to-r',
                    'from-emerald-500/80',
                    'via-emerald-400/60',
                    'to-transparent',
                    'from-amber-500/80',
                    'via-amber-400/60'
                );
                if (enabled) {
                    accentEl.classList.add(
                        'bg-gradient-to-r',
                        'from-emerald-500/80',
                        'via-emerald-400/60',
                        'to-transparent'
                    );
                } else {
                    accentEl.classList.add(
                        'bg-gradient-to-r',
                        'from-amber-500/80',
                        'via-amber-400/60',
                        'to-transparent'
                    );
                }
            }

            var iconEl = articleEl.querySelector('[data-alert-enabled-icon="1"]');
            if (iconEl) {
                iconEl.classList.remove(
                    'border-emerald-500/20',
                    'bg-emerald-500/10',
                    'text-emerald-700',
                    'dark:text-emerald-300',
                    'border-amber-500/20',
                    'bg-amber-500/10',
                    'text-amber-700',
                    'dark:text-amber-300'
                );
                if (enabled) {
                    iconEl.classList.add(
                        'border-emerald-500/20',
                        'bg-emerald-500/10',
                        'text-emerald-700',
                        'dark:text-emerald-300'
                    );
                } else {
                    iconEl.classList.add(
                        'border-amber-500/20',
                        'bg-amber-500/10',
                        'text-amber-700',
                        'dark:text-amber-300'
                    );
                }
            }

            var toggleBtn = articleEl.querySelector('[data-alert-enabled-toggle="1"]');
            if (toggleBtn) {
                toggleBtn.setAttribute('data-alert-enabled', enabled ? '1' : '0');
                toggleBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                toggleBtn.setAttribute('aria-label', enabled ? 'Disable alert' : 'Enable alert');
                toggleBtn.setAttribute('title', enabled ? 'Disable alert' : 'Enable alert');
            }

            var badgeEl = articleEl.querySelector('[data-alert-enabled-badge="1"]');
            if (badgeEl) {
                badgeEl.classList.remove('badge-success', 'badge-danger');
                badgeEl.classList.add(enabled ? 'badge-success' : 'badge-danger');
                badgeEl.textContent = enabled ? 'On' : 'Off';
            }

            var evaluateBtn = articleEl.querySelector('[data-alert-evaluate-button="1"]');
            if (evaluateBtn) {
                evaluateBtn.setAttribute('data-alert-evaluate-initial-disabled', enabled ? '0' : '1');
                if (evaluateBtn.getAttribute('aria-busy') !== 'true') {
                    evaluateBtn.disabled = !enabled;
                }
            }
        },

        /**
         * Handle enabled toggle click.
         * @param {HTMLElement} btnEl
         * @returns {void}
         */
        private__handleEnabledToggleClick(btnEl) {
            var self = this;
            if (!window.horizon || !window.horizon.http || !btnEl) return;
            if (btnEl.disabled || btnEl.getAttribute('data-alert-enabled-toggle-running') === '1') return;

            var url = btnEl.getAttribute('data-alert-enabled-toggle-url');
            var articleEl = btnEl.closest('[data-stream-row-id]');
            if (!url || !articleEl) return;

            btnEl.setAttribute('data-alert-enabled-toggle-running', '1');
            btnEl.disabled = true;

            window.horizon.http.post(url, {}).then(function (data) {
                var enabled = !!(data && data.enabled);
                self.private__applyAlertEnabledState(articleEl, enabled);
                if (!isHotReloadEnabled()) {
                    window.location.reload();
                }
            }).catch(function () {
            }).finally(function () {
                btnEl.removeAttribute('data-alert-enabled-toggle-running');
                btnEl.disabled = false;
            });
        },

        /**
         * Handle evaluate alert click.
         * @param {HTMLElement} btnEl
         * @returns {void}
         */
        private__handleEvaluateAlertClick(btnEl) {
            var self = this;
            if (!window.horizon || !window.horizon.http || !btnEl) return;
            var alreadyRunning = btnEl.getAttribute && btnEl.getAttribute('data-alert-evaluation-running') === '1' || self.bulkEvaluationInProgress;
            if (alreadyRunning || btnEl.disabled || btnEl.getAttribute && btnEl.getAttribute('aria-busy') === 'true') return;
            var url = btnEl.getAttribute('data-alert-evaluate-url');
            var alertId = btnEl.getAttribute('data-alert-id');
            var alertName = btnEl.getAttribute('data-alert-name');
            var alertLabel = alertName && alertName.trim() !== '' ? '"' + alertName + '"' : '#' + alertId;
            if (!url || !alertId) return;
            btnEl.setAttribute('data-alert-evaluation-running', '1');
            self.private__setEvaluateButtonLoading(btnEl, true);

            window.horizon.http.post(url, {}).then(function (data) {
                var errorMessage = data && data.error_message ? data.error_message : null;
                var triggered = !!(data && data.triggered);
                var triggeredServiceId = data && data.triggered_service_id ? data.triggered_service_id : null;
                var delivered = !!(data && data.delivered);
                var serviceSuffix = triggeredServiceId ? ' (service ' + triggeredServiceId + ')' : '';

                if (errorMessage) {
                    window.toast.error(errorMessage);
                    return;
                }

                if (triggered) {
                    if (delivered) {
                        window.toast.success('Alert ' + alertLabel + ' triggered and delivery sent' + serviceSuffix + '.');
                        return;
                    }

                    window.toast.info('Alert ' + alertLabel + ' triggered' + serviceSuffix + '; delivery batched.');
                    return;
                }

                if (delivered) {
                    window.toast.warning('Alert ' + alertLabel + ' did not trigger; a pending delivery batch was flushed.');
                    return;
                }

                window.toast.warning('Alert ' + alertLabel + ' did not trigger.');
            }).catch(function (_err) {
            }).finally(function () {
                var currentBtn = self.private__resolveEvaluateButton(alertId, btnEl);
                if (currentBtn && currentBtn.removeAttribute) {
                    currentBtn.removeAttribute('data-alert-evaluation-running');
                }
                self.private__setEvaluateButtonLoading(currentBtn, false);
            });
        },

        /**
         * Handle evaluate all click.
         * @param {HTMLElement} bulkBtnEl
         * @returns {void}
         */
        private__handleEvaluateAllClick(bulkBtnEl) {
            var self = this;
            if (!window.horizon || !window.horizon.http || !bulkBtnEl) return;
            var alreadyRunning = bulkBtnEl.getAttribute && bulkBtnEl.getAttribute('data-alert-evaluation-running') === '1' || self.bulkEvaluationInProgress || bulkBtnEl.disabled;
            if (alreadyRunning) return;
            var bulkUrl = bulkBtnEl.getAttribute('data-alert-evaluate-all-url');
            var statusUrlTemplate = bulkBtnEl.getAttribute('data-alert-evaluate-all-status-url');
            if (!bulkUrl || !statusUrlTemplate) return;

            self.bulkEvaluationInProgress = true;
            bulkBtnEl.setAttribute('data-alert-evaluation-running', '1');
            self.private__setAllEvaluateButtonsDisabled(true);
            self.private__setEvaluateButtonLoading(bulkBtnEl, true);
            self.private__setEvaluateAllLabel(bulkBtnEl, 'Evaluating...');

            window.horizon.http.post(bulkUrl, {}).then(function (data) {
                var evaluationId = data && data.evaluation_id ? data.evaluation_id : null;
                var totalAlerts = data && typeof data.total_alerts === 'number' ? data.total_alerts : null;
                if (!evaluationId) {
                    self.private__resetEvaluateAllButton(bulkBtnEl);
                    window.toast.error('Unable to start evaluation.');
                    return;
                }

                window.toast.info('Evaluation started for all alerts.');
                self.private__pollEvaluationStatus({
                    evaluationId: evaluationId,
                    statusUrlTemplate: statusUrlTemplate,
                    bulkBtnEl: bulkBtnEl,
                    totalAlerts: totalAlerts
                });
            }).catch(function (_err) {
                self.private__resetEvaluateAllButton(bulkBtnEl);
            });
        },

        /**
         * Poll evaluation status.
         * @param {object} params
         * @returns {void}
         */
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
                self.private__resetEvaluateAllButton(bulkBtnEl);
            }

            intervalId = window.setInterval(function () {
                if (Date.now() - startTs > maxPollMs) {
                    stop();
                    window.toast.error('Bulk evaluation timed out. Check queue workers and retry.');
                    return;
                }

                window.horizon.http.get(statusUrl).then(function (data) {
                    if (!data) return;
                    var totalAlerts = typeof data.total_alerts === 'number' ? data.total_alerts : 0;
                    var evaluatedCount = typeof data.evaluated_count === 'number' ? data.evaluated_count : 0;

                    self.private__setEvaluateAllLabel(bulkBtnEl, 'Evaluating ' + evaluatedCount + '/' + totalAlerts);

                    if (data.status === 'completed' || evaluatedCount >= totalAlerts) {
                        stop();
                        var triggeredCount = typeof data.triggered_count === 'number' ? data.triggered_count : 0;
                        var deliveredCount = typeof data.delivered_count === 'number' ? data.delivered_count : 0;
                        var errorCount = typeof data.error_count === 'number' ? data.error_count : 0;
                        var firstErrorMessage = data.first_error_message || data.error_message || null;

                        if (errorCount > 0) {
                            window.toast.error(errorCount + ' alert(s) failed during evaluation' + (firstErrorMessage ? ': ' + firstErrorMessage : '.'));
                            return;
                        }

                        if (triggeredCount > 0) {
                            if (deliveredCount > 0) {
                                window.toast.success(triggeredCount + ' alert(s) triggered and ' + deliveredCount + ' delivery batch(es) were sent during evaluation.');
                                return;
                            }

                            window.toast.info(triggeredCount + ' alert(s) triggered during evaluation; delivery was batched.');
                            return;
                        }

                        if (deliveredCount > 0) {
                            window.toast.warning('No alerts triggered, but ' + deliveredCount + ' pending delivery batch(es) were flushed during evaluation.');
                            return;
                        }

                        window.toast.warning('No alerts triggered during evaluation.');
                    }

                    if (data.status === 'failed') {
                        stop();
                        var msg = data.error_message || 'Bulk evaluation failed.';
                        window.toast.error(msg);
                    }
                }).catch(function () {
                    // Keep polling even on transient errors.
                });
            }, intervalMs);
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
        /**
         * Initialize the alert detail.
         * @returns {void}
         */
        init() {
            var self = this;

            if (config && config.initialDeliveryLog) {
                self.openDeliveryLogModal(config.initialDeliveryLog);
            }

            // Initial hydration to initially show alert detail charts
            renderAlertDetailCharts();
        },
        /**
         * Open delivery log modal.
         * @param {object} logData
         * @returns {void}
         */
        openDeliveryLogModal(logData) {
            if (!logData || typeof logData !== 'object') {
                return;
            }
            this.deliveryLog = logData;
            this.deliveryLogModalMounted = true;
            this.showDeliveryLogModal = false;
            requestAnimationFrame(() => {
                this.showDeliveryLogModal = true;
            });
        },
        /**
         * Close delivery log modal.
         * @returns {void}
         */
        closeDeliveryLogModal() {
            this.showDeliveryLogModal = false;
            window.setTimeout(() => {
                if (!this.showDeliveryLogModal) {
                    this.deliveryLogModalMounted = false;
                    this.deliveryLog = null;
                }
            }, 220);
        },
    };
}

/**
 * Render the alert detail charts.
 * @returns {void}
 */
export function renderAlertDetailCharts() {
    if (typeof window.echarts === 'undefined') return;
    var data = parseJsonFromElement('alert-detail-chart-data-json');
    var ready = data && typeof data === 'object' && !Array.isArray(data) && data.chart24h && data.chart24h.xAxis && data.chart24h.xAxis.length;
    var loader24h = document.getElementById('alert-detail-loader-chart-24h');
    if (loader24h) {
        loader24h.style.display = !ready ? 'flex' : 'none';
    }
    var loader7d = document.getElementById('alert-detail-loader-chart-7d');
    if (loader7d) {
        loader7d.style.display = !ready ? 'flex' : 'none';
    }
    var loader30d = document.getElementById('alert-detail-loader-chart-30d');
    if (loader30d) {
        loader30d.style.display = !ready ? 'flex' : 'none';
    }
    if (!data || !ready) return;

    var c = getChartColors();

    function makeBarOption(xAxis, sent, failed) {
        return {
            animation: false,
            color: [c.processed, c.failed],
            tooltip: { trigger: 'axis' },
            legend: { data: ['Sent', 'Failed'], bottom: 0, textStyle: { color: c.axis, fontSize: 10 } },
            grid: { left: 8, right: 16, top: 16, bottom: 36, containLabel: true },
            xAxis: { type: 'category', data: xAxis, axisLine: { lineStyle: { color: c.axis } }, axisLabel: { color: c.axis, fontSize: 10 } },
            yAxis: { type: 'value', name: 'Sends', axisLine: { show: false }, splitLine: { lineStyle: { color: c.axis, opacity: 0.3 } }, axisLabel: { color: c.axis, fontSize: 10 } },
            series: [
                { type: 'bar', name: 'Sent', data: sent, barMaxWidth: 20 },
                { type: 'bar', name: 'Failed', data: failed, barMaxWidth: 20 }
            ]
        };
    }

    for (let i = 0; i < ALERT_DETAIL_CHARTS.length; i++) {
        var { key, id } = ALERT_DETAIL_CHARTS[i];
        var chartData = data[key];
        if (!chartData) continue;
        var el = document.getElementById(id);
        if (el) applyChartOptions(el, makeBarOption(chartData.xAxis, chartData.sent, chartData.failed));
    }
}
