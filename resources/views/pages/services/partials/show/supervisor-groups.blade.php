@if(isset($supervisorGroups) && $supervisorGroups->isNotEmpty())
    @foreach($supervisorGroups as $groupName => $groupSupervisors)
        <div class="card mb-4">
            <div class="flex items-center justify-between gap-2 px-4 py-3">
                <h3 class="text-section-title text-foreground">{{ $groupName }}</h3>
                <p class="text-xs text-muted-foreground">{{ $groupSupervisors->count() }} supervisor(s)</p>
            </div>
            <x-table
                id="horizon-service-supervisors-{{ \Illuminate\Support\Str::slug($groupName) }}"
            >
                <x-slot:head>
                    <x-table.th column="supervisor" class="min-w-40">Supervisor</x-table.th>
                    <x-table.th column="connection" class="min-w-30">Connection</x-table.th>
                    <x-table.th column="queues" class="min-w-40">Queues</x-table.th>
                    <x-table.th column="processes" class="min-w-20">Processes</x-table.th>
                    <x-table.th column="balancing" class="min-w-30">Balancing</x-table.th>
                </x-slot:head>
                @foreach($groupSupervisors as $supervisor)
                    <tr class="transition-colors hover:bg-muted/30">
                        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="supervisor">
                            <a href="{{ route('horizon.services.supervisors.show', ['service' => $service, 'supervisor' => $supervisor->name]) }}" class="link" data-turbo-action="replace">{{ $supervisor->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="connection">
                            {{ $supervisor->connection !== '' ? $supervisor->connection : '–' }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="queues">
                            {{ $supervisor->queues !== '' ? $supervisor->queues : '–' }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-muted-foreground" data-column-id="processes">
                            {{ $supervisor->processes !== null ? number_format($supervisor->processes) : '–' }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="balancing">
                            {{ $supervisor->balancing !== '' ? $supervisor->balancing : '–' }}
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endforeach
@endif
