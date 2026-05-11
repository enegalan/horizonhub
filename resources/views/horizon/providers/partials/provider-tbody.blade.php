@php
    /** @var \App\Models\NotificationProvider $provider */
@endphp
@forelse($providers as $provider)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="prv-{{ (int) $provider->id }}">
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
        <td class="px-4 py-2.5" data-column-id="actions" data-stream-preserve-client>
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
                <x-heroicon-o-megaphone class="empty-state-icon" />
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
