@props([
    'columns' => 3,
])

@for ($i = 0; $i < (int) $columns; $i++)
    <div class="rounded-lg border border-border/70 bg-muted/20 px-4 py-3">
        <div class="skeleton h-3 w-20" style="--skeleton-delay: {{ $i * 80 }}ms"></div>
        <div class="skeleton mt-3 h-7 w-12" style="--skeleton-delay: {{ ($i * 80) + 120 }}ms"></div>
    </div>
@endfor
