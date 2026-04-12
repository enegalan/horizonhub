@if($job->status === 'failed')
    <div>
        <dt class="label-muted mb-1">Exception context</dt>
        <div class="mt-1 rounded-md border border-border bg-muted/30 p-3 text-foreground break-words">
            <div
                data-json-tree="context"
                data-json-source="{{ e($context) }}"
            ></div>
        </div>
    </div>
@endif
