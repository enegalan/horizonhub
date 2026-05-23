{{ $notification['appName'] }} – {{ $notification['alertName'] }}

{{ $notification['ruleLabel'] }}
· {{ $notification['serviceName'] }}
@if($notification['totalEventCount'] > 1)
· {{ $notification['totalEventCount'] }} events
@endif

Condition: {{ $notification['condition'] }}

@if($notification['hasJobDetails'])
@foreach($notification['events'] as $ev)
---- {{ $notification['totalEventCount'] > 1 ? 'Event ' . $ev['index'] : 'Failed job' }} ----
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
@if(!empty($ev['triggered_at']))
Triggered at: {{ $ev['triggered_at'] }}
@endif
@if(!empty($ev['job_uuid']))
Job UUID: {{ $ev['job_uuid'] }}
@endif
@if(!empty($ev['exceptionPreview']))

Exception:
{{ $ev['exceptionPreview'] }}
@endif
@if(!empty($ev['exceptionExpandable']) && !empty($ev['jobUrl']))
Show more: {{ $ev['jobUrl'] }}
@endif
@if(!empty($ev['jobUrl']))
View job: {{ $ev['jobUrl'] }}
@endif

@endforeach
@else
@if(!empty($notification['detectedAt']))
Detected at: {{ $notification['detectedAt'] }}
@endif
@if($notification['totalEventCount'] > 1)
Events: {{ $notification['totalEventCount'] }}
@endif

@endif

View alert: {{ $notification['alertUrl'] }}
@if(!empty($notification['serviceUrl']))
View service: {{ $notification['serviceUrl'] }}
@endif

Sent at {{ $notification['sentAt'] }}
