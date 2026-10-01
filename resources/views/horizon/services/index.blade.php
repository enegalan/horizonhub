@extends('layouts.app')

@section('content')
    <div
        class="space-y-6"
        x-data="{
            showDeleteServiceModal: false,
            deleteServiceName: '',
            deleteServiceAction: '',
            openDeleteServiceModal(name, action) {
                this.deleteServiceName = name;
                this.deleteServiceAction = action;
                this.showDeleteServiceModal = true;
            },
            closeDeleteServiceModal() {
                this.showDeleteServiceModal = false;
            },
            confirmDeleteService() {
                this.$refs.deleteServiceForm.requestSubmit();
                this.closeDeleteServiceModal();
            },
            togglingServices: [],
            init() {
                var self = this;

                window.horizon.registerDocumentDelegate('services-list', self, function (e, instance) {
                    var enabledToggleBtn = e.target.closest('[data-service-enabled-toggle]');
                    if (enabledToggleBtn) {
                        e.preventDefault();
                        instance.private__handleEnabledToggleClick(enabledToggleBtn);
                    }
                });
            },
            private__applyServiceEnabledState(articleEl, enabled) {
                if (!articleEl) return;

                var connectivity = articleEl.getAttribute('data-service-connectivity') || 'offline';
                articleEl.classList.toggle('service-card--enabled', !!enabled);
                articleEl.classList.toggle('service-card--disabled', !enabled);

                var toggleBtn = articleEl.querySelector('[data-service-enabled-toggle]');
                if (toggleBtn) {
                    toggleBtn.setAttribute('data-service-enabled', enabled ? '1' : '0');
                    toggleBtn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
                    toggleBtn.setAttribute('aria-label', enabled ? 'Disable service' : 'Enable service');
                    toggleBtn.setAttribute('title', enabled ? 'Disable service' : 'Enable service');
                }

                var badgeEl = articleEl.querySelector('[data-service-enabled-badge]');
                if (badgeEl) {
                    badgeEl.textContent = enabled ? 'On' : 'Off';
                }

                var connectivityBadgeEl = articleEl.querySelector('[data-service-connectivity-badge]');
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
            private__handleEnabledToggleClick(btnEl) {
                var self = this;
                if (!window.horizon || !window.horizon.http) return;
                if (btnEl.disabled || self.togglingServices.includes(btnEl)) return;

                var url = btnEl.getAttribute('data-service-enabled-toggle-url');
                var articleEl = btnEl.closest('[data-stream-row-id]');
                if (!url || !articleEl) return;

                self.togglingServices.push(btnEl);
                window.horizon.setLoading(btnEl, true);

                window.horizon.http.post(url, {}).then(function (data) {
                    var enabled = !!(data && data.enabled);
                    self.private__applyServiceEnabledState(articleEl, enabled);
                    if (!window.horizon.isHotReloadEnabled()) {
                        window.location.reload();
                    }
                }).catch(function () {
                }).finally(function () {
                    self.togglingServices = self.togglingServices.filter(el => el !== btnEl);
                    window.horizon.setLoading(btnEl, false);
                });
            },
        }"
    >
        <x-resource-index-page
            eyebrow="Connected Horizon instances"
            title="Services"
            description="Register each Horizon deployment, monitor its health, and open its dashboard when you need to inspect queues and workers."
            :filter-action="route('horizon.services.index')"
            filter-form-attributes='data-service-tag-filter="1" data-service-tag-filter-manual="1"'
            stats-target="horizon-service-stats"
            stats-view="horizon.services.partials.index.stats"
            body-target="tbody-horizon-service-list"
            body-view="horizon.services.partials.index.tbody"
            :body-view-data="['services' => $services]"
            :defer="$defer ?? false"
        >
            <x-slot:actions>
                <x-form-drawer-link :href="route('horizon.services.create')" class="h-9 shrink-0 text-sm">
                    Register service
                </x-form-drawer-link>
            </x-slot:actions>

            <x-slot:filter>
                <x-service-tag-filter
                    :all-tags="$allTags ?? []"
                    :selected-tags="$selectedTags ?? []"
                />
            </x-slot:filter>
        </x-resource-index-page>

        <x-horizon.delete-confirm-modal
            entity="Service"
            title="Delete service"
            resource-label="service"
        />
    </div>
@endsection
