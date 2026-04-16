@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
@endphp
@if($paginator->total() > 0)
    <x-pagination :paginator="$paginator" />
@endif
