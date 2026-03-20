<?php

namespace App\Services;

use App\Models\Service;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HorizonApiProxyService {

    /**
     * Retry a job through the Horizon HTTP API.
     *
     * @param Service $service
     * @param string $jobUuid
     * @return array{success: bool, message?: string, status?: int}
     */
    public function retryJob(Service $service, string $jobUuid): array {
        $relativePath = $this->private__parseTemplate('horizonhub.horizon_paths.retry', '{id}', $jobUuid);

        return $this->private__callWithDashboardSession($service, $relativePath, 'post');
    }

    /**
     * Test connectivity with the Horizon HTTP API for a service.
     *
     * @param Service $service
     * @return array{success: bool, message?: string, status?: int}
     */
    public function ping(Service $service): array {
        $relativePath = (string) \config('horizonhub.horizon_paths.ping');

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * Get the queue workload from the Horizon HTTP API for a service.
     *
     * @param Service $service
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getWorkload(Service $service): array {
        $relativePath = (string) \config('horizonhub.horizon_paths.workload');

        $result = $this->private__call($service, $relativePath, 'get');
        if (! ($result['success'] ?? false) && \in_array($result['status'] ?? 0, [401, 403], true)) {
            $result = $this->private__callWithDashboardSession($service, $relativePath, 'get');
        }

        return $result;
    }

    /**
     * Get high-level dashboard statistics (including jobs per minute and recent jobs)
     * from the Horizon HTTP API for a service.
     *
     * This proxies the Horizon `/stats` endpoint and returns its decoded payload.
     *
     * @param Service $service
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getStats(Service $service): array {
        $relativePath = (string) \config('horizonhub.horizon_paths.ping');

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * Get Horizon masters (and their supervisors) from the Horizon HTTP API for a service.
     *
     * @param Service $service
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getMasters(Service $service): array {
        $relativePath = (string) \config('horizonhub.horizon_paths.masters');

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * Get failed jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service
     * @param array<string, mixed> $query
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getFailedJobs(Service $service, array $query = []): array {
        $path = (string) \config('horizonhub.horizon_paths.failed_jobs');
        if ($query === []) {
            $query = ['starting_at' => 0, 'limit' => 50];
        }
        $queryString = \http_build_query($query);

        return $this->private__call($service, "$path?$queryString", 'get');
    }

    /**
     * Get a single failed job by UUID from the Horizon HTTP API for a service.
     *
     * @param Service $service
     * @param string $jobUuid
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getFailedJob(Service $service, string $jobUuid): array {
        $relativePath = $this->private__parseTemplate('horizonhub.horizon_paths.failed_job', '{id}', $jobUuid);

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * Get completed jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service
     * @param array<string, mixed> $query
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getCompletedJobs(Service $service, array $query = []): array {
        $path = (string) \config('horizonhub.horizon_paths.completed_jobs');
        if ($query === []) {
            $query = ['starting_at' => 0, 'limit' => 50];
        }
        $queryString = \http_build_query($query);

        return $this->private__call($service, "$path?$queryString", 'get');
    }

    /**
     * Get pending/processing jobs from the Horizon HTTP API for a service.
     *
     * @param Service $service
     * @param array<string, mixed> $query
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getPendingJobs(Service $service, array $query = []): array {
        $path = (string) \config('horizonhub.horizon_paths.pending_jobs');
        if ($query === []) {
            $query = ['starting_at' => 0, 'limit' => 50];
        }
        $queryString = \http_build_query($query);

        return $this->private__call($service, "$path?$queryString", 'get');
    }

    /**
     * Get queue metrics from the Horizon HTTP API for a service (queue sizes, etc.).
     *
     * @param Service $service
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getMetricsQueues(Service $service): array {
        $relativePath = (string) \config('horizonhub.horizon_paths.metrics_queues', '/metrics/queues');

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * @param Service $service
     * @return string
     * @throws \RuntimeException
     */
    private function private__getServiceBase(Service $service): string {
        $serviceBase = \rtrim($service->base_url ?? '', '/');
        if ($serviceBase === '') {
            throw new \RuntimeException('Service has no base_url configured.');
        }
        return $serviceBase;
    }

    /**
     * Build the base URL for the Horizon API of a service.
     *
     * @param Service $service
     * @return string
     */
    private function private__buildBaseUrl(Service $service): string {
        $apiBasePath = (string) \config('horizonhub.horizon_paths.api');
        $apiBasePath = '/' . \ltrim($apiBasePath, '/');

        return $this->private__getServiceBase($service) . \rtrim($apiBasePath, '/');
    }

    /**
     * Build the URL for the Horizon dashboard of a service.
     *
     * @param Service $service
     * @return string
     */
    private function private__buildDashboardUrl(Service $service): string {
        $dashboardPath = (string) \config('horizonhub.horizon_paths.dashboard');
        $dashboardPath = \ltrim($dashboardPath, '/');

        return $this->private__getServiceBase($service) . '/' . $dashboardPath;
    }

    /**
     * Parse a template path replacing a placeholder with a value.
     *
     * @param string $templateKey
     * @param string $placeholder
     * @param mixed $value
     * @return string
     */
    private function private__parseTemplate(string $templateKey, string $placeholder, mixed $value): string {
        $template = (string) \config($templateKey);
        if ($template === '') {
            throw new \RuntimeException("Invalid configuration: {$templateKey} is empty.");
        }
        if (\strpos($template, $placeholder) === false) {
            throw new \RuntimeException("Invalid configuration: {$templateKey} must contain \"{$placeholder}\" placeholder.");
        }

        return \str_replace($placeholder, $value, $template);
    }

    /**
     * @param string $rawBody
     * @return array<string, mixed>|null
     */
    private function private__parseResponseBody(string $rawBody): ?array {
        if ($rawBody === '') {
            return null;
        }
        $decoded = \json_decode($rawBody, true);
        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @param Response $response
     * @return string
     */
    private function private__buildErrorMessageFromResponse(Response $response): string {
        $rawBody = $response->body();
        $decoded = $this->private__parseResponseBody($rawBody);

        if (\is_array($decoded) && isset($decoded['message']) && (string) $decoded['message'] !== '') {
            return (string) $decoded['message'];
        }

        $trimmedBody = \trim((string) $rawBody);
        $isHtml = $trimmedBody !== \strip_tags($trimmedBody);
        if (! $isHtml && $trimmedBody !== '') {
            return \mb_substr($trimmedBody, 0, 200);
        }

        return "Horizon API returned an HTTP error ({$response->status()}).";
    }

    /**
     * @param Response $response
     * @param Service $service
     * @param string $url
     * @param bool $updateHeartbeat
     * @param string $logContext
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    private function private__processHttpResponse(
        Response $response,
        Service $service,
        string $url,
        bool $updateHeartbeat = false,
        string $logContext = '',
    ): array {
        if ($response->successful()) {
            $data = $this->private__parseResponseBody($response->body());
            if ($updateHeartbeat) {
                $service->forceFill([
                    'last_seen_at' => \now(),
                    'status' => 'online',
                ])->saveQuietly();
            }
            return $data === null
                ? ['success' => true]
                : ['success' => true, 'data' => $data];
        }

        $message = $this->private__buildErrorMessageFromResponse($response);
        Log::warning("Horizon Hub: Horizon API call failed $logContext", [
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
    }

    /**
     * Call the Horizon HTTP API for a given service.
     *
     * @param Service $service
     * @param string $path
     * @param string $method
     * @return array{success: bool, message?: string, status?: int}
     */
    private function private__call(Service $service, string $path, string $method = 'post'): array {
        try {
            $base = $this->private__buildBaseUrl($service);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 400,
            ];
        }

        $url = "$base/" . \ltrim($path, '/');

        try {
            $request = Http::timeout(\config('horizonhub.api_timeout'));
            $response = match (\strtolower($method)) {
                'get' => $request->get($url),
                'delete' => $request->delete($url),
                default => $request->post($url),
            };
            return $this->private__processHttpResponse($response, $service, $url, true);
        } catch (\Throwable $e) {
            Log::error('Horizon Hub: Horizon API call exception', [
                'service_id' => $service->id ?? null,
                'url' => $url ?? null,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 502,
            ];
        }
    }

    /**
     * Bootstrap a Horizon dashboard session and CSRF token.
     *
     * This simulates a browser visiting the Horizon dashboard to obtain
     * a session and CSRF token that can be used to call Horizon's API
     * endpoints protected by CSRF.
     *
     * @param Service $service
     * @return array{csrf_token: string, cookies: CookieJar}|null
     */
    private function private__bootstrapDashboardSession(Service $service): ?array {
        try {
            $dashboardUrl = $this->private__buildDashboardUrl($service);
        } catch (\Throwable $e) {
            Log::warning('Horizon Hub: failed to build Horizon dashboard URL', [
                'service_id' => $service->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $cookieJar = new CookieJar();

        try {
            $response = Http::timeout(10)
                ->withOptions(['cookies' => $cookieJar])
                ->get($dashboardUrl);
        } catch (\Throwable $e) {
            Log::warning('Horizon Hub: failed to bootstrap Horizon dashboard session', [
                'service_id' => $service->id ?? null,
                'url' => $dashboardUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (! $response->ok()) {
            Log::warning('Horizon Hub: unexpected status when bootstrapping Horizon dashboard session', [
                'service_id' => $service->id ?? null,
                'url' => $dashboardUrl,
                'status' => $response->status(),
            ]);

            return null;
        }

        $html = $response->body();
        $matches = [];

        if (! \preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/', $html, $matches)) {
            Log::warning('Horizon Hub: unable to extract CSRF token from Horizon dashboard', [
                'service_id' => $service->id ?? null,
                'url' => $dashboardUrl,
            ]);

            return null;
        }

        $csrfToken = (string) ($matches[1] ?? '');
        if ($csrfToken === '') {
            Log::warning('Horizon Hub: empty CSRF token extracted from Horizon dashboard', [
                'service_id' => $service->id ?? null,
                'url' => $dashboardUrl,
            ]);

            return null;
        }

        return [
            'csrf_token' => $csrfToken,
            'cookies' => $cookieJar,
        ];
    }

    /**
     * Call the Horizon HTTP API for a given service using a dashboard session and CSRF token.
     *
     * This is intended for write operations (such as retrying jobs) that are
     * protected by Laravel's CSRF middleware on the service.
     *
     * If a 419 status is returned, a single automatic re-bootstrap and retry
     * is performed to refresh the session/CSRF token.
     *
     * @param Service $service
     * @param string $path
     * @param string $method
     * @return array{success: bool, message?: string, status?: int}
     */
    private function private__callWithDashboardSession(Service $service, string $path, string $method = 'post'): array {
        try {
            $base = $this->private__buildBaseUrl($service);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 400,
            ];
        }

        $url = "$base/" . \ltrim($path, '/');

        $attempt = function () use ($service, $url, $method): ?Response {
            $bootstrap = $this->private__bootstrapDashboardSession($service);
            if ($bootstrap === null) {
                return null;
            }
            $request = Http::timeout(10)
                ->withOptions(['cookies' => $bootstrap['cookies']])
                ->withHeaders(['X-CSRF-TOKEN' => $bootstrap['csrf_token']]);
            return match (\strtolower($method)) {
                'get' => $request->get($url),
                'delete' => $request->delete($url),
                default => $request->post($url),
            };
        };

        try {
            $response = $attempt();
            if ($response === null) {
                return [
                    'success' => false,
                    'message' => 'Unable to bootstrap Horizon dashboard session or CSRF token.',
                    'status' => 502,
                ];
            }
            if ($response->status() === 419) {
                $retryResponse = $attempt();
                if ($retryResponse !== null) {
                    $response = $retryResponse;
                }
            }
            return $this->private__processHttpResponse($response, $service, $url, false, ' (with dashboard session)');
        } catch (\Throwable $e) {
            Log::error('Horizon Hub: Horizon API call exception (with dashboard session)', [
                'service_id' => $service->id ?? null,
                'url' => $url ?? null,
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
