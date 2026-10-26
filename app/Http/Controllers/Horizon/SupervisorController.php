<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Contracts\View\View;

class SupervisorController extends Controller
{
    /**
     * Display one supervisor nested under its service.
     */
    public function show(Service $service, string $supervisor): View
    {
        return \view('pages.supervisors.show', [
            'defer' => true,
            'header' => $supervisor,
            'service' => $service,
            'supervisorName' => $supervisor,
        ]);
    }
}
