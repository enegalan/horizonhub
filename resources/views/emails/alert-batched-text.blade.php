Horizon Hub Alert

{{ $alert->rule_type }}
@if($service)
· {{ $service->name }}
@endif
@if(count($enrichedEvents) > 1)
· {{ count($enrichedEvents) }} events
@endif

@foreach($enrichedEvents as $index => $ev)
---- {{ count($enrichedEvents) > 1 ? 'Event ' . ($index + 1) : 'Failed job' }} ----
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
@if(isset($ev['job_id']) && $ev['job_id'])
Job ID: {{ $ev['job_id'] }}
@endif
@if(!empty($ev['exception']))

Exception:
{{ $ev['exception'] }}
@endif

@endforeach

Sent at {{ now()->format('Y-m-d H:i:s T') }}
