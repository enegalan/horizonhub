<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ThemeController extends Controller
{
    /**
     * Display theme page.
     */
    public function theme(): View
    {
        return \view('horizon.theme.index', [
            'header' => 'Theme',
        ]);
    }
}
