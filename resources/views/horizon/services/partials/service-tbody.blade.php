@php
    /** @var \App\Models\Service $service */
@endphp
@forelse($services as $service)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="svc-{{ (int) $service->id }}">
        <td class="px-4 py-2.5 text-sm font-medium" data-column-id="name">
            <a href="{{ route('horizon.services.show', $service) }}" class="link" data-turbo-action="replace">{{ $service->name }}</a>
        </td>
        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="base_url">
            {{ $service->getBaseUrl() ?: '–' }}
        </td>
        <td class="px-4 py-2.5" data-column-id="status">
            @if($service->status === 'online')
                <span class="badge-success">online</span>
            @elseif($service->status === 'stand_by')
                <span class="badge-warning">stand by</span>
            @else
                <span class="badge-danger">offline</span>
            @endif
        </td>
        <td class="px-4 py-2.5" data-column-id="horizon_status">
            @if(isset($service->horizon_status) && $service->horizon_status)
                @php
                    $hs = \strtolower((string) $service->horizon_status);
                    if ($hs === 'active' || $hs === 'running') {
                        $badgeClass = 'badge-success';
                    } elseif ($hs === 'inactive') {
                        $badgeClass = 'badge-warning';
                    } else {
                        $badgeClass = 'badge-muted';
                    }
                @endphp
                <span class="{{ $badgeClass }}">{{ $service->horizon_status }}</span>
            @else
                <span class="text-xs text-muted-foreground">–</span>
            @endif
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="jobs">{{ $service->horizon_jobs_count ?? 0 }}</td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="failed">{{ $service->horizon_failed_jobs_count ?? 0 }}</td>
        <td
            class="px-4 py-2.5 text-xs text-muted-foreground font-mono tabular-nums"
            data-column-id="last_seen"
        >
            {{ $service->last_seen_at?->format('Y-m-d H:i:s') ?? '-' }}
        </td>
        <td class="px-4 py-2.5" data-column-id="actions" data-stream-preserve-client>
            <div class="flex items-center gap-2">
                @php
                    $dashboardUrl = $service->getPublicUrl().'/'.\config('horizonhub.horizon_paths.dashboard');
                @endphp
                <x-button
                    variant="ghost"
                    type="button"
                    onclick="window.open('{{ $dashboardUrl }}', '_blank')"
                    class="h-8 min-h-8 p-2"
                    aria-label="Open Horizon dashboard"
                    title="Open Horizon dashboard"
                >
                    <x-heroicon-o-window class="size-4" />
                </x-button>
                <form method="POST" action="{{ route('horizon.services.test-connection', $service) }}">
                    @csrf
                    <x-button
                        variant="ghost"
                        type="submit"
                        class="h-8 min-h-8 p-2"
                        aria-label="Test connection"
                        title="Test connection"
                    >
                        <x-heroicon-o-signal class="size-4" />
                    </x-button>
                </form>
                <x-button
                    variant="ghost"
                    type="button"
                    onclick="window.location.href='{{ route('horizon.services.edit', $service) }}'"
                    class="h-8 min-h-8 p-2"
                    aria-label="Edit"
                    title="Edit"
                >
                    <x-heroicon-o-pencil-square class="size-4" />
                </x-button>
                @php
                    $serviceDeleteClick = 'openDeleteServiceModal('.\Illuminate\Support\Js::from($service->name).', '.\Illuminate\Support\Js::from(route('horizon.services.destroy', $service)).')';
                @endphp
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 p-2 text-destructive hover:text-destructive"
                    aria-label="Delete"
                    title="Delete"
                    x-on:click="{{ $serviceDeleteClick }}"
                >
                    <x-heroicon-o-trash class="size-4" />
                </x-button>
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" data-column-id="name">
            <div class="empty-state">
                <x-heroicon-o-server-stack class="empty-state-icon" />
                <p class="empty-state-title">No services</p>
                <p class="empty-state-description">Register a service above to connect your Horizon instance.</p>
            </div>
        </td>
    </tr>
@endforelse
