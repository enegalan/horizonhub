<?php

namespace HorizonHub\Agent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;

class HorizonHubActionController {
    /**
     * Retry a job.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function retry(string $id): JsonResponse {
        try {
            Artisan::call('queue:retry', ['id' => $id]);
            $output = trim(Artisan::output());
            if (str_contains($output, 'retried') || str_contains($output, 'The failed job')) {
                return response()->json(['message' => 'Job retry dispatched']);
            }
            return response()->json(['message' => $output ?: 'Retry attempted'], 400);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a job.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function delete(string $id): JsonResponse {
        try {
            Artisan::call('queue:forget', ['id' => $id]);
            return response()->json(['message' => 'Job removed from failed queue']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Pause a queue.
     *
     * @param Request $request
     * @param string $name
     * @return JsonResponse
     */
    public function pause(Request $request, string $name): JsonResponse {
        [$connection, $queue] = $this->parseQueueName($name);
        try {
            Queue::pause($connection, $queue);
            return response()->json(['message' => 'Queue paused']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Resume a queue.
     *
     * @param Request $request
     * @param string $name
     * @return JsonResponse
     */
    public function resume(Request $request, string $name): JsonResponse {
        [$connection, $queue] = $this->parseQueueName($name);
        try {
            Queue::resume($connection, $queue);
            return response()->json(['message' => 'Queue resumed']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse the queue name.
     *
     * @param string $name
     * @return array
     */
    private function parseQueueName(string $name): array {
        if (str_contains($name, '.')) {
            $parts = explode('.', $name, 2);
            return [$parts[0], $parts[1]];
        }
        return ['redis', $name ?: 'default'];
    }
}
