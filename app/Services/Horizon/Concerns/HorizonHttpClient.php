<?php

namespace App\Services\Horizon\Concerns;

use App\Models\Service;
use App\Services\Horizon\Contracts\HorizonClientCache as HorizonClientCacheContract;
use App\Services\Horizon\Contracts\HorizonHttpClient as HorizonHttpClientContract;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HorizonHttpClient implements HorizonHttpClientContract
{
    /**
     * The cache instance.
     */
    private HorizonClientCacheContract $cache;

    /**
     * The constructor.
     *
     * @param HorizonClientCacheContract $cache The cache instance.
     */
    public function __construct(HorizonClientCacheContract $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Call the Horizon HTTP API for a service.
     *
     * @param Service $service The service instance.
     * @param string $path The path.
     * @param string $method The method.
     * @param bool $withDashboardSession Whether to include the dashboard session.
     * @param bool $bypassFailureCooldown Whether to bypass the failure cooldown.
     * @param bool $allowWhenDisabled Whether to allow when disabled.
     *
     * @return array The response data.
     */
    public function call(
        Service $service,
        string $path,
        string $method = 'post',
        bool $withDashboardSession = false,
        bool $bypassFailureCooldown = false,
        bool $allowWhenDisabled = false,
    ): array {
        if (! $service->enabled && ! $allowWhenDisabled) {
            return [
                'success' => false,
                'message' => 'Service is disabled.',
                'status' => 503,
            ];
        }

        if (! $bypassFailureCooldown && $this->cache->hasFailureCooldown($service)) {
            return [
                'success' => false,
                'message' => 'Service temporarily in cooldown after recent upstream failures.',
                'status' => 503,
            ];
        }

        $base = $service->getBaseUrl() . (string) config('horizonhub.horizon_paths.api');

        $url = "$base/" . \ltrim($path, '/');

        $httpMethod = \strtolower($method);

        $shouldCache = $httpMethod === 'get' && ! $withDashboardSession && ! $allowWhenDisabled;

        if ($shouldCache) {
            $cached = $this->cache->getRequestPathCache($service, $path);

            if (empty($cached)) {
                $lock = $this->cache->requestPathFillLock($service, $path);

                try {
                    $lock->block((int) config('horizonhub.api_timeout'));
                    $cached = $this->cache->getRequestPathCache($service, $path);
                } finally {
                    $lock->release();
                }
            }

            if ($cached !== null) {
                if (config('app.debug')) {
                    Log::debug(config('app.name') . ': Horizon API call (cache hit)', [
                        'service_id' => $service->id ?? null,
                        'service_name' => $service->name ?? null,
                        'url' => $url,
                        'http_method' => $httpMethod,
                        'with_dashboard_session' => $withDashboardSession,
                        'allow_when_disabled' => $allowWhenDisabled,
                    ]);
                }

                return $cached;
            }
        }

        if (config('app.debug')) {
            Log::debug(config('app.name') . ': Horizon API call', [
                'service_id' => $service->id ?? null,
                'service_name' => $service->name ?? null,
                'url' => $url,
                'http_method' => $httpMethod,
                'with_dashboard_session' => $withDashboardSession,
                'allow_when_disabled' => $allowWhenDisabled,
            ]);
        }

        $attempt = function () use ($service, $url, $httpMethod, $withDashboardSession): ?Response {
            $request = $this->private__newHorizonPendingRequest($httpMethod, $service);

            if ($withDashboardSession) {
                $bootstrap = $this->private__bootstrapDashboardSession($service);

                if (empty($bootstrap)) {
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

            $result = $this->private__processHttpResponse(
                $response,
                $service,
                $url,
                ! $withDashboardSession,
                $withDashboardSession ? ' (with dashboard session)' : '',
            );

            if ($result['success'] === true) {
                $this->cache->forgetFailureCooldown($service);

                if ($shouldCache) {
                    $this->cache->putRequestPathCache($service, $path, $result);
                }

                return $result;
            }

            if (! \in_array($result['status'], config('horizonhub.horizon_http_auth_statuses'), true)) {
                $this->cache->putFailureCooldown($service);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error(config('app.name') . ': Horizon API call exception' . ($withDashboardSession ? ' (with dashboard session)' : ''), [
                'service_id' => $service->id ?? null,
                'url' => $url,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            $this->cache->putFailureCooldown($service);

            $statusCode = $e->getCode();

            // Default exceptions have status code 0, we want to separate network errors from other exceptions and return the appropiate status code
            if ($statusCode === 0) {
                $statusCode = $e instanceof ConnectionException
                    || $e instanceof RequestException
                    || $e instanceof GuzzleException
                    ? 502
                    : 500;
            }

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => $statusCode,
            ];
        }
    }

    /**
     * Bootstrap the dashboard session.
     *
     * @param Service $service The service instance.
     *
     * @return array|null The response data.
     */
    private function private__bootstrapDashboardSession(Service $service): ?array
    {
        $dashboardUrl = $service->getBaseUrl() . (string) config('horizonhub.horizon_paths.dashboard');

        $cookieJar = new CookieJar;

        try {
            $response = $this->private__newHorizonPendingRequest('get', $service)
                ->withOptions(['cookies' => $cookieJar])
                ->get($dashboardUrl);
        } catch (\Throwable $e) {
            Log::warning(config('app.name') . ': failed to bootstrap Horizon dashboard session', [
                'service_id' => $service->id ?? null,
                'url' => $dashboardUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->ok()) {
            Log::warning(config('app.name') . ': unexpected status when bootstrapping Horizon dashboard session', [
                'service_id' => $service->id ?? null,
                'url' => $dashboardUrl,
                'status' => $response->status(),
            ]);

            return null;
        }

        $html = $response->body();
        $matches = [];

        if (! \preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/', $html, $matches)) {
            Log::warning(config('app.name') . ': unable to extract CSRF token from Horizon dashboard', [
                'service_id' => $service->id ?? null,
                'url' => $dashboardUrl,
            ]);

            return null;
        }

        $csrfToken = (string) $matches[1];

        return [
            'csrf_token' => $csrfToken,
            'cookies' => $cookieJar,
        ];
    }

    /**
     * Build the error message from the response.
     *
     * @param Response $response The response.
     *
     * @return string The error message.
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

    /**
     * Create a new Horizon pending request.
     *
     * @param string $httpMethod The HTTP method.
     * @param Service|null $service The service instance.
     *
     * @return PendingRequest The pending request.
     */
    private function private__newHorizonPendingRequest(string $httpMethod, ?Service $service = null): PendingRequest
    {
        $httpMethod = \strtoupper($httpMethod);
        $timeoutSeconds = (int) config('horizonhub.api_timeout');
        $request = Http::timeout($timeoutSeconds);

        $connectTimeout = config('horizonhub.horizon_http_connect_timeout');

        if ($connectTimeout !== null && (float) $connectTimeout > 0) {
            $request->connectTimeout((float) $connectTimeout);
        }

        $retryConfig = config('horizonhub.horizon_http_retry');
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

        if ($service !== null) {
            $headers = [];

            foreach ($service->headers as $header) {
                $headers[$header->name] = $header->value ?? '';
            }

            $request = $request->withHeaders($headers);
        }

        return $request;
    }

    /**
     * Process the HTTP response.
     *
     * @param Response $response The response.
     * @param Service $service The service instance.
     * @param string $url The URL.
     * @param bool $updateHeartbeat Whether to update the heartbeat.
     * @param string $logContext The log context.
     *
     * @return array The response data.
     */
    private function private__processHttpResponse(Response $response, Service $service, string $url, bool $updateHeartbeat = false, string $logContext = ''): array
    {
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
        Log::warning(config('app.name') . ': Horizon API call failed ' . $logContext, [
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
}
