@php
    /** @var list<array<string, mixed>> $rows */
@endphp

@forelse($rows as $row)
    <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="qs-{{ (int) $row['service_id'] }}">
        <td class="px-4 py-2.5 text-sm font-medium text-foreground" data-column-id="service">
            <a href="{{ route('horizon.services.show', $row['service_id']) }}" class="link" data-turbo-action="replace">{{ $row['service_name'] }}</a>
        </td>
        <td class="px-4 py-2.5 text-sm text-muted-foreground break-all" data-column-id="connection">
            {{ $row['connection'] !== '' ? $row['connection'] : '—' }}
        </td>
        <td class="px-4 py-2.5 text-sm font-medium tabular-nums text-foreground" data-column-id="pending">
            {{ number_format((int) $row['jobs']) }}
        </td>
        <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="processes">
            {{ $row['processes'] !== null ? number_format((int) $row['processes']) : '—' }}
        </td>
        <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="wait">
            {{ $row['wait'] !== null ? \App\Support\Jobs\JobRuntime::getFormattedRuntime((float) $row['wait']) : '—' }}
        </td>
        <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="throughput">
            {{ $row['throughput'] !== null ? number_format((float) $row['throughput'], 2) : '—' }}
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="px-4 py-8 text-center text-sm text-muted-foreground">
            No services currently report this queue.
        </td>
    </tr>
@endforelse
