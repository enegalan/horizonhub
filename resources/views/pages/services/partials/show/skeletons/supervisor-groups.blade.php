<div class="card mb-4">
    <div class="flex items-center justify-between border-b border-border px-4 py-3">
        <x-skeleton.text class="h-6 w-40" />
        <x-skeleton.text class="h-4 w-28" />
    </div>
    <x-table
        id="horizon-service-supervisors"
    >
        <x-slot:head>
            <x-table.th column="supervisor" class="min-w-40">Supervisor</x-table.th>
            <x-table.th column="connection" class="min-w-30">Connection</x-table.th>
            <x-table.th column="queues" class="min-w-40">Queues</x-table.th>
            <x-table.th column="processes" class="min-w-20">Processes</x-table.th>
            <x-table.th column="balancing" class="min-w-30">Balancing</x-table.th>
        </x-slot:head>
        <x-skeleton.table-rows rows="4" columns="5" />
    </x-table>
</div>
