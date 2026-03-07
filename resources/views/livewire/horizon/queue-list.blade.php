<div @horizon-queue-action-done.window="$wire.$refresh()">
    <div class="card">
        <div class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div class="space-y-2">
                <x-input-label class="text-[11px] font-medium text-muted-foreground">Service</x-input-label>
                <x-select wire:model.live="serviceFilter" class="w-48">
                    <option value="">All</option>
                    @foreach($services as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </x-select>
            </div>
            @if($queueCount > 0)
                <p class="text-xs text-muted-foreground">{{ $queueCount }} queue(s), {{ number_format($totalJobs) }} total jobs</p>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full" data-resizable-table="horizon-queue-list" data-column-ids="service,queue,status,job_count,actions" data-service-filter="{{ $serviceFilter }}">
                <thead wire:ignore>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="service">Service</th>
                        <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="queue">Queue</th>
                        <th class="table-header px-4 py-2.5" data-column-id="status">Status</th>
                        <th class="table-header px-4 py-2.5" data-column-id="job_count">Job count</th>
                        <th class="table-header px-4 py-2.5 w-40" data-column-id="actions">Actions</th>
                    </tr>
                </thead>
                @include('livewire.horizon.partials.queue-list-tbody', ['queues' => $queues, 'queueStates' => $queueStates])
            </table>
        </div>
    </div>
</div>

@script
<script>
    window.addEventListener('horizon-hub-refresh', () => {
        try { $wire.$refresh(); } catch (e) {}
    });
</script>
@endscript
