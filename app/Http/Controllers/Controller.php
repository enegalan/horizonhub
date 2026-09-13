<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Redirect to a named route carrying a flash status.
     *
     * @param array{message: string, type: string} $status The flash status.
     * @param array<int|string, int|string> $params The route parameters.
     */
    protected function redirectToRoute(string $route, array $status, array $params = []): RedirectResponse
    {
        return redirect()->route($route, $params)->with('status', $status);
    }
}
