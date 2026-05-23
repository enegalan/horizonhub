<div class="card mb-4">
    <div class="flex items-center justify-between border-b border-border px-4 py-3">
        <x-skeleton.text class="h-6 w-40" />
        <x-skeleton.text class="h-4 w-28" />
    </div>
    <div class="table-scroll">
        <table class="min-w-full overflow-hidden">
            <thead>
                <tr class="border-b border-border bg-muted/50">
                    <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="supervisor">Supervisor</th>
                    <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="connection">Connection</th>
                    <th class="table-header px-4 py-2.5 min-w-[160px]" data-column-id="queues">Queues</th>
                    <th class="table-header px-4 py-2.5 min-w-[80px]" data-column-id="processes">Processes</th>
                    <th class="table-header px-4 py-2.5 min-w-[120px]" data-column-id="balancing">Balancing</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                <x-skeleton.table-rows rows="4" columns="5" />
            </tbody>
        </table>
    </div>
</div>
