@extends('layouts.app')

@section('content')
    <div
        x-data="window.horizonServiceList ? window.horizonServiceList() : {}"
        x-init="typeof init === 'function' ? init() : null"
    >
        <div class="card mb-4">
            <div class="px-4 py-3">
                <h2 class="text-section-title text-foreground mb-3">Register service</h2>
                @if(session('status'))
                    <p class="mb-2 text-xs text-muted-foreground">{{ session('status') }}</p>
                @endif
                <form method="POST" action="{{ route('horizon.services.store') }}" class="space-y-3 max-w-sm">
                    @csrf
                    <div class="space-y-2">
                        <x-input-label>Name</x-input-label>
                        <x-text-input type="text" name="name" value="{{ old('name') }}" placeholder="my-service" class="w-full" />
                        @error('name') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                    </div>
                    <div class="space-y-2">
                        <x-input-label>Base URL</x-input-label>
                        <x-text-input type="url" name="base_url" value="{{ old('base_url') }}" placeholder="http://my-service" class="w-full" />
                        @error('base_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            Internal URL used to obtain events from the service.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <x-input-label>Public URL (optional)</x-input-label>
                        <x-text-input type="url" name="public_url" value="{{ old('public_url') }}" placeholder="http://my-service:8080" class="w-full" />
                        @error('public_url') <span class="text-xs text-destructive">{{ $message }}</span> @enderror
                        <p class="text-xs text-muted-foreground">
                            URL reachable from your browser.
                        </p>
                    </div>
                    <x-button type="submit" class="h-9 text-sm relative inline-flex items-center justify-center">
                        Register
                    </x-button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="overflow-x-auto">
                <table class="min-w-full" data-resizable-table="horizon-service-list" data-column-ids="name,base_url,status,jobs,failed,last_seen,actions">
                    <thead>
                        <tr class="border-b border-border bg-muted/50">
                            <th class="table-header px-4 py-2.5" data-column-id="name">Name</th>
                            <th class="table-header px-4 py-2.5" data-column-id="base_url">Base URL</th>
                            <th class="table-header px-4 py-2.5" data-column-id="status">Status</th>
                            <th class="table-header px-4 py-2.5" data-column-id="jobs">Jobs</th>
                            <th class="table-header px-4 py-2.5" data-column-id="failed">Failed</th>
                            <th class="table-header px-4 py-2.5" data-column-id="last_seen">Last seen</th>
                            <th class="table-header px-4 py-2.5 w-24" data-column-id="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($services as $service)
                            <tr class="transition-colors hover:bg-muted/30">
                                <td class="px-4 py-2.5 text-sm font-medium" data-column-id="name">
                                    <a href="{{ route('horizon.services.show', $service) }}" class="link">{{ $service->name }}</a>
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="base_url">
                                    {{ $service->base_url ?? '–' }}
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
                                <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="jobs">{{ $service->horizon_jobs_count ?? 0 }}</td>
                                <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="failed">{{ $service->horizon_failed_jobs_count ?? 0 }}</td>
                                <td
                                    class="px-4 py-2.5 text-xs text-muted-foreground"
                                    data-column-id="last_seen"
                                    data-datetime="{{ $service->last_seen_at?->toIso8601String() }}"
                                >
                                    {{ $service->last_seen_at ? '…' : '–' }}
                                </td>
                                <td class="px-4 py-2.5" data-column-id="actions">
                                    <div class="flex items-center gap-2">
                                        @php
                                            $dashboardBase = $service->public_url ?: $service->base_url;
                                        @endphp
                                        @if($dashboardBase)
                                            <x-button
                                                variant="ghost"
                                                type="button"
                                                onclick="window.open('{{ rtrim($dashboardBase, '/') . \config('horizonhub.horizon_paths.dashboard') }}', '_blank')"
                                                class="h-8 min-h-8 p-2"
                                                aria-label="Open Horizon dashboard"
                                                title="Open Horizon dashboard"
                                            >
                                                <x-heroicon-o-window class="size-4" />
                                            </x-button>
                                        @endif
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
                                        <form method="POST" action="{{ route('horizon.services.destroy', $service) }}" onsubmit="return confirm('Delete service {{ $service->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-button
                                                variant="ghost"
                                                type="submit"
                                                class="h-8 min-h-8 p-2 text-destructive hover:text-destructive"
                                                aria-label="Delete"
                                                title="Delete"
                                            >
                                                <x-heroicon-o-trash class="size-4" />
                                            </x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" data-column-id="name">
                                    <div class="empty-state">
                                        <x-heroicon-o-server-stack class="empty-state-icon" />
                                        <p class="empty-state-title">No services</p>
                                        <p class="empty-state-description">Register a service above to connect your Horizon instance.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
