<div>
    <dt class="label-muted mb-1">Data</dt>
    <div class="mt-1 rounded-md border border-border bg-muted/30 p-3 text-xs font-mono text-foreground break-words">
        <div
            class="horizon-json-tree"
            data-stream-preserve-client
            data-json-tree="data"
            data-json-source="{{ e($commandData ?? 'null') }}"
        ></div>
    </div>
</div>
