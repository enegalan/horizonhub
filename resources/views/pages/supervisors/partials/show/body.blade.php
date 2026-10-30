@php
    /** @var \App\Models\Service $service */
    /** @var array<string, mixed>|null $master */
    /** @var array<string, mixed>|null $supervisor */
    /** @var string $supervisorName */
    /** @var string $dashboardUrl */
    $formatValue = static function (mixed $value, string $suffix = ''): string {
        if ($value === null || $value === '') {
            return '—';
        }

        if (\is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (\is_array($value)) {
            return \json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—';
        }

        return (string) $value . $suffix;
    };
@endphp

@if(! $service->enabled)
    @include('pages.partials.service-disabled-banner', [
        'message' => 'This service is disabled. Enable it to read supervisor details from its Horizon API.',
    ])
@elseif($supervisor === null || $master === null)
    <x-empty-state
        title="Supervisor not found"
        :description="'Horizon did not report a supervisor named ' . $supervisorName . ' for this service. It may have been removed or renamed since the page was opened.'"
    >
        <div class="mt-4 flex flex-wrap justify-center gap-3">
            <a href="{{ route('horizon.services.show', $service) }}" class="link text-sm" data-turbo-action="replace">Back to service</a>
            <a href="{{ route('horizon.instances.index') }}" class="link text-sm" data-turbo-action="replace">All instances</a>
        </div>
    </x-empty-state>
@else
    @php
        $status = (string) ($supervisor['status'] ?? '');
        $masterStatus = (string) ($master['status'] ?? '');
        $environment = (string) ($master['environment'] ?? '');
        $masterPid = (string) ($master['pid'] ?? '');
        $masterName = (string) ($master['name'] ?? '');
        $queueNames = $supervisor['queueNames'] ?? [];
        $processesByQueue = $supervisor['processesByQueue'] ?? [];
        $warnings = $supervisor['warnings'] ?? [];
        $options = $supervisor['options'] ?? [];
        $backoff = $supervisor['backoff'] ?? null;
    @endphp

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <x-horizon-status :status="$status" />
            <span class="text-sm text-muted-foreground">Master</span>
            <span class="font-mono text-sm text-foreground break-all">{{ $masterName !== '' ? $masterName : '—' }}</span>
            @if($masterStatus !== '')
                <x-horizon-status :status="$masterStatus" />
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <a href="{{ route('horizon.services.show', $service) }}" class="link" data-turbo-action="replace">Service dashboard</a>
            <a href="{{ route('horizon.instances.index') }}" class="link" data-turbo-action="replace">All instances</a>
            <a href="{{ $dashboardUrl }}" class="link" target="_blank" rel="noopener noreferrer">Open Horizon dashboard</a>
        </div>
    </div>

    @if(! empty($warnings))
        <div class="mb-5 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3">
            <p class="text-sm font-medium text-amber-900 dark:text-amber-200">Attention</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5 text-sm text-amber-800 dark:text-amber-300">
                @foreach($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Processes" tone="emerald">
            @if(($supervisor['processes'] ?? null) !== null)
                <span>{{ number_format((int) $supervisor['processes']) }}</span>
            @else
                <span>—</span>
            @endif
        </x-stat-card>
        <x-stat-card label="Scale" tone="sky">
            <span>
                @if(($supervisor['minProcesses'] ?? null) !== null || ($supervisor['maxProcesses'] ?? null) !== null)
                    {{ $supervisor['minProcesses'] ?? '—' }}–{{ $supervisor['maxProcesses'] ?? '—' }}
                @else
                    —
                @endif
            </span>
        </x-stat-card>
        <x-stat-card label="Balance" tone="violet" :value="($supervisor['balance'] ?? '') !== '' ? $supervisor['balance'] : '—'" />
        <x-stat-card label="Connection" tone="neutral" :value="($supervisor['connection'] ?? '') !== '' ? $supervisor['connection'] : '—'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="border-b border-border bg-muted/20 px-4 py-3">
                <h3 class="text-section-title text-foreground">Master</h3>
            </div>
            <dl class="divide-y divide-border text-sm">
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Name</dt>
                    <dd class="font-mono text-foreground break-all">{{ $masterName !== '' ? $masterName : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Environment</dt>
                    <dd class="text-foreground">{{ $environment !== '' ? $environment : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">PID</dt>
                    <dd class="font-mono text-foreground">{{ $masterPid !== '' ? $masterPid : '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                    <dt class="text-muted-foreground">Status</dt>
                    <dd><x-horizon-status :status="$masterStatus" /></dd>
                </div>
            </dl>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-border bg-muted/20 px-4 py-3">
                <h3 class="text-section-title text-foreground">Runtime configuration</h3>
            </div>
            <dl class="divide-y divide-border text-sm">
                @foreach([
                    'Memory' => ($supervisor['memory'] ?? null) !== null ? number_format((int) $supervisor['memory']) . ' MB' : '—',
                    'Timeout' => ($supervisor['timeout'] ?? null) !== null ? number_format((int) $supervisor['timeout']) . ' s' : '—',
                    'Max tries' => ($supervisor['maxTries'] ?? null) !== null ? (string) $supervisor['maxTries'] : '—',
                    'Max time' => ($supervisor['maxTime'] ?? null) !== null ? number_format((int) $supervisor['maxTime']) . ' s' : '—',
                    'Max jobs' => ($supervisor['maxJobs'] ?? null) !== null ? number_format((int) $supervisor['maxJobs']) : '—',
                    'Nice' => ($supervisor['nice'] ?? null) !== null ? (string) $supervisor['nice'] : '—',
                    'Sleep' => ($supervisor['sleep'] ?? null) !== null ? (string) $supervisor['sleep'] . ' s' : '—',
                    'Rest' => ($supervisor['rest'] ?? null) !== null ? (string) $supervisor['rest'] . ' s' : '—',
                    'Backoff' => $formatValue($backoff),
                    'Balance cooldown' => ($supervisor['balanceCooldown'] ?? null) !== null ? (string) $supervisor['balanceCooldown'] . ' s' : '—',
                    'Balance max shift' => ($supervisor['balanceMaxShift'] ?? null) !== null ? (string) $supervisor['balanceMaxShift'] : '—',
                    'Auto scaling strategy' => ($supervisor['autoScalingStrategy'] ?? '') !== '' ? $supervisor['autoScalingStrategy'] : '—',
                    'Force' => $formatValue($supervisor['force'] ?? null),
                ] as $label => $value)
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <dt class="text-muted-foreground">{{ $label }}</dt>
                        <dd class="text-right text-foreground break-all">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div class="mt-6 card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/20 px-4 py-3">
            <h3 class="text-section-title text-foreground">Queues</h3>
            <p class="text-xs text-muted-foreground">{{ \count($queueNames) }} queue(s)</p>
        </div>
        <x-table id="supervisor-queues">
            <x-slot:head>
                <x-table.th column="queue" class="min-w-40">Queue</x-table.th>
                <x-table.th column="processes" class="min-w-24">Processes</x-table.th>
            </x-slot:head>
            @forelse($queueNames as $queueName)
                <tr class="transition-colors hover:bg-muted/30">
                    <td class="px-4 py-2.5 text-sm" data-column-id="queue">
                        <a href="{{ route('horizon.queues.show', ['queue' => $queueName]) }}" class="link font-mono" data-turbo-action="replace">{{ $queueName }}</a>
                    </td>
                    <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="processes">
                        {{ isset($processesByQueue[$queueName]) ? number_format($processesByQueue[$queueName]) : '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-4 py-8 text-center text-sm text-muted-foreground">No queues are configured for this supervisor.</td>
                </tr>
            @endforelse
        </x-table>
    </div>

    @include('pages.partials.raw-options-details', [
        'options' => $options,
        'title' => 'Raw supervisor options',
    ])
@endif
