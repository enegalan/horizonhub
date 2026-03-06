<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AgentProxyService {
    private function parseTemplate(string $templateKey, string $placeholder, mixed $value): string {
        $template = (string) \config($templateKey);
        if (empty($template)) {
            throw new \RuntimeException("Invalid configuration: {$templateKey} is empty.");
        }
        if (\strpos($template, $placeholder) === false) {
            throw new \RuntimeException("Invalid configuration: {$templateKey} must contain \"{$placeholder}\" placeholder.");
        }
        return \Str::replace($placeholder, $value, $template);
    }
    /**
     * Retry a job.
     *
     * @param Service $service
     * @param string $jobUuid
     * @return array
     */
    public function retryJob(Service $service, string $jobUuid): array {
        $path = $this->parseTemplate('horizonhub.agent.retry_path', '{id}', $jobUuid);
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
        $path = $this->parseTemplate('horizonhub.agent.delete_path', '{id}', $jobUuid);
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
        $path = $this->parseTemplate('horizonhub.agent.pause_path', '{name}', $queueName);
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
        $path = $this->parseTemplate('horizonhub.agent.resume_path', '{name}', $queueName);
        return $this->callAgent($service, $path, 'post');
    }

    /**
     * Call the agent.
     *
     * The hub authenticates with the agent using the service API key and a
     * signed payload. The agent must validate:
     * - X-Api-Key header matches its configured API key
     * - X-Hub-Timestamp is recent enough
     * - X-Hub-Signature is a sha256 HMAC of "<timestamp>.<body>" with the API key
     *
     * @param Service $service
     * @param string $path
     * @param string $method
     * @return array{success: bool, message?: string, status?: int}
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

            if (\is_array($decoded) && isset($decoded['message']) && (string) $decoded['message'] !== '') {
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

            Log::warning('Horizon Hub: agent call failed', [
                'service_id' => $service->id,
                'url' => $url,
                'status' => $response->status(),
                'message' => $message,
            ]);

            return [
                'success' => false,
                'message' => $message,
                'status' => $response->status(),
            ];
        } catch (\Throwable $e) {
            $service->update(['status' => 'offline']);
            Log::error('Horizon Hub: agent call exception', [
                'service_id' => $service->id,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 502,
            ];
        }
    }
}
