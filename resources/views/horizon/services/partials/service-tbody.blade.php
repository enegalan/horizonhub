@php
    /** @var \App\Models\Service $service */
@endphp
@forelse($services as $service)
    @php
        $serviceStatus = \strtolower((string) ($service->status ?? ''));
        $isOnline = $serviceStatus === 'online';
        $isStandBy = $serviceStatus === 'stand_by';
        $isEnabled = (bool) ($service->enabled ?? true);
        $horizonStatus = isset($service->horizon_status) && (string) $service->horizon_status !== ''
            ? \strtolower((string) $service->horizon_status)
            : '';
        $dashboardUrl = $service->getPublicUrl().config('horizonhub.horizon_paths.dashboard');
    @endphp
    <article
        @class([
            'card group relative overflow-hidden transition-colors',
            'opacity-60' => ! $isEnabled,
            'hover:border-emerald-500/45 dark:hover:border-emerald-400/50' => $isEnabled && $isOnline,
            'hover:border-amber-500/45 dark:hover:border-amber-400/50' => ($isEnabled && $isStandBy) || ! $isEnabled,
            'hover:border-red-500/45 dark:hover:border-red-400/50' => $isEnabled && ! $isOnline && ! $isStandBy,
        ])
        data-stream-row-id="svc-{{ (int) $service->id }}"
        data-service-connectivity="{{ $serviceStatus }}"
    >
        <div
            @class([
                'absolute inset-x-0 top-0 h-1 bg-gradient-to-r to-transparent',
                'from-emerald-500/80 via-emerald-400/60' => $isEnabled && $isOnline,
                'from-amber-500/80 via-amber-400/60' => ($isEnabled && $isStandBy) || ! $isEnabled,
                'from-red-500/80 via-red-400/60' => $isEnabled && ! $isOnline && ! $isStandBy,
            ])
            data-service-enabled-accent="1"
            aria-hidden="true"
        ></div>

        <div class="flex h-full flex-col p-4">
            <div class="flex items-start justify-between gap-3" data-stream-preserve-client>
                <div class="flex min-w-0 items-start gap-3">
                    <div
                        @class([
                            'flex size-11 shrink-0 items-center justify-center rounded-xl border',
                            'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' => $isEnabled && $isOnline,
                            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300' => ($isEnabled && $isStandBy) || ! $isEnabled,
                            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-300' => $isEnabled && ! $isOnline && ! $isStandBy,
                        ])
                        data-service-enabled-icon="1"
                    >
                        <x-heroicon-o-server-stack class="size-5" />
                    </div>
                    <div class="min-w-0">
                        <a href="{{ route('horizon.services.show', $service) }}" class="link truncate text-sm font-semibold text-foreground" data-turbo-action="replace">
                            {{ $service->name }}
                        </a>
                        <p class="mt-1 truncate font-mono text-xs text-muted-foreground">{{ $service->getBaseUrl() ?: 'No base URL' }}</p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1.5">
                    <button
                        type="button"
                        class="service-enabled-toggle flex rounded-md transition-opacity hover:opacity-80 disabled:cursor-wait disabled:opacity-60"
                        data-service-enabled-toggle="1"
                        data-service-id="{{ (int) $service->id }}"
                        data-service-enabled="{{ $isEnabled ? '1' : '0' }}"
                        data-service-enabled-toggle-url="{{ route('horizon.services.toggle-enabled', $service) }}"
                        aria-pressed="{{ $isEnabled ? 'true' : 'false' }}"
                        aria-label="{{ $isEnabled ? 'Disable service' : 'Enable service' }}"
                        title="{{ $isEnabled ? 'Disable service' : 'Enable service' }}"
                    >
                        <span
                            class="{{ $isEnabled ? 'badge-success' : 'badge-danger' }}"
                            data-service-enabled-badge="1"
                        >
                            {{ $isEnabled ? 'On' : 'Off' }}
                        </span>
                    </button>
                    @if($isEnabled)
                        @if($isOnline)
                            <span class="badge-success shrink-0 text-[10px]">Online</span>
                        @elseif($isStandBy)
                            <span class="badge-warning shrink-0 text-[10px]">Stand-by</span>
                        @else
                            <span class="badge-danger shrink-0 text-[10px]">Offline</span>
                        @endif
                    @else
                        <span class="badge-warning shrink-0 text-[10px]">Disabled</span>
                    @endif
                </div>
            </div>

            <div class="mt-4 space-y-3 rounded-lg border border-border/70 bg-muted/20 px-3 py-2.5">
                <div class="grid gap-2 sm:grid-cols-2">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Horizon</p>
                        <p class="mt-1 text-xs text-foreground/90">
                            @if($horizonStatus !== '')
                                {{ $service->horizon_status }}
                            @else
                                Offline
                            @endif
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Last seen</p>
                        <p class="mt-1 font-mono text-xs text-foreground/90">
                            {{ $service->last_seen_at?->format('Y-m-d H:i:s') ?? 'Never' }}
                        </p>
                    </div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Jobs</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-foreground">{{ $service->horizon_jobs_count ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Failed</p>
                        <p class="mt-1 text-sm font-semibold tabular-nums text-foreground">{{ $service->horizon_failed_jobs_count ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-end gap-2" data-stream-preserve-client>
                <x-button
                    variant="ghost"
                    type="button"
                    onclick="window.open('{{ $dashboardUrl }}', '_blank')"
                    class="h-8 min-h-8 px-2.5 text-xs"
                    aria-label="Open Horizon dashboard"
                    title="Open Horizon dashboard"
                >
                    <x-heroicon-o-window class="size-4" />
                    <span>Dashboard</span>
                </x-button>
                <form method="POST" action="{{ route('horizon.services.test-connection', $service) }}">
                    @csrf
                    <x-button
                        variant="ghost"
                        type="submit"
                        class="h-8 min-h-8 px-2.5 text-xs"
                        aria-label="Test connection"
                        title="Test connection"
                    >
                        <x-heroicon-o-signal class="size-4" />
                        <span>Test</span>
                    </x-button>
                </form>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 px-2.5 text-xs"
                    aria-label="Edit"
                    title="Edit"
                    onclick="window.location.href='{{ route('horizon.services.edit', $service) }}'"
                >
                    <x-heroicon-o-pencil-square class="size-4" />
                    <span>Edit</span>
                </x-button>
                <x-button
                    variant="ghost"
                    type="button"
                    class="h-8 min-h-8 px-2.5 text-xs text-destructive hover:text-destructive"
                    aria-label="Delete"
                    title="Delete"
                    x-on:click="openDeleteServiceModal({{ \Illuminate\Support\Js::from($service->name) }}, {{ \Illuminate\Support\Js::from(route('horizon.services.destroy', $service)) }})"
                >
                    <x-heroicon-o-trash class="size-4" />
                    <span>Delete</span>
                </x-button>
            </div>
        </div>
    </article>
@empty
    <div class="card p-8 sm:col-span-2 xl:col-span-3">
        <x-empty-state
            title="No services"
            description="Register a service above to connect your first Horizon instance."
        >
            <x-slot name="icon">
                <x-heroicon-o-server-stack class="empty-state-icon" />
            </x-slot>
        </x-empty-state>
    </div>
@endforelse
