<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Facades\Http;

class AgentProxyService {
    /**
     * Retry a job.
     *
     * @param Service $service
     * @param string $jobUuid
     * @return array
     */
    public function retryJob(Service $service, string $jobUuid): array {
        $path = \Str::replace('{id}', $jobUuid, \config('horizonhub.agent.retry_path'));
        return $this->callAgent($service, $path, 'post');
    }

    /**
     * Delete a job.
     *
     * @param Service $service
     * @param string $jobUuid
     * @return array
     */
    public function deleteJob(Service $service, string $jobUuid): array {
        $path = \Str::replace('{id}', $jobUuid, \config('horizonhub.agent.delete_path'));
        return $this->callAgent($service, $path, 'delete');
    }

    /**
     * Pause a queue.
     *
     * @param Service $service
     * @param string $queueName
     * @return array
     */
    public function pauseQueue(Service $service, string $queueName): array {
        $path = \Str::replace('{name}', $queueName, \config('horizonhub.agent.pause_path'));
        return $this->callAgent($service, $path, 'post');
    }

    /**
     * Resume a queue.
     *
     * @param Service $service
     * @param string $queueName
     * @return array
     */
    public function resumeQueue(Service $service, string $queueName): array {
        $path = \Str::replace('{name}', $queueName, \config('horizonhub.agent.resume_path'));
        return $this->callAgent($service, $path, 'post');
    }

    /**
     * Call the agent.
     *
     * @param Service $service
     * @param string $path
     * @param string $method
     * @return array
     */
    private function callAgent(Service $service, string $path, string $method): array {
        $baseUrl = \rtrim($service->base_url ?? '', '/');
        if ($baseUrl === '') {
            return ['success' => false, 'message' => 'Service has no base_url', 'status' => 400];
        }

        $url = $baseUrl . $path;
        $timestamp = (string) \time();
        $body = '';
        $signature = 'sha256=' . \hash_hmac('sha256', "$timestamp.$body", $service->api_key);

        try {
            $request = Http::timeout(10)
                ->withHeaders([
                    'X-Api-Key' => $service->api_key,
                    'X-Hub-Timestamp' => $timestamp,
                    'X-Hub-Signature' => $signature,
                ])
                ->withBody($body, 'text/plain');

            $response = match (\Str::lower($method)) {
                'delete' => $request->delete($url),
                default => $request->post($url),
            };

            if ($response->successful()) {
                return ['success' => true];
            }

            $service->update(['status' => 'offline']);

            $rawBody = $response->body();
            $message = 'Agent error';

            $decoded = null;
            if ($rawBody !== '' && $rawBody !== null) {
                $decoded = \json_decode($rawBody, true);
            }

            if (\is_array($decoded) && \isset($decoded['message']) && (string) $decoded['message'] !== '') {
                $message = (string) $decoded['message'];
            } else {
                $trimmedBody = \trim((string) $rawBody);
                $isHtml = $trimmedBody !== \strip_tags($trimmedBody);
                if (! $isHtml && $trimmedBody !== '') {
                    $message = \mb_substr($trimmedBody, 0, 200);
                } else {
                    $message = "Agent returned an HTTP error ({$response->status()}).";
                }
            }

            return [
                'success' => false,
                'message' => $message,
                'status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            $service->update(['status' => 'offline']);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 502,
            ];
        }
    }
}
