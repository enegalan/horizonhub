@php
    /** @var string $jsonSource */
    $jsonSource ??= 'null';
    /** @var string $title */
    $title ??= 'Raw options';
@endphp

<details class="mt-6 card overflow-hidden">
    <summary class="cursor-pointer bg-muted/20 px-4 py-3 text-sm font-medium text-foreground">
        {{ $title }}
    </summary>
    <div class="p-4">
        <div class="rounded-md border border-border bg-muted/30 p-3 text-xs font-mono text-foreground wrap-break-word">
            <div
                class="horizon-json-tree"
                data-stream-preserve-client
                data-json-tree="options"
                data-json-source="{{ e($jsonSource) }}"
            ></div>
        </div>
    </div>
</details>
