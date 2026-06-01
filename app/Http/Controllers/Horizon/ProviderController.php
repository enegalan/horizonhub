<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Horizon\UpsertProviderRequest;
use App\Models\NotificationProvider;
use App\Support\FlashStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProviderController extends Controller
{
    /**
     * Show the form to create a new provider.
     */
    public function create(): View
    {
        return $this->private__formView(new NotificationProvider);
    }

    /**
     * Delete a provider.
     */
    public function destroy(NotificationProvider $provider): RedirectResponse
    {
        $provider->delete();

        return redirect()
            ->route('horizon.providers.index')
            ->with('status', FlashStatus::success('Provider deleted.'));
    }

    /**
     * Show the form to edit an existing provider.
     */
    public function edit(NotificationProvider $provider): View
    {
        return $this->private__formView($provider);
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
        NotificationProvider::create($request->normalizedProviderData());

        return redirect()
            ->route('horizon.providers.index')
            ->with('status', FlashStatus::success('Provider created.'));
    }

    /**
     * Update an existing provider.
     */
    public function update(UpsertProviderRequest $request, NotificationProvider $provider): RedirectResponse
    {
        $provider->update($request->normalizedProviderData());

        return redirect()
            ->route('horizon.providers.index')
            ->with('status', FlashStatus::success('Provider updated.'));
    }

    /**
     * Build data for the provider form view.
     */
    private function private__formView(NotificationProvider $provider): View
    {
        $webhookUrl = $provider->usesWebhook() ? $provider->getWebhookUrl() : '';

        $toEmails = $provider->getToEmails();
        $emailTo = \is_array($toEmails) ? \implode(', ', $toEmails) : (string) $toEmails;

        return \view('horizon.providers.form', [
            'provider' => $provider,
            'webhookUrl' => $webhookUrl,
            'emailTo' => $emailTo,
            'header' => $provider->exists ? 'Edit provider' : 'New provider',
        ]);
    }
}
