@if(! empty($tags))
    <div @class(['flex flex-wrap gap-1.5', $class ?? null])>
        @foreach($tags as $tag)
            @if(\is_string($tag) && $tag !== '')
                <span class="badge-muted text-[10px] font-medium normal-case tracking-normal">{{ $tag }}</span>
            @endif
        @endforeach
    </div>
@endif

