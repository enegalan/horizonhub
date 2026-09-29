@if($job->status === \App\Enums\JobStatus::Failed->value && count($retryHistory) > 0)
    @php
        $retryRowsNormalized = [];
        foreach ($retryHistory as $row) {
            $retryRowsNormalized[] = [
                'id' => $row['id'] ?? null,
                'status' => $row['status'] ?? null,
                'retried_at' => isset($row['retried_at']) && \is_numeric($row['retried_at']) ? (int) $row['retried_at'] : null,
            ];
        }
        $retryHistoryStreamSig = \hash('sha256', \json_encode($retryRowsNormalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @endphp
    <div data-horizon-stream-sig="{{ $retryHistoryStreamSig }}">
        <dt class="label-muted mb-1">Retries history</dt>
        <x-table
            id="horizon-job-retry-history"
        >
            <x-slot:head>
                <x-table.th column="uuid">UUID</x-table.th>
                <x-table.th column="status" class="min-w-[100px]">Status</x-table.th>
                <x-table.th column="retried_at" class="min-w-[100px]">Retried at</x-table.th>
            </x-slot:head>
            @foreach($retryHistory as $retryJob)
                @php
                    $retriedAt = null;
                    if (isset($retryJob['retried_at']) && \is_numeric($retryJob['retried_at'])) {
                        $retriedAt = \Carbon\Carbon::createFromTimestamp((int) $retryJob['retried_at']);
                    }
                    $retryStatus = isset($retryJob['status']) && \is_string($retryJob['status']) && $retryJob['status'] !== ''
                        ? \App\Enums\JobStatus::normalizeStatus($retryJob['status'])?->value ?? $retryJob['status']
                        : null;
                @endphp
                <tr class="transition-colors hover:bg-muted/30">
                    <td class="px-4 py-2.5 text-sm text-primary truncate max-w-[180px]" data-column-id="uuid">
                        @if(!empty($retryJob['id']) && \is_string($retryJob['id']) && $job->service)
                            <a class="link" href="{{ route('horizon.jobs.show', ['job' => $retryJob['id']]) }}" data-turbo-action="replace">{{ $retryJob['id'] }}</a>
                        @else
                            {{ $retryJob['id'] ?? '–' }}
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-sm text-foreground" data-column-id="status">
                        @if($retryStatus === 'failed')
                            <span class="badge-danger">{{ $retryStatus }}</span>
                        @elseif($retryStatus === 'processed' || $retryStatus === 'completed')
                            <span class="badge-success">{{ $retryStatus }}</span>
                        @elseif($retryStatus === 'processing')
                            <span class="badge-warning">{{ $retryStatus }}</span>
                        @elseif(!empty($retryStatus))
                            <span class="badge-muted">{{ $retryStatus }}</span>
                        @else
                            –
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="retried_at">{{ $retriedAt?->format('Y-m-d H:i:s') ?? '–' }}</td>
                </tr>
            @endforeach
        </x-table>
    </div>
@endif
