<p class="mb-3 text-xs text-muted-foreground">
    <a href="{{ route('horizon.index') }}" class="link" data-turbo-action="replace">Jobs</a>
    @if($job->service)
        / <a href="{{ route('horizon.services.show', $job->service) }}" class="link" data-turbo-action="replace">{{ $job->service->name }}</a>
    @endif
    / <span class="text-foreground">{{ $job->name ?? $job->uuid }}</span>
</p>
