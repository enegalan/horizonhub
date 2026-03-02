<?php

namespace HorizonHub\Agent\Support;

use Illuminate\Support\Str;

class EventPayloadBuilder {
    /** @var array<string, float> job_id => microtime when JobProcessing was built */
    private static array $jobStartedAt = array();

    public static function fromJobProcessed(object $event): array {
        $job = static::jobFromEvent($event);
        $base = static::baseJobPayload($event, $job, 'JobProcessed', 'processed');
        $payload = array_merge($base, [
            'processed_at' => now()->toIso8601String(),
        ]);
        $runtimeSeconds = static::popRuntimeSeconds($base['job_id'] ?? '');
        if ($runtimeSeconds !== null) {
            $payload['runtime_seconds'] = round($runtimeSeconds, 3);
        }
        return $payload;
    }

    public static function fromJobDeleted(object $event): array {
        $job = static::jobFromEvent($event);
        $base = static::baseJobPayload($event, $job, 'JobProcessed', 'processed');
        $payload = array_merge($base, [
            'processed_at' => now()->toIso8601String(),
        ]);
        $runtimeSeconds = static::popRuntimeSeconds($base['job_id'] ?? '');
        if ($runtimeSeconds !== null) {
            $payload['runtime_seconds'] = round($runtimeSeconds, 3);
        }
        return $payload;
    }

    public static function fromJobFailed(object $event): array {
        $job = static::jobFromEvent($event);
        $base = static::baseJobPayload($event, $job, 'JobFailed', 'failed');
        $payload = array_merge($base, [
            'failed_at' => now()->toIso8601String(),
            'exception' => static::formatException($event->exception ?? null),
        ]);
        $runtimeSeconds = static::popRuntimeSeconds($base['job_id'] ?? '');
        if ($runtimeSeconds !== null) {
            $payload['runtime_seconds'] = round($runtimeSeconds, 3);
        }
        return $payload;
    }

    public static function fromJobProcessing(object $event): array {
        $job = static::jobFromEvent($event);
        $payload = static::baseJobPayload($event, $job, 'JobProcessing', 'processing');
        $jobId = $payload['job_id'] ?? '';
        if ($jobId !== '') {
            self::$jobStartedAt[$jobId] = microtime(true);
        }
        return $payload;
    }

    /**
     * Build JobProcessing payload from Horizon's JobReserved and record start time for runtime.
     * Horizon fires JobReserved when a worker picks up a job; JobDeleted when it finishes.
     */
    public static function fromJobReserved(object $event): array {
        $jobId = method_exists($event->payload, 'id') ? (string) $event->payload->id() : '';
        if ($jobId !== '') {
            self::$jobStartedAt[$jobId] = microtime(true);
        }
        $conn = property_exists($event, 'connectionName') ? $event->connectionName : 'redis';
        $queueName = property_exists($event, 'queue') ? $event->queue : 'default';
        $queue = $conn . '.' . $queueName;
        $decoded = isset($event->payload->decoded) ? $event->payload->decoded : array();
        $name = isset($decoded['displayName']) ? $decoded['displayName'] : null;
        $result = array(
            'event_type' => 'JobProcessing',
            'job_id' => $jobId,
            'queue' => $queue,
            'status' => 'processing',
            'attempts' => 0,
            'name' => $name,
            'payload' => $decoded,
        );
        $queuedAt = static::queuedAtFromPayload(is_array($decoded) ? $decoded : array());
        if ($queuedAt !== null) {
            $result['queued_at'] = $queuedAt;
        }
        return $result;
    }

    private static function formatException(mixed $exception): ?string {
        if ($exception === null) {
            return null;
        }
        if (is_object($exception) && method_exists($exception, 'getMessage')) {
            $msg = $exception->getMessage();
            $trace = method_exists($exception, 'getTraceAsString') ? $exception->getTraceAsString() : '';
            return $trace !== '' ? $msg . "\n\n" . $trace : $msg;
        }
        return (string) $exception;
    }

    /**
     * Returns runtime in seconds since JobProcessing and removes the stored start time.
     */
    private static function popRuntimeSeconds(string $jobId): ?float {
        if ($jobId === '' || ! isset(self::$jobStartedAt[$jobId])) {
            return null;
        }
        $start = self::$jobStartedAt[$jobId];
        unset(self::$jobStartedAt[$jobId]);
        $seconds = microtime(true) - $start;
        return $seconds >= 0 ? $seconds : null;
    }

    public static function fromSupervisorLooped(object $event): array {
        $supervisorName = 'default';
        if (property_exists($event, 'supervisor') && isset($event->supervisor)) {
            $supervisorName = isset($event->supervisor->name) ? (string) $event->supervisor->name : 'default';
        }
        return array(
            'event_type' => 'SupervisorLooped',
            'job_id' => '',
            'queue' => $supervisorName,
            'status' => 'looped',
        );
    }

    public static function fromQueuePaused(object $event): array {
        $connection = property_exists($event, 'connectionName') ? $event->connectionName : 'redis';
        $queue = property_exists($event, 'queue') ? $event->queue : 'default';
        return [
            'event_type' => 'QueuePaused',
            'job_id' => '',
            'queue' => $connection . '.' . $queue,
            'status' => 'paused',
        ];
    }

    public static function fromQueueResumed(object $event): array {
        $connection = property_exists($event, 'connectionName') ? $event->connectionName : 'redis';
        $queue = property_exists($event, 'queue') ? $event->queue : 'default';
        return [
            'event_type' => 'QueueResumed',
            'job_id' => '',
            'queue' => $connection . '.' . $queue,
            'status' => 'resumed',
        ];
    }

    private static function jobFromEvent(object $event): ?object {
        return property_exists($event, 'job') ? $event->job : null;
    }

    private static function baseJobPayload(object $event, ?object $job, string $eventType, string $status): array {
        $queue = 'redis.default';
        $attempts = 0;
        $jobId = '';
        $payload = [];

        if ($job !== null) {
            if (method_exists($job, 'getQueue')) {
                $q = $job->getQueue();
                $conn = property_exists($event, 'connectionName') ? $event->connectionName : 'redis';
                $queue = $conn . '.' . ($q ?: 'default');
            }
            if (method_exists($job, 'attempts')) {
                $attempts = (int) $job->attempts();
            }
            if (method_exists($job, 'getJobId')) {
                $jobId = (string) $job->getJobId();
            }
            if (method_exists($job, 'payload')) {
                $payload = $job->payload();
                if (is_string($payload)) {
                    $payload = json_decode($payload, true) ?: [];
                }
                if (isset($payload['uuid'])) {
                    $jobId = (string) $payload['uuid'];
                }
            }
        }

        if ($jobId === '') {
            $jobId = Str::uuid()->toString();
        }

        $displayName = $payload['displayName'] ?? $payload['job'] ?? null;
        $name = is_string($displayName) ? $displayName : (is_array($displayName) ? ($displayName['displayName'] ?? null) : null);

        $result = [
            'event_type' => $eventType,
            'job_id' => $jobId,
            'queue' => $queue,
            'status' => $status,
            'attempts' => $attempts,
            'name' => $name,
            'payload' => $payload,
        ];
        $queuedAt = static::queuedAtFromPayload($payload);
        if ($queuedAt !== null) {
            $result['queued_at'] = $queuedAt;
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $payload Laravel job payload (decoded)
     */
    private static function queuedAtFromPayload(array $payload): ?string {
        $createdAt = isset($payload['created_at']) ? (int) $payload['created_at'] : null;
        if ($createdAt > 0) {
            return date('c', $createdAt);
        }
        $availableAt = isset($payload['available_at']) ? (int) $payload['available_at'] : null;
        if ($availableAt > 0) {
            return date('c', $availableAt);
        }
        return null;
    }
}
