<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Services\ServiceFilterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class InstanceController extends Controller
{
    /**
     * Display masters and supervisors across connected services.
     */
    public function index(Request $request): View
    {
        return \view('pages.instances.index', ServiceFilterService::indexViewData($request, [
            'header' => 'Instances',
            'services' => Service::enabledNamed(),
        ]));
    }
}
