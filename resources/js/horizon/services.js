/**
 * Services index interactions (enable/disable toggle).
 */
export function horizonServicesList() {
    return {
        init() {
            var self = this;

            if (!window.__horizonServicesListToggleClickListenerAttached) {
                window.__horizonServicesListToggleClickListenerAttached = true;
                window.__horizonServicesListToggleClickListener = function (e) {
                    var instance = window.__horizonServicesListToggleInstance;
                    if (!instance || typeof instance.private__handleEnabledToggleClick !== 'function') {
                        return;
                    }

                    var enabledToggleBtn = e.target && e.target.closest
                        ? e.target.closest('[data-service-enabled-toggle="1"]')
                        : null;
                    if (enabledToggleBtn) {
                        e.preventDefault();
                        instance.private__handleEnabledToggleClick(enabledToggleBtn);
                    }
                };
                document.addEventListener('click', window.__horizonServicesListToggleClickListener);
            }

            window.__horizonServicesListToggleInstance = self;
        },

        /**
         * Apply enabled state to a service card.
         * @param {HTMLElement} articleEl
         * @param {boolean} enabled
         * @returns {void}
         */
        private__applyServiceEnabledState(articleEl, enabled) {
            if (!articleEl) return;

            var accentEl = articleEl.querySelector('[data-service-enabled-accent="1"]');
            if (accentEl) {
                accentEl.classList.remove(
                    'from-emerald-500/80',
                    'via-emerald-400/60',
                    'from-amber-500/80',
                    'via-amber-400/60',
                    'from-red-500/80',
                    'via-red-400/60'
                );
                if (enabled) {
                    var status = articleEl.getAttribute('data-service-connectivity') || 'offline';
                    if (status === 'online') {
                        accentEl.classList.add('from-emerald-500/80', 'via-emerald-400/60');
                    } else if (status === 'stand_by') {
                        accentEl.classList.add('from-amber-500/80', 'via-amber-400/60');
                    } else {
                        accentEl.classList.add('from-red-500/80', 'via-red-400/60');
                    }
                } else {
                    accentEl.classList.add('from-amber-500/80', 'via-amber-400/60');
                }
            }

            var iconEl = articleEl.querySelector('[data-service-enabled-icon="1"]');
            if (iconEl) {
                iconEl.classList.remove(
                    'border-emerald-500/20',
                    'bg-emerald-500/10',
                    'text-emerald-700',
                    'dark:text-emerald-300',
                    'border-amber-500/20',
                    'bg-amber-500/10',
                    'text-amber-700',
                    'dark:text-amber-300',
                    'border-red-500/20',
                    'bg-red-500/10',
                    'text-red-700',
                    'dark:text-red-300'
                );
                if (!enabled) {
                    iconEl.classList.add(
                        'border-amber-500/20',
                        'bg-amber-500/10',
                        'text-amber-700',
                        'dark:text-amber-300'
                    );
                } else {
                    var connectivity = articleEl.getAttribute('data-service-connectivity') || 'offline';
                    if (connectivity === 'online') {
                        iconEl.classList.add(
                            'border-emerald-500/20',
                            'bg-emerald-500/10',
                            'text-emerald-700',
                            'dark:text-emerald-300'
                        );
                    } else if (connectivity === 'stand_by') {
                        iconEl.classList.add(
                            'border-amber-500/20',
                            'bg-amber-500/10',
                            'text-amber-700',
                            'dark:text-amber-300'
                        );
                    } else {
                        iconEl.classList.add(
                            'border-red-500/20',
                            'bg-red-500/10',
                            'text-red-700',
                            'dark:text-red-300'
                        );
                    }
                }
            }

            articleEl.classList.toggle('opacity-60', !enabled);

            var toggleBtn = articleEl.querySelector('[data-service-enabled-toggle="1"]');
            if (toggleBtn) {
                toggleBtn.setAttribute('data-service-enabled', enabled ? '1' : '0');
                toggleBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                toggleBtn.setAttribute('aria-label', enabled ? 'Disable service' : 'Enable service');
                toggleBtn.setAttribute('title', enabled ? 'Disable service' : 'Enable service');
            }

            var badgeEl = articleEl.querySelector('[data-service-enabled-badge="1"]');
            if (badgeEl) {
                badgeEl.classList.remove('badge-success', 'badge-danger');
                badgeEl.classList.add(enabled ? 'badge-success' : 'badge-danger');
                badgeEl.textContent = enabled ? 'On' : 'Off';
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
            if (btnEl.disabled || btnEl.getAttribute('data-service-enabled-toggle-running') === '1') return;

            var url = btnEl.getAttribute('data-service-enabled-toggle-url');
            var articleEl = btnEl.closest('[data-stream-row-id]');
            if (!url || !articleEl) return;

            btnEl.setAttribute('data-service-enabled-toggle-running', '1');
            btnEl.disabled = true;

            window.horizon.http.post(url, {}).then(function (data) {
                var enabled = !!(data && data.enabled);
                self.private__applyServiceEnabledState(articleEl, enabled);
            }).catch(function () {
            }).finally(function () {
                btnEl.removeAttribute('data-service-enabled-toggle-running');
                btnEl.disabled = false;
            });
        },
    };
}
