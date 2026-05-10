@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
@endphp
@if(isset($paginator) && $paginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $paginator->total() > 0)
    <x-pagination :paginator="$paginator" />
@endif
