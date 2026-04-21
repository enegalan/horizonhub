<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\NotificationProvider;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Display the settings page.
     */
    public function index(Request $request): View
    {
        $tab = (string) $request->query('tab', 'appearance');

        if (! \in_array($tab, ['appearance', 'providers'], true)) {
            $tab = 'appearance';
        }

        $providers = NotificationProvider::orderBy('type')->orderBy('name')->get();

        return \view('horizon.settings.index', [
            'tab' => $tab,
            'providers' => $providers,
            'header' => 'Settings',
        ]);
    }
}
