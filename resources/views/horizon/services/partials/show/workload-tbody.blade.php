@include('horizon.partials.workload-rows-tbody', [
    'workloadRows' => $workloadQueues ?? [],
    'includeServiceColumn' => false,
    'emptyId' => 'service-show-workload-empty',
    'rowIdPrefix' => 'wl',
    'emptyTitle' => 'No queues for this service yet',
    'emptyDescription' => 'Queues will appear here once jobs are dispatched to this service.',
])
