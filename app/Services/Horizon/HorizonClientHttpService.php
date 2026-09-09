<?php

namespace App\Services\Horizon;

use App\Models\Service;
use App\Support\Http\HttpRetryBackoff;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HorizonClientHttpService
{
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
    public static function call(
        Service $service,
        string $path,
        string $method = 'post',
        bool $withDashboardSession = false,
        bool $bypassFailureCooldown = false,
        bool $allowWhenDisabled = false,
    ): array {
        // Check if the service is enabled.
        if (! $allowWhenDisabled && ! $service->enabled) {
            return [
                'success' => false,
                'message' => 'Service is disabled.',
                'status' => 503,
            ];
        }

        // Check if the service is in cooldown.
        if (! $bypassFailureCooldown && HorizonClientCacheService::hasFailureCooldown($service)) {
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
        $lock = null;
        $slotAcquired = false;

        try {
            // Check if request was previously cached so we can avoid new HTTP request.
            if ($shouldCache) {
                $cached = HorizonClientCacheService::getRequestPathCache($service, $path);

                if (empty($cached)) {
                    $lock = HorizonClientCacheService::requestPathFillLock($service, $path);

                    try {
                        $lock->block((int) config('horizonhub.api_timeout'));
                    } catch (LockTimeoutException) {
                        $lock = null;

                        return [
                            'success' => false,
                            'message' => 'Horizon API request coalescing timed out.',
                            'status' => 503,
                        ];
                    }

                    $cached = HorizonClientCacheService::getRequestPathCache($service, $path);
                }

                if ($cached !== null) {
                    if (config('app.debug')) {
                        Log::channel('app')->debug('Horizon API call (cache hit)', [
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

                // Reserve a concurrency slot so a slow upstream service is not
                // overwhelmed by parallel polls coming from multiple streams.
                $slotAcquired = HorizonClientCacheService::acquireServiceRequestSlot($service);

                if (! $slotAcquired) {
                    return [
                        'success' => false,
                        'message' => 'Service concurrent request limit reached.',
                        'status' => 503,
                    ];
                }
            }

            if (config('app.debug')) {
                Log::channel('app')->debug('Horizon API call', [
                    'service_id' => $service->id ?? null,
                    'service_name' => $service->name ?? null,
                    'url' => $url,
                    'http_method' => $httpMethod,
                    'with_dashboard_session' => $withDashboardSession,
                    'allow_when_disabled' => $allowWhenDisabled,
                ]);
            }

            // Attempt to make the HTTP request.
            $attempt = function () use ($service, $url, $httpMethod, $withDashboardSession): ?Response {
                $request = self::private__newHorizonPendingRequest($httpMethod, $service);

                if ($withDashboardSession) {
                    $bootstrap = self::private__bootstrapDashboardSession($service);

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

            $response = $attempt();

            if ($response === null) {
                return [
                    'success' => false,
                    'message' => 'Unable to bootstrap Horizon dashboard session or CSRF token.',
                    'status' => 502,
                ];
            }

            // If the response is a 419 (page expired), retry the request.
            if ($withDashboardSession && $response->status() === 419) {
                $retryResponse = $attempt();

                if ($retryResponse !== null) {
                    $response = $retryResponse;
                }
            }

            // Process the HTTP response.
            $result = self::private__processHttpResponse(
                $response,
                $service,
                $url,
                ! $withDashboardSession,
                $withDashboardSession ? ' (with dashboard session)' : '',
            );

            // Handle successful response.
            if ($result['success'] === true) {
                // Forget failure cooldown and timeout advice.
                HorizonClientCacheService::forgetFailureCooldown($service);
                HorizonClientCacheService::forgetTimeoutAdvice($service);

                if ($shouldCache) {
                    // Cache the response.
                    HorizonClientCacheService::putRequestPathCache($service, $path, $result);
                }

                return $result;
            }

            // If the response is not authorized, put the service in cooldown.
            if (! \in_array($result['status'], config('horizonhub.horizon_http_auth_statuses'), true)) {
                HorizonClientCacheService::putFailureCooldown($service);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::channel('app')->error('Horizon API call exception' . ($withDashboardSession ? ' (with dashboard session)' : ''), [
                'service_id' => $service->id ?? null,
                'url' => $url,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            // Put the service in cooldown.
            HorizonClientCacheService::putFailureCooldown($service);

            // Advise the user to raise the API timeout when the upstream service
            // responds slower than the configured value.
            if (self::private__isTimeoutException($e)) {
                HorizonClientCacheService::putTimeoutAdvice($service);
            }

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
        } finally {
            if ($slotAcquired) {
                HorizonClientCacheService::releaseServiceRequestSlot($service);
            }

            $lock?->release();
        }
    }

    /**
     * Bootstrap the dashboard session.
     *
     * @param Service $service The service instance.
     *
     * @return array|null The response data.
     */
    private static function private__bootstrapDashboardSession(Service $service): ?array
    {
        $dashboardUrl = $service->getBaseUrl() . (string) config('horizonhub.horizon_paths.dashboard');

        $cookieJar = new CookieJar;

        try {
            $response = self::private__newHorizonPendingRequest('get', $service)
                ->withOptions(['cookies' => $cookieJar])
                ->get($dashboardUrl);
        } catch (\Throwable $e) {
            Log::channel('app')->warning('failed to bootstrap Horizon dashboard session', [
                'service_id' => $service->id ?? null,
                'url' => $dashboardUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->ok()) {
            Log::channel('app')->warning('unexpected status when bootstrapping Horizon dashboard session', [
                'service_id' => $service->id ?? null,
                'url' => $dashboardUrl,
                'status' => $response->status(),
            ]);

            return null;
        }

        $html = $response->body();
        $matches = [];

        if (! \preg_match('/<meta\s+name=["\']csrf-token["\']\s+content=["\']([^"\']+)["\']/', $html, $matches)) {
            Log::channel('app')->warning('unable to extract CSRF token from Horizon dashboard', [
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
     * Determine whether an exception represents an upstream connection timeout.
     *
     * @param \Throwable $e The exception.
     *
     * @return bool True when the upstream service timed out, false otherwise.
     */
    private static function private__isTimeoutException(\Throwable $e): bool
    {
        if (! $e instanceof ConnectionException) {
            return false;
        }

        return \str_contains(\strtolower((string) $e->getMessage()), 'timed out');
    }

    /**
     * Create a new Horizon pending request.
     *
     * @param string $httpMethod The HTTP method.
     * @param Service|null $service The service instance.
     *
     * @return PendingRequest The pending request.
     */
    private static function private__newHorizonPendingRequest(string $httpMethod, ?Service $service = null): PendingRequest
    {
        $request = Http::timeout((int) config('horizonhub.api_timeout'));

        $connectTimeout = config('horizonhub.horizon_http_connect_timeout');

        if ($connectTimeout !== null && (float) $connectTimeout > 0) {
            $request->connectTimeout((float) $connectTimeout);
        }

        $retryConfig = config('horizonhub.horizon_http_retry');
        $retryTimes = (int) max(1, $retryConfig['times']);
        $retryOnStatus = $retryConfig['retry_on_status'];

        if ($httpMethod === 'get' && $retryTimes > 1) {
            $request = $request->retry(
                $retryTimes,
                // Calculate the sleep time between retries using exponential backoff.
                function (int $attempt, \Throwable $e): int {
                    return HttpRetryBackoff::delayMsForAttempt($attempt);
                },
                // Retry the request if it fails.
                function (\Throwable $exception, PendingRequest $pending, ?string $method = 'GET') use ($retryOnStatus): bool {
                    // Only retry GET requests.
                    if ($method !== null && \strtoupper($method) !== 'GET') {
                        return false;
                    }

                    // Do not retry connection/timeouts.
                    if ($exception instanceof ConnectionException) {
                        return false;
                    }

                    // Retry the request if it fails.
                    if ($exception instanceof RequestException && $exception->response !== null) {
                        return \in_array($exception->response->status(), $retryOnStatus, true);
                    }

                    return false;
                },
                throw: false,
            );
        }

        // Add the service headers to the request.
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
    private static function private__processHttpResponse(Response $response, Service $service, string $url, bool $updateHeartbeat = false, string $logContext = ''): array
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

        // Build the error message from response.
        $errorMessage = "Horizon API returned an HTTP error ({$response->status()}).";
        $rawBody = $response->body();
        $decoded = \json_decode($rawBody, true);

        if (\is_array($decoded) && isset($decoded['message']) && (string) $decoded['message'] !== '') {
            $errorMessage = (string) $decoded['message'];
        } else {
            $trimmedBody = \trim((string) $rawBody);
            $isHtml = $trimmedBody !== \strip_tags($trimmedBody);

            if (! $isHtml && $trimmedBody !== '') {
                $errorMessage = \mb_substr($trimmedBody, 0, 200);
            }
        }

        Log::channel('app')->warning("Horizon API call failed $logContext", [
            'service_id' => $service->id,
            'url' => $url,
            'status' => $response->status(),
            'message' => $errorMessage,
        ]);

        return [
            'success' => false,
            'message' => $errorMessage,
            'status' => $response->status(),
        ];
    }
}
