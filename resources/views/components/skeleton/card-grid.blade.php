@props([
    'items' => 6,
])

@for ($i = 0; $i < (int) $items; $i++)
    <div class="card overflow-hidden p-4">
        <div class="flex items-start gap-3">
            <div class="skeleton size-11 shrink-0 rounded-xl" style="--skeleton-delay: {{ $i * 70 }}ms"></div>
            <div class="min-w-0 flex-1 space-y-2">
                <div class="skeleton h-4 w-2/3" style="--skeleton-delay: {{ ($i * 70) + 80 }}ms"></div>
                <div class="skeleton h-3 w-1/2" style="--skeleton-delay: {{ ($i * 70) + 160 }}ms"></div>
            </div>
        </div>
        <div class="mt-4 space-y-2">
            <div class="skeleton h-3 w-full" style="--skeleton-delay: {{ ($i * 70) + 240 }}ms"></div>
            <div class="skeleton h-3 w-5/6" style="--skeleton-delay: {{ ($i * 70) + 320 }}ms"></div>
        </div>
    </div>
@endfor
