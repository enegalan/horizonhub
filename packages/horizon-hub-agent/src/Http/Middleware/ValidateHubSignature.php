<?php

namespace HorizonHub\Agent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateHubSignature {
    private const TIMESTAMP_HEADER = 'X-Hub-Timestamp';
    private const SIGNATURE_HEADER = 'X-Hub-Signature';
    private const MAX_AGE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response {
        $apiKey = config('horizon_hub.api_key');
        if ($apiKey === '' || $apiKey === null) {
            return response()->json(['message' => 'Agent not configured'], 503);
        }

        $incomingKey = $request->header('X-Api-Key');
        $timestamp = $request->header(self::TIMESTAMP_HEADER);
        $signature = $request->header(self::SIGNATURE_HEADER);

        if (! $incomingKey || ! $timestamp || ! $signature) {
            return response()->json(['message' => 'Missing API key, timestamp or signature'], 401);
        }

        if (! hash_equals($apiKey, $incomingKey)) {
            return response()->json(['message' => 'Invalid API key'], 401);
        }

        $timestampInt = (int) $timestamp;
        if (abs(time() - $timestampInt) > self::MAX_AGE_SECONDS) {
            return response()->json(['message' => 'Request timestamp expired'], 401);
        }

        $payload = $request->getContent();
        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $apiKey);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
