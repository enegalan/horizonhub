<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FormDrawer
{
    public const FRAME_ID = 'form-drawer';

    public static function inFrame(Request $request): bool
    {
        return $request->header('Turbo-Frame') === self::FRAME_ID;
    }

    public static function onRedirect(RedirectResponse $response, Request $request): RedirectResponse
    {
        if (self::inFrame($request)) {
            $response->header('Turbo-Frame', '_top');
        }

        return $response;
    }
}
