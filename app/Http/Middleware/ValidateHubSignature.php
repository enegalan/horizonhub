<?php

namespace App\Http\Middleware;

use App\Models\Service;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateHubSignature {
    private const TIMESTAMP_HEADER = 'X-Hub-Timestamp';
    private const SIGNATURE_HEADER = 'X-Hub-Signature';
    private const MAX_AGE_SECONDS = 300;

    /**
     * Validate the Horizon Hub signature.
     * 
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response {
        $apiKey = $request->header('X-Api-Key');
        $timestamp = $request->header(self::TIMESTAMP_HEADER);
        $signature = $request->header(self::SIGNATURE_HEADER);

        if (! $apiKey || ! $timestamp || ! $signature) {
            return response()->json(['message' => 'Missing API key, timestamp or signature'], 401);
        }

        $service = Service::where('api_key', $apiKey)->first();
        if (! $service) {
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

        $request->attributes->set('horizonhub_service', $service);

        return $next($request);
    }
}
