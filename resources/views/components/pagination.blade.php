@props(['paginator' => null, 'onEachSide' => 2])

@if (isset($paginator) && $paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $window = (int) $onEachSide;
        $slider = [];
        if ($last <= ($window * 2 + 3)) {
            $slider = range(1, $last);
        } else {
            if ($current <= $window + 2) {
                $slider = array_merge(range(1, min($window * 2 + 2, $last)), ['...'], [$last]);
            } elseif ($current >= $last - $window - 1) {
                $slider = array_merge([1], ['...'], range(max(1, $last - $window * 2 - 1), $last));
            } else {
                $slider = array_merge([1], ['...'], range($current - $window, $current + $window), ['...'], [$last]);
            }
        }
    @endphp
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center gap-2">
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground cursor-not-allowed">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" wire:navigate class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground hover:text-foreground transition-colors">Previous</a>
        @endif

        @foreach ($slider as $page)
            @if ($page === '...')
                <span class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground">…</span>
            @elseif ($page == $current)
                <span class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm font-medium bg-primary text-primary-foreground" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" wire:navigate class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground hover:text-foreground transition-colors">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" wire:navigate class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground hover:text-foreground transition-colors">Next</a>
        @else
            <span class="inline-flex items-center justify-center rounded-md px-2 py-1 text-sm text-muted-foreground cursor-not-allowed">Next</span>
        @endif
    </nav>
@endif
