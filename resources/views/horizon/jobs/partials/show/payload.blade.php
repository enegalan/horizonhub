<div>
    <dt class="label-muted mb-1">Payload</dt>
    <div class="mt-1 rounded-md border border-border bg-muted/30 p-3 text-xs font-mono text-foreground break-words">
        <div
            class="horizon-json-tree"
            data-stream-preserve-client
            data-json-tree="payload"
            data-json-source="{{ e($payload ?? 'null') }}"
        ></div>
    </div>
</div>
