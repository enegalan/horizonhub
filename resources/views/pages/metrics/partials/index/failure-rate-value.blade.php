@php($r = $failureRate24h ?? ['rate' => 0.0, 'processed' => 0, 'failed' => 0])
{{ $r['rate'] ?? 0 }}% <span class="text-xs font-normal text-muted-foreground">({{ $r['failed'] ?? 0 }} failed / {{ $r['processed'] ?? 0 }} processed)</span>
