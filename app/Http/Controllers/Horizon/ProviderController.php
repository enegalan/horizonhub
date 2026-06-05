<?php

namespace App\Http\Controllers\Horizon;

use App\Contracts\HorizonHubStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\UpsertProviderRequest;
use App\Models\NotificationProvider;
use App\Support\FlashStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProviderController extends Controller
{
    /**
     * The store.
     */
    private HorizonHubStore $store;

    /**
     * The constructor.
     *
     * @param HorizonHubStore $store The store.
     */
    public function __construct(HorizonHubStore $store)
    {
        $this->store = $store;
    }

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
        $this->store->deleteNotificationProvider($provider);

        return redirect()
            ->route('horizon.providers.index')
            ->with('status', FlashStatus::success('Provider deleted.'));
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
    public function index(): View
    {
        return \view('horizon.providers.index', [
            'providers' => collect(),
            'defer' => true,
            'header' => 'Providers',
        ]);
    }

    /**
     * Store a new provider.
     */
    public function store(UpsertProviderRequest $request): RedirectResponse
    {
        $this->store->createNotificationProvider($request->normalizedProviderData());

        return redirect()
            ->route('horizon.providers.index')
            ->with('status', FlashStatus::success('Provider created.'));
    }

    /**
     * Update an existing provider.
     */
    public function update(UpsertProviderRequest $request, NotificationProvider $provider): RedirectResponse
    {
        $this->store->updateNotificationProvider($provider, $request->normalizedProviderData());

        return redirect()
            ->route('horizon.providers.index')
            ->with('status', FlashStatus::success('Provider updated.'));
    }
}
