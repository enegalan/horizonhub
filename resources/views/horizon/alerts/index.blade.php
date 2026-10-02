@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="{
            showDeleteAlertModal: false,
            deleteAlertName: '',
            deleteAlertAction: '',
            openDeleteAlertModal(name, action) {
                this.deleteAlertName = name;
                this.deleteAlertAction = action;
                this.showDeleteAlertModal = true;
            },
            closeDeleteAlertModal() {
                this.showDeleteAlertModal = false;
            },
            confirmDeleteAlert() {
                this.$refs.deleteAlertForm.requestSubmit();
                this.closeDeleteAlertModal();
            },
            bulkEvaluationInProgress: false,
            togglingAlerts: [],
            init() {
                var self = this;

                window.horizon.registerDocumentDelegate('alerts-list', self, function (e, instance) {
                    var evaluateAllBtn = e.target.closest('[data-alert-evaluate-all-button]');
                    if (evaluateAllBtn) {
                        e.preventDefault();
                        instance.private__handleEvaluateAllClick(evaluateAllBtn);
                        return;
                    }

                    var evalBtn = e.target.closest('[data-alert-evaluate-button]');
                    if (evalBtn) {
                        e.preventDefault();
                        instance.private__handleEvaluateAlertClick(evalBtn);
                        return;
                    }

                    var enabledToggleBtn = e.target.closest('[data-alert-enabled-toggle]');
                    if (enabledToggleBtn) {
                        e.preventDefault();
                        instance.private__handleEnabledToggleClick(enabledToggleBtn);
                    }
                });
            },
            private__resolveEvaluateButton(alertId, fallbackEl) {
                if (!alertId || typeof document === 'undefined') {
                    return fallbackEl || null;
                }
                var targetId = String(alertId);
                var buttons = document.querySelectorAll('[data-alert-evaluate-button]');
                for (var i = 0; i < buttons.length; i++) {
                    if (buttons[i].getAttribute('data-alert-id') === targetId) {
                        return buttons[i];
                    }
                }
                return fallbackEl || null;
            },
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
            private__setAllEvaluateButtonsDisabled(disabled) {
                if (typeof document === 'undefined') return;
                var buttons = document.querySelectorAll('[data-alert-evaluate-button], [data-alert-evaluate-all-button]');
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
            private__setEvaluateAllLabel(bulkBtnEl, label) {
                var labelEl = bulkBtnEl && bulkBtnEl.querySelector
                    ? bulkBtnEl.querySelector('[data-alert-evaluate-all-label]')
                    : null;
                if (labelEl) {
                    labelEl.textContent = label;
                }
            },
            private__resetEvaluateAllButton(bulkBtnEl) {
                this.bulkEvaluationInProgress = false;
                if (bulkBtnEl && bulkBtnEl.removeAttribute) {
                    bulkBtnEl.removeAttribute('data-alert-evaluation-running');
                }
                this.private__setAllEvaluateButtonsDisabled(false);
                this.private__setEvaluateButtonLoading(bulkBtnEl, false);
                this.private__setEvaluateAllLabel(bulkBtnEl, 'Evaluate all alerts');
            },
            private__applyAlertEnabledState(articleEl, enabled) {
                if (!articleEl) return;

                articleEl.classList.toggle('alert-card--enabled', !!enabled);
                articleEl.classList.toggle('alert-card--disabled', !enabled);

                var toggleBtn = articleEl.querySelector('[data-alert-enabled-toggle]');
                if (toggleBtn) {
                    toggleBtn.setAttribute('data-alert-enabled', enabled ? '1' : '0');
                    toggleBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                    toggleBtn.setAttribute('aria-label', enabled ? 'Disable alert' : 'Enable alert');
                    toggleBtn.setAttribute('title', enabled ? 'Disable alert' : 'Enable alert');
                }

                var badgeEl = articleEl.querySelector('[data-alert-enabled-badge]');
                if (badgeEl) {
                    badgeEl.textContent = enabled ? 'On' : 'Off';
                }

                var evaluateBtn = articleEl.querySelector('[data-alert-evaluate-button]');
                if (evaluateBtn) {
                    evaluateBtn.setAttribute('data-alert-evaluate-initial-disabled', enabled ? '0' : '1');
                    if (evaluateBtn.getAttribute('aria-busy') !== 'true') {
                        evaluateBtn.disabled = !enabled;
                    }
                }
            },
            private__handleEnabledToggleClick(btnEl) {
                var self = this;
                if (!window.horizon || !window.horizon.http) return;
                if (btnEl.disabled || self.togglingAlerts.includes(btnEl)) return;

                var url = btnEl.getAttribute('data-alert-enabled-toggle-url');
                var articleEl = btnEl.closest('[data-stream-row-id]');
                if (!url || !articleEl) return;

                self.togglingAlerts.push(btnEl);
                window.horizon.setLoading(btnEl, true);

                window.horizon.http.post(url, {}).then(function (data) {
                    var enabled = !!(data && data.enabled);
                    self.private__applyAlertEnabledState(articleEl, enabled);
                    if (!window.horizon.isHotReloadEnabled()) {
                        window.location.reload();
                    }
                }).catch(function () {
                }).finally(function () {
                    window.horizon.setLoading(btnEl, false);
                    self.togglingAlerts = self.togglingAlerts.filter(el => el !== btnEl);
                });
            },
            private__handleEvaluateAlertClick(btnEl) {
                var self = this;
                if (!window.horizon || !window.horizon.http || !btnEl) return;
                var alreadyRunning = btnEl.getAttribute && btnEl.getAttribute('data-alert-evaluation-running') === '1' || self.bulkEvaluationInProgress;
                if (alreadyRunning || btnEl.disabled || btnEl.getAttribute && btnEl.getAttribute('aria-busy') === 'true') return;
                var url = btnEl.getAttribute('data-alert-evaluate-url');
                var alertId = btnEl.getAttribute('data-alert-id');
                var alertName = btnEl.getAttribute('data-alert-name');
                var alertLabel = alertName && alertName.trim() !== '' ? '“' + alertName + '”' : '#' + alertId;
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
            private__pollEvaluationStatus(params) {
                var self = this;
                if (!params || !params.evaluationId || !params.statusUrlTemplate) return;
                var evaluationId = params.evaluationId;
                var statusUrlTemplate = params.statusUrlTemplate;
                var bulkBtnEl = params.bulkBtnEl;

                var statusUrl = statusUrlTemplate.replace('__EVALUATION_ID__', encodeURIComponent(evaluationId));
                var intervalMs = 2000;
                var startTs = Date.now();
                var maxPollMs = 180000;

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

                        if (data.status === 'expired') {
                            stop();
                            window.toast.error('Bulk evaluation expired. Start a new evaluation.');
                            return;
                        }

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
        }"
    >
        <x-resource-index-page
            eyebrow="Monitoring rules"
            title="Alert rules"
            description="Define when Horizon should notify your team about failures, blocked queues, slow jobs, or offline workers."
            :filter-action="route('horizon.alerts.index')"
            stats-target="horizon-alert-stats"
            :stats-columns="3"
            stats-view="horizon.alerts.partials.index.stats"
            body-target="tbody-horizon-alerts-list"
            body-view="horizon.alerts.partials.index.tbody"
            :body-view-data="['alerts' => $alerts]"
            :defer="$defer ?? false"
        >
            <x-slot:actions>
                @if($evaluateAllAlertsVisible ?? false)
                    <x-button
                        variant="secondary"
                        type="button"
                        class="h-9 text-sm alert-evaluate-btn"
                        data-alert-evaluate-all-button="1"
                        data-alert-evaluate-all-url="{{ route('horizon.alerts.evaluate-all') }}"
                        data-alert-evaluate-all-status-url="{{ route('horizon.alerts.evaluations.status', ['evaluationId' => '__EVALUATION_ID__']) }}"
                    >
                        <span class="inline-flex items-center gap-2">
                            <x-icons.bell class="size-4 alert-evaluate-btn-icon" />
                            <x-icons.arrow-path class="size-4 animate-spin alert-evaluate-btn-spinner hidden" />
                            <span data-alert-evaluate-all-label>Evaluate all alerts</span>
                        </span>
                    </x-button>
                @endif
                <x-form-drawer-link :href="route('horizon.alerts.create')" class="h-9 shrink-0 text-sm">
                    New alert
                </x-form-drawer-link>
            </x-slot:actions>

            <x-slot:filter>
                <div class="min-w-0 flex-1 space-y-2">
                    <x-input-label for="alerts-index-search">Search</x-input-label>
                    <x-text-input
                        id="alerts-index-search"
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Alert name"
                        class="w-full min-w-0 sm:max-w-xs"
                    />
                </div>
            </x-slot:filter>
        </x-resource-index-page>

        <x-horizon.delete-confirm-modal
            entity="Alert"
            title="Delete alert"
            resource-label="alert"
        />
    </div>
@endsection
