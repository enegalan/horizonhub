@extends('layouts.app')

@section('content')
    <div
        x-data="{
            retrying: false,
            showAllExceptionLines: false,
            init() {
                this.restorePersistedUiState();
            },
            getExceptionStorageKey() {
                var rootEl = document.querySelector('[data-horizon-job-detail-root]');

                var jobUuid = String(rootEl && rootEl.getAttribute('data-horizon-job-uuid') || '').trim();

                if (!jobUuid) return null;
                return 'horizonhub:job-detail:' + jobUuid + ':exceptions:show-all';
            },
            persistUiState() {
                if (typeof window === 'undefined' || !window.localStorage) return;
                var key = this.getExceptionStorageKey();
                if (!key) return;
                try {
                    window.localStorage.setItem(key, this.showAllExceptionLines ? '1' : '0');
                } catch (_e) {
                }
            },
            restorePersistedUiState() {
                if (typeof window === 'undefined' || !window.localStorage) return;
                var key = this.getExceptionStorageKey();
                if (!key) return;
                try {
                    var raw = window.localStorage.getItem(key);
                    if (raw === null) return;
                    this.showAllExceptionLines = raw === '1' || raw === 'true';
                } catch (_e) {
                }
            },
            postSingleJobRetry(retryUrl) {
                if (!window.horizon || !window.horizon.http || this.retrying) return;
                var self = this;
                this.retrying = true;
                window.horizon.http.post(retryUrl, {}).then(function () {
                    window.toast.info('Retry requested.');
                }).catch(function () {
                }).finally(function () {
                    self.retrying = false;
                });
            },
            retry() {
                var btn = this.$el ? this.$el.querySelector('[data-job-detail-retry]') : null;
                if (!btn) {
                    return;
                }
                var retryUrl = btn.getAttribute('data-job-detail-retry-url');
                if (!retryUrl) {
                    return;
                }
                this.postSingleJobRetry(retryUrl);
            },
            toggleExceptionLines() {
                this.showAllExceptionLines = !this.showAllExceptionLines;
                this.persistUiState();
            },
        }"
        x-init="typeof init === 'function' ? init() : null"
        id="horizon-job-detail"
        data-horizon-job-detail-root="1"
        data-horizon-job-uuid="{{ ! empty($job->uuid ?? null) ? e($job->uuid) : '' }}"
    >
        @include('horizon.jobs.partials.show.body')
    </div>
@endsection
