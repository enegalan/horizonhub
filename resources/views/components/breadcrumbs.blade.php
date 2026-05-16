@props([
    'items' => [],
])

<nav {{ $attributes->class(['mb-3 text-xs text-muted-foreground']) }} aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-1">
        @foreach($items as $index => $item)
            @if($index > 0)
                <li class="text-muted-foreground/60" aria-hidden="true">/</li>
            @endif
            <li class="min-w-0">
                @if(! empty($item['url'] ?? null))
                    <a href="{{ $item['url'] }}" class="link" data-turbo-action="replace">{{ $item['label'] }}</a>
                @else
                    <span class="truncate text-foreground">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
