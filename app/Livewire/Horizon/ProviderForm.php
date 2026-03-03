<?php

namespace App\Livewire\Horizon;

use App\Models\NotificationProvider;
use Livewire\Component;

class ProviderForm extends Component {
    public ?NotificationProvider $provider = null;

    public string $name = '';

    public string $type = NotificationProvider::TYPE_SLACK;

    public string $webhook_url = '';

    public string $email_to = '';

    public function mount(?NotificationProvider $provider = null): void {
        if ($provider !== null) {
            $this->provider = $provider;
            $this->name = $this->provider->name;
            $this->type = $this->provider->type;
            $config = $this->provider->config ?? array();
            if ($this->provider->type === NotificationProvider::TYPE_SLACK) {
                $this->webhook_url = (string) ($config['webhook_url'] ?? '');
            } else {
                $to = $config['to'] ?? array();
                $this->email_to = is_array($to) ? implode(', ', $to) : (string) $to;
            }
        }
    }

    public function save(): void {
        $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:slack,email',
            'webhook_url' => 'required_if:type,slack|nullable|url',
            'email_to' => 'required_if:type,email|nullable|string',
        ]);

        if ($this->type === NotificationProvider::TYPE_EMAIL) {
            $emails = array_values(array_filter(array_map('trim', explode(',', $this->email_to))));
            foreach ($emails as $e) {
                if (! filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $this->addError('email_to', __('One or more email addresses are invalid.'));

                    return;
                }
            }
            $this->validate(['email_to' => 'required']);
        }

        $config = $this->type === NotificationProvider::TYPE_SLACK
            ? array('webhook_url' => $this->webhook_url)
            : array('to' => array_values(array_filter(array_map('trim', explode(',', $this->email_to)))));

        $data = array(
            'name' => $this->name,
            'type' => $this->type,
            'config' => $config,
        );

        if ($this->provider) {
            $this->provider->update($data);
            $this->dispatch('toast', type: 'success', message: 'Provider updated.');
        } else {
            NotificationProvider::create($data);
            $this->dispatch('toast', type: 'success', message: 'Provider created.');
        }

        $this->redirect(route('horizon.settings', ['tab' => 'providers']), navigate: true);
    }

    public function render() {
        $header = $this->provider ? 'Edit provider' : 'New provider';

        return view('livewire.horizon.provider-form', [])->layout('layouts.app', ['header' => 'Horizon Hub – ' . $header]);
    }
}
