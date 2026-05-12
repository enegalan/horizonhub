@php
    /** @var \App\Models\Service $service */
@endphp
@forelse($services as $service)
    @php
        $serviceStatus = \strtolower((string) ($service->status ?? ''));
        $isOnline = $serviceStatus === 'online';
        $isStandBy = $serviceStatus === 'stand_by';
        $horizonStatus = isset($service->horizon_status) && (string) $service->horizon_status !== ''
            ? \strtolower((string) $service->horizon_status)
            : '';
        $dashboardUrl = $service->getPublicUrl().config('horizonhub.horizon_paths.dashboard');
        $serviceDeleteClick = 'openDeleteServiceModal('.\Illuminate\Support\Js::from($service->name).', '.\Illuminate\Support\Js::from(route('horizon.services.destroy', $service)).')';
    @endphp
    <article
        class="card group relative overflow-hidden transition-colors hover:border-primary/30"
        data-stream-row-id="svc-{{ (int) $service->id }}"
    >
        <div
            @class([
                'absolute inset-x-0 top-0 h-1',
                'bg-gradient-to-r from-emerald-500/80 via-emerald-400/60 to-transparent' => $isOnline,
                'bg-gradient-to-r from-amber-500/80 via-amber-400/60 to-transparent' => $isStandBy,
                'bg-gradient-to-r from-red-500/80 via-red-400/60 to-transparent' => ! $isOnline && ! $isStandBy,
            ])
            aria-hidden="true"
        ></div>

        <div class="flex h-full flex-col p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <div
                        @class([
                            'flex size-11 shrink-0 items-center justify-center rounded-xl border',
                            'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' => $isOnline,
                            'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300' => $isStandBy,
                            'border-red-500/20 bg-red-500/10 text-red-700 dark:text-red-300' => ! $isOnline && ! $isStandBy,
                        ])
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
                @if($isOnline)
                    <span class="badge-success shrink-0">Online</span>
                @elseif($isStandBy)
                    <span class="badge-warning shrink-0">Stand-by</span>
                @else
                    <span class="badge-danger shrink-0">Offline</span>
                @endif
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
                    x-on:click="{{ $serviceDeleteClick }}"
                >
                    <x-heroicon-o-trash class="size-4" />
                    <span>Delete</span>
                </x-button>
            </div>
        </div>
    </article>
@empty
    <div class="card p-8 sm:col-span-2 xl:col-span-3">
        <div class="empty-state">
            <x-heroicon-o-server-stack class="empty-state-icon" />
            <p class="empty-state-title">No services</p>
            <p class="empty-state-description">Register a service above to connect your first Horizon instance.</p>
        </div>
    </div>
@endforelse
