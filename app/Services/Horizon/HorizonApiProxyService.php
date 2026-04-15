<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Support\ConfigHelper;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HorizonApiProxyService
{
    /**
     * Retry a job through the Horizon HTTP API.
     *
     * @return array{success: bool, message?: string, status?: int}
     */
    public function retryJob(Service $service, string $jobUuid): array
    {
        $relativePath = ConfigHelper::getParsedTpl('horizonhub.horizon_paths.retry', ['{id}' => $jobUuid]);

        return $this->private__call($service, $relativePath, 'post', true);
    }

    /**
     * Test connectivity with the Horizon HTTP API for a service.
     *
     * @return array{success: bool, message?: string, status?: int}
     */
    public function ping(Service $service): array
    {
        $relativePath = (string) ConfigHelper::get('horizonhub.horizon_paths.ping');

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * Get the queue workload from the Horizon HTTP API for a service.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getWorkload(Service $service): array
    {
        $relativePath = (string) ConfigHelper::get('horizonhub.horizon_paths.workload');

        $result = $this->private__call($service, $relativePath, 'get');
        if (! ($result['success'] ?? false) && \in_array($result['status'] ?? 0, [401, 403], true)) {
            $result = $this->private__call($service, $relativePath, 'get', true);
        }

        return $result;
    }

    /**
     * Get high-level dashboard statistics (including jobs per minute and recent jobs)
     * from the Horizon HTTP API for a service.
     *
     * This proxies the Horizon `/stats` endpoint and returns its decoded payload.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getStats(Service $service): array
    {
        $relativePath = (string) ConfigHelper::get('horizonhub.horizon_paths.ping');

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * Get Horizon masters (and their supervisors) from the Horizon HTTP API for a service.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getMasters(Service $service): array
    {
        $relativePath = (string) ConfigHelper::get('horizonhub.horizon_paths.masters');

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * Get failed jobs from the Horizon HTTP API for a service.
     *
     * @param  array<string, mixed>  $query
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getFailedJobs(Service $service, array $query = []): array
    {
        $path = (string) ConfigHelper::get('horizonhub.horizon_paths.failed_jobs');
        $query = \array_merge(
            ['starting_at' => 0, 'limit' => ConfigHelper::getIntWithMin('horizonhub.horizon_api_job_list_page_size', 1)],
            $query,
        );
        $queryString = \http_build_query($query);

        return $this->private__call($service, "$path?$queryString", 'get');
    }

    /**
     * Get a single job by UUID from the Horizon HTTP API for a service.
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getJob(Service $service, string $jobUuid): array
    {
        $relativePath = ConfigHelper::getParsedTpl('horizonhub.horizon_paths.job', ['{id}' => $jobUuid]);

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * Get completed jobs from the Horizon HTTP API for a service.
     *
     * @param  array<string, mixed>  $query
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getCompletedJobs(Service $service, array $query = []): array
    {
        $path = (string) ConfigHelper::get('horizonhub.horizon_paths.completed_jobs');
        $query = \array_merge(
            ['starting_at' => 0, 'limit' => ConfigHelper::getIntWithMin('horizonhub.horizon_api_job_list_page_size', 1)],
            $query,
        );
        $queryString = \http_build_query($query);

        return $this->private__call($service, "$path?$queryString", 'get');
    }

    /**
     * Get pending/processing jobs from the Horizon HTTP API for a service.
     *
     * @param  array<string, mixed>  $query
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getPendingJobs(Service $service, array $query = []): array
    {
        $path = (string) ConfigHelper::get('horizonhub.horizon_paths.pending_jobs');
        $query = \array_merge(
            ['starting_at' => 0, 'limit' => ConfigHelper::getIntWithMin('horizonhub.horizon_api_job_list_page_size', 1)],
            $query,
        );
        $queryString = \http_build_query($query);

        return $this->private__call($service, "$path?$queryString", 'get');
    }

    /**
     * Get queue metrics from the Horizon HTTP API for a service (queue sizes, etc.).
     *
     * @return array{success: bool, message?: string, status?: int, data?: array}
     */
    public function getMetricsQueues(Service $service): array
    {
        $relativePath = (string) ConfigHelper::get('horizonhub.horizon_paths.metrics_queues');

        return $this->private__call($service, $relativePath, 'get');
    }

    /**
     * @throws \RuntimeException
     */
    private function private__getServiceBase(Service $service): string
    {
        $serviceBase = \rtrim($service->base_url ?? '', '/');
        if ($serviceBase === '') {
            throw new \RuntimeException('Service has no base_url configured.');
        }

        return $serviceBase;
    }

    /**
     * Build the base URL for the Horizon API of a service.
     */
    private function private__buildBaseUrl(Service $service): string
    {
        $apiBasePath = (string) ConfigHelper::get('horizonhub.horizon_paths.api');
        $apiBasePath = '/'.\ltrim($apiBasePath, '/');

        return $this->private__getServiceBase($service).\rtrim($apiBasePath, '/');
    }

    /**
     * Build the URL for the Horizon dashboard of a service.
     */
    private function private__buildDashboardUrl(Service $service): string
    {
        $dashboardPath = (string) ConfigHelper::get('horizonhub.horizon_paths.dashboard');
        $dashboardPath = \ltrim($dashboardPath, '/');

        return $this->private__getServiceBase($service).'/'.$dashboardPath;
    }

    /**
     * Call the Horizon HTTP API for a given service.
     *
     * @param  bool  $withDashboardSession  When true, bootstrap Horizon dashboard session and CSRF token.
     * @return array{success: bool, message?: string, status?: int}
     */
    private function private__call(Service $service, string $path, string $method = 'post', bool $withDashboardSession = false): array
    {
        try {
            $base = $this->private__buildBaseUrl($service);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 400,
            ];
        }

        $url = "$base/".\ltrim($path, '/');

        $httpMethod = \strtolower($method);
        $attempt = function () use ($service, $url, $httpMethod, $withDashboardSession): ?Response {
            $request = $this->private__newHorizonPendingRequest($httpMethod);

            if ($withDashboardSession) {
                $bootstrap = $this->private__bootstrapDashboardSession($service);
                if ($bootstrap === null) {
                    return null;
                }
                $request = $request
                    ->withOptions(['cookies' => $bootstrap['cookies']])
                    ->withHeaders(['X-CSRF-TOKEN' => $bootstrap['csrf_token']]);
            }

            return match ($httpMethod) {
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
            if ($withDashboardSession && $response->status() === 419) {
                $retryResponse = $attempt();
                if ($retryResponse !== null) {
                    $response = $retryResponse;
                }
            }

            return $this->private__processHttpResponse(
                $response,
                $service,
                $url,
                ! $withDashboardSession,
                $withDashboardSession ? ' (with dashboard session)' : '',
            );
        } catch (\Throwable $e) {
            Log::error('Horizon Hub: Horizon API call exception'.($withDashboardSession ? ' (with dashboard session)' : ''), [
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
     * @return array{csrf_token: string, cookies: CookieJar}|null
     */
    private function private__bootstrapDashboardSession(Service $service): ?array
    {
        try {
            $dashboardUrl = $this->private__buildDashboardUrl($service);
        } catch (\Throwable $e) {
            Log::warning('Horizon Hub: failed to build Horizon dashboard URL', [
                'service_id' => $service->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $cookieJar = new CookieJar;

        try {
            $response = $this->private__newHorizonPendingRequest()
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
     * Build an HTTP client for Horizon calls with unified timeout and optional GET retries.
     *
     * @param  string  $httpMethod  Uppercase or lowercase HTTP method.
     */
    private function private__newHorizonPendingRequest(string $httpMethod = 'get'): PendingRequest
    {
        $httpMethod = \strtoupper($httpMethod);
        $timeoutSeconds = ConfigHelper::getIntWithMin('horizonhub.api_timeout', 10);
        $request = Http::timeout($timeoutSeconds);

        $connectTimeout = ConfigHelper::get('horizonhub.horizon_http_connect_timeout');
        if ($connectTimeout !== null && (float) $connectTimeout > 0) {
            $request->connectTimeout((float) $connectTimeout);
        }

        $retryConfig = ConfigHelper::get('horizonhub.horizon_http_retry');
        $retryTimes = (int) $retryConfig['times'];
        $sleepBaseMs = (int) $retryConfig['sleep_ms'];
        $retryOnStatus = $retryConfig['retry_on_status'];

        if ($retryConfig && $httpMethod === 'GET' && $retryTimes > 1) {
            $request = $request->retry(
                $retryTimes,
                function (int $attempt, \Throwable $e) use ($sleepBaseMs): int {
                    return $sleepBaseMs * (2 ** \max(0, $attempt - 1));
                },
                function (\Throwable $exception, PendingRequest $pending, ?string $method = 'GET') use ($retryOnStatus): bool {
                    if ($method !== null && \strtoupper($method) !== 'GET') {
                        return false;
                    }

                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException && $exception->response !== null) {
                        return \in_array($exception->response->status(), $retryOnStatus, true);
                    }

                    return false;
                },
                throw: false,
            );
        }

        return $request;
    }

    /**
     * Process the HTTP response.
     *
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
            $data = \json_decode($response->body(), true);
            if ($updateHeartbeat) {
                $service->forceFill([
                    'last_seen_at' => \now(),
                    'status' => 'online',
                ])->saveQuietly();
            }

            return ! \is_array($data)
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
     * Build an error message from a response.
     */
    private function private__buildErrorMessageFromResponse(Response $response): string
    {
        $rawBody = $response->body();
        $decoded = \json_decode($rawBody, true);

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
}
