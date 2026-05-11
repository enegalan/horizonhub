@props([
    'rows' => 5,
    'columns' => 1,
])
@for ($i = 0; $i < (int) $rows; $i++)
    <tr class="border-b border-border/60">
        @for ($c = 0; $c < (int) $columns; $c++)
            <td class="px-4 py-3">
                <div
                    class="skeleton h-4 w-full max-w-[200px]"
                    style="--skeleton-delay: {{ ($i * 80) + ($c * 35) }}ms"
                ></div>
            </td>
        @endfor
    </tr>
@endfor
