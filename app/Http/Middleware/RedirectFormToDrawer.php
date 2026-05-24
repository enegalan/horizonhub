<?php

namespace App\Http\Middleware;

use App\Support\FormDrawer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectFormToDrawer
{
    public function handle(Request $request, Closure $next, string $indexRoute): Response
    {
        // We want to redirect the user to the index page with the drawer open instead of displaying the full form view page.
        if ($request->isMethod('GET') && ! FormDrawer::inFrame($request)) {
            FormDrawer::flashPendingOpen($request);

            return redirect()->route($indexRoute);
        }

        return $next($request);
    }
}
