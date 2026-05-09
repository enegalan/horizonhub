@extends('layouts.app')

@section('content')
    <div class="max-w-3xl space-y-6"
        x-data="{
            showDeleteProviderModal: false,
            deleteProviderName: '',
            deleteProviderAction: '',
            openDeleteProviderModal(name, action) {
                this.deleteProviderName = name;
                this.deleteProviderAction = action;
                this.showDeleteProviderModal = true;
            },
            closeDeleteProviderModal() {
                this.showDeleteProviderModal = false;
            },
            confirmDeleteProvider() {
                this.$refs.deleteProviderForm.requestSubmit();
                this.closeDeleteProviderModal();
            }
        }"
    >
        <div class="card">
            <div class="px-4 py-3 flex items-center justify-between">
                <h2 class="text-section-title text-foreground">Notification providers</h2>
                <x-button
                    type="button"
                    class="h-9 text-sm"
                    onclick="window.location.href='{{ route('horizon.providers.create') }}'"
                >
                    New provider
                </x-button>
            </div>
            @if(session('status'))
                <div class="px-4 pb-3 text-xs text-muted-foreground">
                    {{ session('status') }}
                </div>
            @endif
        </div>

        <div class="card">
            <x-table
                resizable-key="horizon-settings-providers"
                column-ids="name,type,config,actions"
            >
                <x-slot:head>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5" data-column-id="name">Name</th>
                        <th class="table-header px-4 py-2.5" data-column-id="type">Type</th>
                        <th class="table-header px-4 py-2.5" data-column-id="config">Config</th>
                        <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                    </tr>
                </x-slot:head>
                        @forelse($providers as $provider)
                            <tr class="transition-colors hover:bg-muted/30">
                                <td class="px-4 py-2.5 text-sm font-medium" data-column-id="name">{{ $provider->name }}</td>
                                <td class="px-4 py-2.5" data-column-id="type">
                                    @if($provider->type === 'slack')
                                        <span class="badge">Slack</span>
                                    @else
                                        <span class="badge">Email</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-sm text-muted-foreground font-mono max-w-xs truncate" data-column-id="config">
                                    @if($provider->type === 'slack')
                                        {{ $provider->getWebhookUrl() ?: '–' }}
                                    @else
                                        {{ implode(', ', $provider->getToEmails()) ?: '–' }}
                                    @endif
                                </td>
                                <td class="px-4 py-2.5" data-column-id="actions">
                                    <div class="flex items-center gap-2">
                                        <x-button
                                            variant="ghost"
                                            type="button"
                                            class="h-8 min-h-8 p-2"
                                            aria-label="Edit"
                                            title="Edit"
                                            onclick="window.location.href='{{ route('horizon.providers.edit', $provider) }}'"
                                        >
                                            <x-heroicon-o-pencil-square class="size-4" />
                                        </x-button>
                                        @php
                                            $providerDeleteClick = 'openDeleteProviderModal('.\Illuminate\Support\Js::from($provider->name).', '.\Illuminate\Support\Js::from(route('horizon.providers.destroy', $provider)).')';
                                        @endphp
                                        <x-button
                                            variant="ghost"
                                            type="button"
                                            class="h-8 min-h-8 p-2 text-destructive hover:text-destructive"
                                            aria-label="Delete"
                                            title="Delete"
                                            x-on:click="{{ $providerDeleteClick }}"
                                        >
                                            <x-heroicon-o-trash class="size-4" />
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" data-column-id="name">
                                    <div class="empty-state">
                                        <x-heroicon-o-bell class="empty-state-icon" />
                                        <p class="empty-state-title">No providers</p>
                                        <p class="empty-state-description">Create Slack or Email providers, then select them when creating alerts.</p>
                                        <x-button
                                            type="button"
                                            class="mt-3 h-9 text-sm"
                                            onclick="window.location.href='{{ route('horizon.providers.create') }}'"
                                        >
                                            New provider
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
            </x-table>
        </div>

        @include('horizon.settings.partials.delete-provider-confirm-modal')
    </div>
@endsection
