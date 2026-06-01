import { isHotReloadEnabled } from '../lib/sse';

/**
 * Service create/edit form.
 *
 * @param {Array<{name: string, value: string}>} initialHeaders
 * @param {string[]} initialTags
 * @param {string[]} existingTags
 * @returns {object}
 */
export function horizonServiceForm(initialHeaders, initialTags, existingTags) {
    var headers = Array.isArray(initialHeaders) ? initialHeaders : [];
    var tags = Array.isArray(initialTags) ? initialTags.slice() : [];
    var knownTags = Array.isArray(existingTags) ? existingTags.slice() : [];

    if (headers.length === 0) {
        headers.push({ name: '', value: '' });
    }

    return {
        headers: headers,
        tags: tags,
        existingTags: knownTags,
        tagInput: '',
        tagSuggestionsOpen: false,
        tagSuggestionHighlight: -1,
        tagSuggestionsLimit: 15,
        get tagSuggestions() {
            var query = (this.tagInput || '').trim().toLowerCase();
            var available = this.existingTags.filter(function (tag) {
                return this.tags.indexOf(tag) === -1;
            });

            if (query !== '') {
                available = available.filter(function (tag) {
                    return tag.indexOf(query) !== -1;
                });
            }

            return available.slice(0, this.tagSuggestionsLimit);
        },

        openTagSuggestions() {
            this.tagSuggestionsOpen = true;
            this.tagSuggestionHighlight = -1;
        },

        closeTagSuggestions() {
            this.tagSuggestionsOpen = false;
            this.tagSuggestionHighlight = -1;
        },

        highlightNextTagSuggestion() {
            if (this.tagSuggestions.length === 0) {
                return;
            }

            this.tagSuggestionsOpen = true;
            if (this.tagSuggestionHighlight < this.tagSuggestions.length - 1) {
                this.tagSuggestionHighlight += 1;
            } else {
                this.tagSuggestionHighlight = 0;
            }
        },

        highlightPreviousTagSuggestion() {
            var suggestions = this.tagSuggestions;
            if (suggestions.length === 0) {
                return;
            }

            this.tagSuggestionsOpen = true;
            if (this.tagSuggestionHighlight > 0) {
                this.tagSuggestionHighlight -= 1;
            } else {
                this.tagSuggestionHighlight = suggestions.length - 1;
            }
        },

        hasHighlightedTagSuggestion() {
            return this.tagSuggestionHighlight >= 0
                && this.tagSuggestionHighlight < this.tagSuggestions.length;
        },

        selectHighlightedTagSuggestion() {
            if (!this.hasHighlightedTagSuggestion()) {
                return;
            }

            this.selectTagSuggestion(this.tagSuggestions[this.tagSuggestionHighlight]);
        },

        selectTagSuggestion(tag) {
            this.tagInput = tag;
            this.addTag();
            this.closeTagSuggestions();
        },

        addTag() {
            var value = (this.tagInput || '').trim();
            if (value === '') {
                return;
            }

            var normalized = value.toLowerCase().replace(/\s+/g, ' ');
            if (this.tags.indexOf(normalized) !== -1) {
                this.tagInput = '';
                this.closeTagSuggestions();
                return;
            }

            this.tags.push(normalized);
            this.tagInput = '';
            this.closeTagSuggestions();
        },

        removeTag(index) {
            if (index >= 0 && index < this.tags.length) {
                this.tags.splice(index, 1);
            }
        },

        canAddHeader() {
            for (let i = 0; i < this.headers.length; i++) {
                if ((this.headers[i].name || '').trim() === '') {
                    return false;
                }
            }

            return true;
        },

        addHeader() {
            if (!this.canAddHeader()) {
                return;
            }

            this.headers.push({ name: '', value: '' });
        },

        removeHeader(index) {
            if (this.headers.length > 1) {
                this.headers.splice(index, 1);
            } else {
                this.headers[0] = { name: '', value: '' };
            }
        },
    };
}

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

            var connectivity = articleEl.getAttribute('data-service-connectivity') || 'offline';
            var hoverBorderClasses = [
                'hover:border-emerald-500/45',
                'dark:hover:border-emerald-400/50',
                'hover:border-amber-500/45',
                'dark:hover:border-amber-400/50',
                'hover:border-red-500/45',
                'dark:hover:border-red-400/50',
            ];
            articleEl.classList.remove.apply(articleEl.classList, hoverBorderClasses);
            if (!enabled || connectivity === 'stand_by') {
                articleEl.classList.add('hover:border-amber-500/45', 'dark:hover:border-amber-400/50');
            } else if (connectivity === 'online') {
                articleEl.classList.add('hover:border-emerald-500/45', 'dark:hover:border-emerald-400/50');
            } else {
                articleEl.classList.add('hover:border-red-500/45', 'dark:hover:border-red-400/50');
            }

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
                    if (connectivity === 'online') {
                        accentEl.classList.add('from-emerald-500/80', 'via-emerald-400/60');
                    } else if (connectivity === 'stand_by') {
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

            var connectivityBadgeEl = articleEl.querySelector('[data-service-connectivity-badge="1"]');
            if (connectivityBadgeEl) {
                connectivityBadgeEl.classList.remove('badge-success', 'badge-warning', 'badge-danger');
                if (!enabled) {
                    connectivityBadgeEl.classList.add('badge-warning');
                    connectivityBadgeEl.textContent = 'Disabled';
                } else if (connectivity === 'online') {
                    connectivityBadgeEl.classList.add('badge-success');
                    connectivityBadgeEl.textContent = 'Online';
                } else if (connectivity === 'stand_by') {
                    connectivityBadgeEl.classList.add('badge-warning');
                    connectivityBadgeEl.textContent = 'Stand-by';
                } else {
                    connectivityBadgeEl.classList.add('badge-danger');
                    connectivityBadgeEl.textContent = 'Offline';
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
            if (btnEl.disabled || btnEl.getAttribute('data-service-enabled-toggle-running') === '1') return;

            var url = btnEl.getAttribute('data-service-enabled-toggle-url');
            var articleEl = btnEl.closest('[data-stream-row-id]');
            if (!url || !articleEl) return;

            btnEl.setAttribute('data-service-enabled-toggle-running', '1');
            btnEl.disabled = true;

            window.horizon.http.post(url, {}).then(function (data) {
                var enabled = !!(data && data.enabled);
                self.private__applyServiceEnabledState(articleEl, enabled);
                if (!isHotReloadEnabled()) {
                    window.location.reload();
                }
            }).catch(function () {
            }).finally(function () {
                btnEl.removeAttribute('data-service-enabled-toggle-running');
                btnEl.disabled = false;
            });
        },
    };
}
