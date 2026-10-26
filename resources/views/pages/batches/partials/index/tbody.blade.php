@php
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>>|list<array<string, mixed>> $batches */
    $search = (string) ($search ?? '');
@endphp
@if(! empty($defer) && count($batches) === 0)
    <x-skeleton.table-rows rows="8" columns="6" />
@else
    @forelse($batches as $batch)
        @php
            $batchId = (string) ($batch['id'] ?? '');
            $batchName = (string) ($batch['name'] ?? '');
            $createdAt = $batch['created_at'] ?? null;
            $status = (string) ($batch['status'] ?? '');
        @endphp
        <tr class="transition-colors hover:bg-muted/30" data-stream-row-id="batch-{{ (int) ($batch['service_id'] ?? 0) }}-{{ rawurlencode($batchId) }}">
            <td class="px-4 py-2.5 text-sm font-medium text-foreground truncate" data-column-id="service">
                @if(! empty($batch['service']))
                    <a href="{{ route('horizon.services.show', $batch['service']) }}" class="link" data-turbo-action="replace" title="{{ $batch['service_name'] ?? '' }}">{{ $batch['service_name'] ?? '' }}</a>
                @else
                    {{ $batch['service_name'] ?? '—' }}
                @endif
            </td>
            <td class="px-4 py-2.5 text-sm" data-column-id="batch">
                <a href="{{ route('horizon.services.batches.show', ['service' => $batch['service_id'] ?? 0, 'batch' => $batchId]) }}" class="link font-medium" data-turbo-action="replace" title="{{ $batchId }}">
                    {{ $batchName !== '' ? $batchName : $batchId }}
                </a>
                @if($batchName !== '')
                    <p class="mt-0.5 font-mono text-xs text-muted-foreground break-all">{{ $batchId }}</p>
                @endif
            </td>
            <td class="px-4 py-2.5 text-sm" data-column-id="status">
                <x-batch-status :status="$status" />
            </td>
            <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="size">
                {{ number_format((int) ($batch['total_jobs'] ?? 0)) }}
            </td>
            <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="completion">
                {{ (int) ($batch['progress'] ?? 0) }}%
            </td>
            <td class="px-4 py-2.5 text-sm tabular-nums text-muted-foreground" data-column-id="created">
                {{ $createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m-d H:i:s') : '—' }}
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="px-4 py-10 text-center text-sm text-muted-foreground">
                {{ $search !== '' ? 'No batches matched the selected services and search.' : 'No batches were reported by the selected services.' }}
            </td>
        </tr>
    @endforelse
@endif
