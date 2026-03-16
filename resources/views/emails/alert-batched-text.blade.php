Horizon Hub Alert

{{ $alert->rule_type }}
@if($service)
· {{ $service->name }}
@endif
@if($totalEventCount > 1)
· {{ $totalEventCount }} events
@endif
@if($totalEventCount > count($enrichedEvents))

Showing first {{ count($enrichedEvents) }} of {{ $totalEventCount }}. View full list: {{ route('horizon.alerts.show', $alert) }}
@endif

@foreach($enrichedEvents as $index => $ev)
---- {{ $totalEventCount > 1 ? 'Event ' . ($index + 1) : 'Failed job' }} ----
@if(!empty($ev['job_class']))
Job: {{ $ev['job_class'] }}
@endif
@if(!empty($ev['queue']))
Queue: {{ $ev['queue'] }}
@endif
@if(!empty($ev['failed_at']))
Failed at: {{ $ev['failed_at'] }}
@endif
@if(isset($ev['attempts']) && $ev['attempts'] !== null)
Attempts: {{ $ev['attempts'] }}
@endif
@if(isset($ev['job_uuid']) && $ev['job_uuid'])
Job UUID: {{ $ev['job_uuid'] }}
@endif
@if(!empty($ev['exception']))

Exception:
{{ $ev['exception'] }}
@endif

@endforeach

Sent at {{ now()->format('Y-m-d H:i:s T') }}
