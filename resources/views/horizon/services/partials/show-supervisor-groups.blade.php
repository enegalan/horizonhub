@if(isset($supervisorGroups) && $supervisorGroups->isNotEmpty())
    @foreach($supervisorGroups as $groupName => $groupSupervisors)
        <div class="card mb-4">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <h3 class="text-section-title text-foreground">{{ $groupName }}</h3>
                <p class="text-xs text-muted-foreground">{{ $groupSupervisors->count() }} supervisor(s)</p>
            </div>
            <x-table
                resizable-key="horizon-service-supervisors-{{ \Illuminate\Support\Str::slug($groupName) }}"
                column-ids="supervisor,connection,queues,processes,balancing"
                body-key="horizon-service-supervisors-{{ \Illuminate\Support\Str::slug($groupName) }}"
            >
                <x-slot:head>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="supervisor">Supervisor</th>
                        <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="connection">Connection</th>
                        <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="queues">Queues</th>
                        <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="processes">Processes</th>
                        <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="balancing">Balancing</th>
                    </tr>
                </x-slot:head>
                @foreach($groupSupervisors as $supervisor)
                    <tr class="transition-colors hover:bg-muted/30">
                        <td class="px-4 py-2.5 font-mono text-xs text-muted-foreground break-all" data-column-id="supervisor">
                            {{ $supervisor->name }}
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
