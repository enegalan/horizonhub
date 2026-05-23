<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormDrawer
{
    /**
     * The ID of the form drawer frame.
     */
    public const FRAME_ID = 'form-drawer';

    /**
     * The session key for the pending open form drawer.
     */
    private const SESSION_PENDING_OPEN = 'form_drawer_open';

    /**
     * Flash the pending open form drawer to the session.
     *
     * @param Request $request The request.
     *
     * @return void
     */
    public static function flashPendingOpen(Request $request): void
    {
        $route = $request->route();
        if ($route === null) {
            return;
        }

        $routeName = $route->getName();
        if (! \is_string($routeName) || $routeName === '') {
            return;
        }

        session()->flash(self::SESSION_PENDING_OPEN, [
            'route' => $routeName,
            'params' => $route->parameters(),
        ]);
    }

    /**
     * Check if the request is in a form drawer frame.
     *
     * @param Request $request The request.
     *
     * @return bool
     */
    public static function inFrame(Request $request): bool
    {
        return $request->header('Turbo-Frame') === self::FRAME_ID;
    }

    /**
     * On redirect, set the Turbo-Frame header to _top if the request is in a form drawer frame.
     *
     * @param RedirectResponse $response The response.
     * @param Request $request The request.
     *
     * @return RedirectResponse
     */
    public static function onRedirect(RedirectResponse $response, Request $request): RedirectResponse
    {
        if (self::inFrame($request)) {
            $response->header('Turbo-Frame', '_top');
        }

        return $response;
    }

    /**
     * Pull the pending open form drawer from the session and return the route name and parameters.
     *
     * @return string|null
     */
    public static function pullFrameSrc(): ?string
    {
        $pending = session()->pull(self::SESSION_PENDING_OPEN);
        if (! is_array($pending)) {
            return null;
        }

        $routeName = $pending['route'] ?? null;
        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        $params = $pending['params'] ?? [];
        if (! is_array($params)) {
            return null;
        }

        try {
            return route($routeName, $params);
        } catch (\Throwable) {
            return null;
        }
    }
}
