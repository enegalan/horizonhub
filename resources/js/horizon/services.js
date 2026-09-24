import { isHotReloadEnabled } from '../lib/sse';
import { setLoading } from '../components/loading-button';

/**
 * Service create/edit form.
 *
 * @param {Array<{name: string, value: string}>} initialHeaders
 * @param {string[]} initialTags
 * @param {string[]} existingTags
 * @param {string} initialTlsClientMode
 * @param {{certName: string|null, keyName: string|null}} initialTlsFiles
 * @returns {object}
 */
export function horizonServiceForm(initialHeaders, initialTags, existingTags, initialTlsClientMode, initialTlsFiles) {
    // Normalize tag value to lowercase and remove whitespace
    function normalizeTag(value) {
        return (value || '').trim().toLowerCase().replace(/\s+/g, ' ');
    }

    var headers = Array.isArray(initialHeaders) ? initialHeaders : [];
    var tags = Array.isArray(initialTags) ? initialTags.map(normalizeTag).filter(Boolean) : [];
    var knownTags = Array.isArray(existingTags) ? existingTags.map(normalizeTag).filter(Boolean) : [];
    var tlsClientMode = initialTlsClientMode || '';
    var tlsFiles = initialTlsFiles && typeof initialTlsFiles === 'object' ? initialTlsFiles : {};
    var tlsCertName = tlsFiles.certName || '';
    var tlsKeyName = tlsFiles.keyName || '';

    if (headers.length === 0) {
        headers.push({ name: '', value: '' });
    }

    return {
        headers: headers,
        tags: tags,
        existingTags: knownTags,
        tlsClientMode: tlsClientMode,
        tlsCertName: tlsCertName,
        tlsKeyName: tlsKeyName,
        tlsCertOnFile: tlsCertName !== '',
        tlsKeyOnFile: tlsKeyName !== '',
        tlsRemoveCert: false,
        tlsRemoveKey: false,
        showTlsPassphrase: false,
        tagInput: '',
        tagSuggestionsOpen: false,
        tagSuggestionHighlight: -1,
        tagSuggestionsLimit: 15,
        get tagSuggestions() {
            var query = normalizeTag(this.tagInput);
            var available = this.existingTags.filter((tag) => {
                return this.tags.indexOf(tag) === -1;
            });

            if (query !== '') {
                available = available.filter(function (tag) {
                    return tag.indexOf(query) !== -1;
                });
            }

            return available.slice(0, this.tagSuggestionsLimit);
        },

        removeTlsCert() {
            this.tlsCertOnFile = false;
            this.tlsRemoveCert = true;
        },

        removeTlsKey() {
            this.tlsKeyOnFile = false;
            this.tlsRemoveKey = true;
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

        canAddTag() {
            var normalized = normalizeTag(this.tagInput);
            if (normalized === '') {
                return false;
            }

            return this.tags.indexOf(normalized) === -1;
        },

        addTag() {
            var normalized = normalizeTag(this.tagInput);
            if (normalized === '') {
                return;
            }
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
        togglingServices: [],
        init() {
            var self = this;

            window.__horizonServicesListToggleInstance = self;
            // Attach the document click listener only once to avoid duplicate toasts.
            if (!window.__horizonServicesListToggleClickListenerAttached) {
                window.__horizonServicesListToggleClickListenerAttached = true;
                window.__horizonServicesListToggleClickListener = function (e) {
                    var instance = window.__horizonServicesListToggleInstance;
                    if (!instance || !e.target || !e.target.closest) return;

                    var enabledToggleBtn = e.target.closest('[data-service-enabled-toggle="1"]');
                    if (enabledToggleBtn) {
                        e.preventDefault();
                        instance.private__handleEnabledToggleClick(enabledToggleBtn);
                    }
                };
                document.addEventListener('click', window.__horizonServicesListToggleClickListener);
            }
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
            articleEl.classList.toggle('service-card--enabled', !!enabled);
            articleEl.classList.toggle('service-card--disabled', !enabled);

            var toggleBtn = articleEl.querySelector('[data-service-enabled-toggle="1"]');
            if (toggleBtn) {
                toggleBtn.setAttribute('data-service-enabled', enabled ? '1' : '0');
                toggleBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                toggleBtn.setAttribute('aria-label', enabled ? 'Disable service' : 'Enable service');
                toggleBtn.setAttribute('title', enabled ? 'Disable service' : 'Enable service');
            }

            var badgeEl = articleEl.querySelector('[data-service-enabled-badge="1"]');
            if (badgeEl) {
                badgeEl.textContent = enabled ? 'On' : 'Off';
            }

            var connectivityBadgeEl = articleEl.querySelector('[data-service-connectivity-badge="1"]');
            if (connectivityBadgeEl) {
                if (!enabled) {
                    connectivityBadgeEl.textContent = 'Disabled';
                } else if (connectivity === 'online') {
                    connectivityBadgeEl.textContent = 'Online';
                } else if (connectivity === 'stand_by') {
                    connectivityBadgeEl.textContent = 'Stand-by';
                } else {
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
            if (!window.horizon || !window.horizon.http) return;
            if (btnEl.disabled || self.togglingServices.includes(btnEl)) return;

            var url = btnEl.getAttribute('data-service-enabled-toggle-url');
            var articleEl = btnEl.closest('[data-stream-row-id]');
            if (!url || !articleEl) return;

            self.togglingServices.push(btnEl);
            setLoading(btnEl, true);

            window.horizon.http.post(url, {}).then(function (data) {
                var enabled = !!(data && data.enabled);
                self.private__applyServiceEnabledState(articleEl, enabled);
                if (!isHotReloadEnabled()) {
                    window.location.reload();
                }
            }).catch(function () {
            }).finally(function () {
                self.togglingServices = self.togglingServices.filter(el => el !== btnEl);
                setLoading(btnEl, false);
            });
        },
    };
}
