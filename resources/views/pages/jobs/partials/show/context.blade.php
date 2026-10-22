@if($job->status === \App\Enums\JobStatus::Failed->value)
    <div>
        <dt class="label-muted mb-1">Exception context</dt>
        <div class="mt-1 rounded-md border border-border bg-muted/30 p-3 text-foreground wrap-break-word">
            <div
                class="horizon-json-tree"
                data-stream-preserve-client
                data-json-tree="context"
                data-json-source="{{ e($job->context ?? 'null') }}"
            ></div>
        </div>
    </div>
@endif
