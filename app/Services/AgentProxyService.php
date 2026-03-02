<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AgentProxyService {
    public function retryJob(Service $service, string $jobUuid): array {
        $path = Str::replace('{id}', $jobUuid, config('horizon_hub.agent.retry_path', '/horizon-hub/jobs/{id}/retry'));
        return $this->callAgent($service, $path, 'post');
    }

    public function deleteJob(Service $service, string $jobUuid): array {
        $path = Str::replace('{id}', $jobUuid, config('horizon_hub.agent.delete_path', '/horizon-hub/jobs/{id}/delete'));
        return $this->callAgent($service, $path, 'delete');
    }

    public function pauseQueue(Service $service, string $queueName): array {
        $path = Str::replace('{name}', $queueName, config('horizon_hub.agent.pause_path', '/horizon-hub/queues/{name}/pause'));
        return $this->callAgent($service, $path, 'post');
    }

    public function resumeQueue(Service $service, string $queueName): array {
        $path = Str::replace('{name}', $queueName, config('horizon_hub.agent.resume_path', '/horizon-hub/queues/{name}/resume'));
        return $this->callAgent($service, $path, 'post');
    }

    private function callAgent(Service $service, string $path, string $method): array {
        $baseUrl = rtrim($service->base_url ?? '', '/');
        if ($baseUrl === '') {
            return ['success' => false, 'message' => 'Service has no base_url', 'status' => 400];
        }

        $url = $baseUrl . $path;
        $timestamp = (string) time();
        $body = '';
        $signature = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $service->api_key);

        try {
            $request = Http::timeout(10)
                ->withHeaders([
                    'X-Api-Key' => $service->api_key,
                    'X-Hub-Timestamp' => $timestamp,
                    'X-Hub-Signature' => $signature,
                ])
                ->withBody($body, 'text/plain');

            $response = match (Str::lower($method)) {
                'delete' => $request->delete($url),
                default => $request->post($url),
            };

            if ($response->successful()) {
                return ['success' => true];
            }

            $service->update(['status' => 'offline']);
            return [
                'success' => false,
                'message' => $response->body() ?: 'Agent error',
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
