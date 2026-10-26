@php
    /** @var list<array<string, mixed>> $masterGroups */
    $masterGroups ??= [];
    $showServiceLink ??= false;
    $emptyTitle ??= 'No supervisors';
    $emptyDescription ??= null;
@endphp

@forelse($masterGroups as $group)
    @php
        $supervisors = $group['supervisors'];
        $groupStatus = $group['status'];
        $serviceId = $group['service_id'];
        $serviceName = $group['service'];
        $groupName = $group['name'];
        $environment = $group['environment'];
        $pid = $group['pid'];
        $tableId = 'master-' . (int) $serviceId . '-' . \Illuminate\Support\Str::slug($groupName) . '-' . $loop->index;
    @endphp
    <div class="card overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border bg-muted/20 px-4 py-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    @if($showServiceLink && $serviceId)
                        <a href="{{ route('horizon.services.show', $serviceId) }}" class="link text-sm font-medium" data-turbo-action="replace">{{ $serviceName }}</a>
                        <span class="text-muted-foreground/60" aria-hidden="true">/</span>
                    @endif
                    <span class="font-mono text-sm text-foreground break-all">{{ $groupName }}</span>
                    <x-horizon-status :status="$groupStatus" />
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ \count($supervisors) }} supervisor(s)
                    @if($environment !== '')
                        · Environment {{ $environment }}
                    @endif
                    @if($pid !== '')
                        · PID {{ $pid }}
                    @endif
                </p>
            </div>
        </div>

        <x-table :id="$tableId">
            <x-slot:head>
                <x-table.th column="supervisor" class="min-w-40">Supervisor</x-table.th>
                <x-table.th column="status" class="min-w-24">Status</x-table.th>
                <x-table.th column="connection" class="min-w-28">Connection</x-table.th>
                <x-table.th column="queues" class="min-w-44">Queues</x-table.th>
                <x-table.th column="processes" class="min-w-24">Processes</x-table.th>
                <x-table.th column="balance" class="min-w-28">Balance</x-table.th>
            </x-slot:head>

            @foreach($supervisors as $supervisor)
                @php
                    $supervisorName = $supervisor['name'];
                    $supervisorStatus = $supervisor['status'];
                    $queueNames = $supervisor['queueNames'];
                    $warnings = $supervisor['warnings'];
                @endphp
                <tr class="align-top transition-colors hover:bg-muted/30">
                    <td class="px-4 py-2.5 text-sm font-medium text-foreground break-all" data-column-id="supervisor">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('horizon.services.supervisors.show', ['service' => $serviceId, 'supervisor' => $supervisorName]) }}" class="link" data-turbo-action="replace">{{ $supervisorName }}</a>
                            @if(! empty($warnings))
                                <span class="badge-warning" title="{{ \implode(' ', $warnings) }}">!</span>
                            @endif
                        </div>
                        @if(! empty($warnings))
                            <p class="mt-1 text-xs font-normal text-amber-700 dark:text-amber-300">{{ $warnings[0] }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-sm" data-column-id="status">
                        <x-horizon-status :status="$supervisorStatus" />
                    </td>
                    <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="connection">
                        {{ $supervisor['connection'] !== '' ? $supervisor['connection'] : '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="queues">
                        <div class="flex flex-wrap gap-1">
                            @forelse($queueNames as $queueName)
                                <a href="{{ route('horizon.queues.show', ['queue' => $queueName]) }}" class="badge-muted font-mono hover:bg-muted/80" data-turbo-action="replace">{{ $queueName }}</a>
                            @empty
                                <span>—</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="px-4 py-2.5 text-sm tabular-nums text-foreground" data-column-id="processes">
                        {{ $supervisor['processes'] !== null ? \number_format((int) $supervisor['processes']) : '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="balance">
                        {{ $supervisor['balance'] !== '' ? $supervisor['balance'] : '—' }}
                    </td>
                </tr>
            @endforeach
        </x-table>
    </div>
@empty
    <x-empty-state :title="$emptyTitle" :description="$emptyDescription" />
@endforelse
