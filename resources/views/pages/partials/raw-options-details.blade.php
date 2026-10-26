@php
    /** @var array<string, mixed> $options */
    $options ??= [];
    /** @var string $title */
    $title ??= 'Raw options';
@endphp

<details class="mt-6 card overflow-hidden">
    <summary class="cursor-pointer border-b border-border bg-muted/20 px-4 py-3 text-sm font-medium text-foreground">
        {{ $title }}
    </summary>
    <pre class="max-h-96 overflow-auto p-4 text-xs text-muted-foreground">{{ \json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
</details>
