<?php

namespace App\Http\Controllers\Horizon;

use App\Http\Controllers\Controller;
use App\Models\NotificationProvider;
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
        return $this->private__formView(new NotificationProvider);
    }

    /**
     * Store a new provider.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->private__validateProvider($request, null);
        NotificationProvider::create($data);

        return redirect()
            ->route('horizon.settings', ['tab' => 'providers'])
            ->with('status', 'Provider created.');
    }

    /**
     * Show the form to edit an existing provider.
     */
    public function edit(NotificationProvider $provider): View
    {
        return $this->private__formView($provider);
    }

    /**
     * Update an existing provider.
     */
    public function update(Request $request, NotificationProvider $provider): RedirectResponse
    {
        $data = $this->private__validateProvider($request, $provider);
        $provider->update($data);

        return redirect()
            ->route('horizon.settings', ['tab' => 'providers'])
            ->with('status', 'Provider updated.');
    }

    /**
     * Delete a provider.
     */
    public function destroy(NotificationProvider $provider): RedirectResponse
    {
        $provider->delete();

        return redirect()
            ->route('horizon.settings', ['tab' => 'providers'])
            ->with('status', 'Provider deleted.');
    }

    /**
     * Build data for the provider form view.
     */
    private function private__formView(NotificationProvider $provider): View
    {
        $config = $provider->config ?? [];
        $webhookUrl = $provider->type === NotificationProvider::TYPE_SLACK ? (string) ($config['webhook_url'] ?? '') : '';
        $emailTo = '';
        if ($provider->type === NotificationProvider::TYPE_EMAIL) {
            $to = $config['to'] ?? [];
            $emailTo = \is_array($to) ? \implode(', ', $to) : (string) $to;
        }

        return \view('horizon.providers.form', [
            'provider' => $provider,
            'webhookUrl' => $webhookUrl,
            'emailTo' => $emailTo,
            'header' => 'Horizon Hub – '.($provider->exists ? 'Edit provider' : 'New provider'),
        ]);
    }

    /**
     * Validate and normalize provider data.
     *
     * @return array<string, mixed>
     */
    private function private__validateProvider(Request $request, ?NotificationProvider $provider): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:slack,email',
            'webhook_url' => 'required_if:type,slack|nullable|url',
            'email_to' => 'required_if:type,email|nullable|string',
        ]);

        if ($validated['type'] === NotificationProvider::TYPE_EMAIL) {
            $emails = \array_values(\array_filter(\array_map('trim', \explode(',', (string) ($validated['email_to'] ?? '')))));
            foreach ($emails as $e) {
                if (! \filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    \abort(422, 'One or more email addresses are invalid.');
                }
            }
            if ($emails === []) {
                \abort(422, 'Email recipients are required.');
            }
            $config = ['to' => $emails];
        } else {
            $config = ['webhook_url' => (string) ($validated['webhook_url'] ?? '')];
        }

        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'config' => $config,
        ];
    }
}
