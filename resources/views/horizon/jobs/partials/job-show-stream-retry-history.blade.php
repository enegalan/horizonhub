@if($job->status === 'failed' && count($retryHistory) > 0)
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
            resizable-key="horizon-job-retry-history"
            column-ids="uuid,status,retried_at"
            body-key="horizon-job-retry-history"
        >
            <x-slot:head>
                <tr class="border-b border-border bg-muted/50">
                    <th class="table-header px-4 py-2.5" data-column-id="uuid">UUID</th>
                    <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="status">Status</th>
                    <th class="table-header px-4 py-2.5 min-w-[100px]" data-column-id="retried_at">Retried at</th>
                </tr>
            </x-slot:head>
            @foreach($retryHistory as $retryJob)
                @php
                    $retriedAtIso = null;
                    if (isset($retryJob['retried_at']) && \is_numeric($retryJob['retried_at'])) {
                        $retriedAtIso = \Carbon\Carbon::createFromTimestamp((int) $retryJob['retried_at'])->toIso8601String();
                    }
                    $retryStatus = isset($retryJob['status']) && \is_string($retryJob['status']) && $retryJob['status'] !== ''
                        ? $retryJob['status']
                        : null;
                @endphp
                <tr class="transition-colors hover:bg-muted/30">
                    <td class="px-4 py-2.5 text-sm text-primary truncate max-w-[180px]" data-column-id="uuid">
                        @if(isset($retryJob['id']) && \is_string($retryJob['id']) && $retryJob['id'] !== '' && $job->service)
                            <a class="link" href="{{ route('horizon.jobs.show', ['job' => $retryJob['id'], 'service_id' => $job->service->id]) }}" data-turbo-action="replace">{{ $retryJob['id'] }}</a>
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
                        @elseif($retryStatus !== null)
                            <span class="badge-muted">{{ $retryStatus }}</span>
                        @else
                            –
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-[180px]" data-column-id="retried_at" data-datetime="{{ $retriedAtIso ?? '' }}">-</td>
                </tr>
            @endforeach
        </x-table>
    </div>
@endif
