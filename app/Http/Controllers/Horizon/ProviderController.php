<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\UpsertProviderRequest;
use App\Models\NotificationProvider;
use App\Support\FlashStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    /**
     * Show the form to create a new provider.
     */
    public function create(): View
    {
        return \view('horizon.providers.form', [
            'provider' => new NotificationProvider,
            'header' => 'New provider',
        ]);
    }

    /**
     * Delete a provider.
     */
    public function destroy(NotificationProvider $provider): RedirectResponse
    {
        $provider->delete();

        return $this->redirectToRoute('horizon.providers.index', FlashStatus::success('Provider deleted.'));
    }

    /**
     * Show the form to edit an existing provider.
     */
    public function edit(NotificationProvider $provider): View
    {
        return \view('horizon.providers.form', [
            'provider' => $provider,
            'header' => 'Edit provider',
        ]);
    }

    /**
     * List notification providers.
     */
    public function index(Request $request): View
    {
        return \view('horizon.providers.index', [
            'providers' => collect(),
            'defer' => true,
            'search' => \trim((string) $request->query('search', '')),
            'header' => 'Providers',
        ]);
    }

    /**
     * Store a new provider.
     */
    public function store(UpsertProviderRequest $request): RedirectResponse
    {
        NotificationProvider::create($request->normalizedProviderData());

        return $this->redirectToRoute('horizon.providers.index', FlashStatus::success('Provider created.'));
    }

    /**
     * Update an existing provider.
     */
    public function update(UpsertProviderRequest $request, NotificationProvider $provider): RedirectResponse
    {
        $provider->update($request->normalizedProviderData());

        return $this->redirectToRoute('horizon.providers.index', FlashStatus::success('Provider updated.'));
    }
}
