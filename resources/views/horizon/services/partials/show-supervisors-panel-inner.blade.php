@if(isset($supervisors) && $supervisors->isNotEmpty())
    <div class="space-y-2">
        @foreach($supervisors as $supervisor)
            @php
                $apiStatus = $supervisor->status ?? '';
                if (\strtolower($apiStatus) === 'running') {
                    $statusColor = 'bg-emerald-500';
                    $statusTitle = 'Online';
                    $statusBlink = false;
                } elseif (\strtolower($apiStatus) === 'inactive' || $apiStatus !== '') {
                    $statusColor = 'bg-amber-500';
                    $statusTitle = $apiStatus !== '' ? \ucfirst($apiStatus) : 'Unknown';
                    $statusBlink = \strtolower($apiStatus) === 'inactive';
                } else {
                    $statusColor = 'bg-slate-400';
                    $statusTitle = 'Unknown';
                    $statusBlink = false;
                }
            @endphp
            <div class="flex items-center justify-between rounded-md border border-border bg-muted/30 px-3 py-2">
                <div class="flex items-center gap-2">
                    <span class="inline-flex shrink-0 size-2.5 rounded-full {{ $statusColor }} @if($statusBlink) animate-pulse @endif" title="{{ $statusTitle }}" aria-label="{{ $statusTitle }}"></span>
                    <span class="font-mono text-sm text-foreground">{{ $supervisor->name }}</span>
                </div>
            </div>
        @endforeach
    </div>
@else
    <p class="text-sm text-muted-foreground">
        Supervisor data is not available. Run
        <code class="rounded bg-muted px-1 py-0.5 font-mono text-xs">php artisan horizon</code>
        on the service,
        ensure the service is running and reachable, and wait a few seconds for supervisor heartbeats.
    </p>
@endif
