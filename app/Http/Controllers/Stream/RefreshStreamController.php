<?php

namespace App\Http\Controllers\Stream;

use App\Http\Controllers\StreamController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RefreshStreamController extends StreamController
{
    /**
     * Open the generic Horizon Hub refresh stream (SSE).
     *
     * Behaviour:
     * - If the request includes a valid "path" query
     *   every tick renders that page internally and pushes its full HTML in the
     *   SSE event payload. The frontend can then refresh only the relevant DOM
     *   fragment without issuing a new HTTP request.
     * - If no valid "path" is provided, each event only includes a timestamp,
     *   and the client is free to decide whether to perform a manual fetch.
     */
    public function stream(Request $request): StreamedResponse
    {
        $path = $this->private__resolvePath($request);
        $query = $this->private__resolveQuery($request);
        $pushHtml = $path !== null;
        $baseUrl = $pushHtml ? $request->getSchemeAndHttpHost() : null;

        $cookies = $pushHtml ? $request->cookies->all() : [];
        $server = $pushHtml ? $request->server->all() : [];

        return $this->runStream(function () use ($pushHtml, $path, $query, $baseUrl, $cookies, $server): array {
            $payload = ['ts' => \time()];

            if ($pushHtml && $path !== null && $baseUrl !== null) {
                $url = $query !== '' ? "$baseUrl$path?$query" : "$baseUrl$path";
                $html = $this->private__fetchPageHtml($url, $cookies, $server);
                if ($html !== null) {
                    $payload['html'] = $html;
                }
            }

            return $payload;
        }, 'refresh');
    }

    /**
     * Resolve and validate path query. Returns path or null if invalid/unsafe.
     */
    private function private__resolvePath(Request $request): ?string
    {
        $path = $request->query('path');
        if ($path === null || $path === '') {
            return null;
        }
        $path = '/'.\ltrim((string) $path, '/');
        if (\str_contains($path, '..')) {
            return null;
        }
        if ($path === '/horizon' || \str_starts_with($path, '/horizon/')) {
            return $path;
        }

        return null;
    }

    /**
     * Parse the "query" param and return a normalized query string for the internal page fetch.
     */
    private function private__resolveQuery(Request $request): string
    {
        $raw = $request->query('query');
        if ($raw === null || $raw === '' || ! \is_string($raw)) {
            return '';
        }

        $decoded = \rawurldecode($raw);
        $pairs = [];
        foreach (\explode('&', $decoded) as $segment) {
            if ($segment === '') {
                continue;
            }
            $eq = \strpos($segment, '=');
            $keyEncoded = $eq !== false ? \substr($segment, 0, $eq) : $segment;
            $key = \rawurldecode(\trim($keyEncoded));
            if ($key === '') {
                continue;
            }
            $valueRaw = $eq !== false ? \substr($segment, $eq + 1) : '';
            $value = \rawurldecode($valueRaw);
            $pairs[] = \rawurlencode($key).'='.\rawurlencode($value);
        }

        return \implode('&', $pairs);
    }

    /**
     * Perform internal request to get full page HTML.
     *
     * @param  array<string, string>  $cookies
     * @param  array<string, mixed>  $server
     */
    private function private__fetchPageHtml(string $url, array $cookies, array $server): ?string
    {
        try {
            $subRequest = Request::create($url, 'GET', [], $cookies, [], $server);
            $subRequest->headers->set('X-Requested-With', 'XMLHttpRequest');

            $response = \app()->handle($subRequest);
            $content = $response->getContent();

            return \is_string($content) ? $content : null;
        } catch (\Throwable $e) {
            \Log::warning('RefreshStreamController fetchPageHtml failed', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
