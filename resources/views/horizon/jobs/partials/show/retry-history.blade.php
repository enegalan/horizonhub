@if($job->status === \App\Enums\JobStatus::Failed->value && count($job->retried_by) > 0)
    @php
        $retryHistoryStreamSig = \hash('sha256', \json_encode($job->retried_by, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @endphp
    <div data-horizon-stream-sig="{{ $retryHistoryStreamSig }}">
        <dt class="label-muted mb-1">Retries history</dt>
        <x-table
            id="horizon-job-retry-history"
        >
            <x-slot:head>
                <x-table.th column="uuid">UUID</x-table.th>
                <x-table.th column="status" class="min-w-25">Status</x-table.th>
                <x-table.th column="retried_at" class="min-w-25">Retried at</x-table.th>
            </x-slot:head>
            @foreach($job->retried_by as $retryJob)
                <tr class="transition-colors hover:bg-muted/30">
                    <td class="px-4 py-2.5 text-sm text-primary truncate max-w-45" data-column-id="uuid">
                        @if(!empty($retryJob['id']) && \is_string($retryJob['id']) && $job->service)
                            <a class="link" href="{{ route('horizon.jobs.show', ['job' => $retryJob['id']]) }}" data-turbo-action="replace">{{ $retryJob['id'] }}</a>
                        @else
                            {{ $retryJob['id'] ?? '–' }}
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-sm text-foreground" data-column-id="status">
                        @if(($retryJob['status'] ?? null) === 'failed')
                            <span class="badge-danger">{{ $retryJob['status'] }}</span>
                        @elseif(($retryJob['status'] ?? null) === 'processed' || ($retryJob['status'] ?? null) === 'completed')
                            <span class="badge-success">{{ $retryJob['status'] }}</span>
                        @elseif(($retryJob['status'] ?? null) === 'processing')
                            <span class="badge-warning">{{ $retryJob['status'] }}</span>
                        @elseif(!empty($retryJob['status']))
                            <span class="badge-muted">{{ $retryJob['status'] }}</span>
                        @else
                            –
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-xs text-muted-foreground truncate max-w-45" data-column-id="retried_at">{{ $retryJob['retried_at']?->format('Y-m-d H:i:s') ?? '–' }}</td>
                </tr>
            @endforeach
        </x-table>
    </div>
@endif
