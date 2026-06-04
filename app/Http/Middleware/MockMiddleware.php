<?php

namespace App\Http\Middleware;

use App\Support\FlashStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MockMiddleware
{
    /**
     * Block mutating requests when API_ENVIRONMENT=mock.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('horizonhub.mock')) {
            return $next($request);
        }

        if (! \in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $message = 'Mock is read-only.';

        if ($request->expectsJson()) {
            return \response()->json(['message' => $message], 403);
        }

        return redirect()
            ->back()
            ->with('status', FlashStatus::error($message));
    }
}
