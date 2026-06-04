<?php

namespace App\Services\Notifiers;

use App\Mail\AlertBatchedMail;
use App\Models\Alert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotifierService extends AbstractAlertNotifier
{
    /**
     * Get the metadata.
     *
     * @return array{label: string, icon: string, description: string, color: string}
     */
    public static function meta(): array
    {
        return [
            'label' => 'Email',
            'icon' => 'envelope',
            'description' => 'Deliver alerts to one or more email recipients.',
            'color' => 'sky',
        ];
    }

    /**
     * Normalize the config.
     *
     * @param array<string, mixed> $validated
     *
     * @return array<string, mixed>
     */
    public static function normalizedConfig(array $validated): array
    {
        $emails = \array_values(\array_filter(\array_map('trim', \explode(',', (string) ($validated['email_to'] ?? '')))));

        foreach ($emails as $email) {
            if (! \filter_var($email, FILTER_VALIDATE_EMAIL)) {
                \abort(422, 'One or more email addresses are invalid.');
            }
        }

        if (empty($emails)) {
            \abort(422, 'Email recipients are required.');
        }

        return ['to' => $emails];
    }

    /**
     * Get the type.
     */
    public static function type(): string
    {
        return 'email';
    }

    /**
     * Send a batched alert.
     *
     * @param Alert $alert The alert.
     * @param array<int, array{service_id: int, job_uuid: string|null, triggered_at: string}> $events The events.
     * @param array<string, mixed> $config The config.
     */
    public function sendBatched(Alert $alert, array $events, array $config): void
    {
        $to = \is_array($config['to'] ?? null)
            ? \array_values(\array_filter(\array_map('trim', $config['to'])))
            : [];

        if (empty($to)) {
            return;
        }

        $notification = $this->buildNotification($alert, $events);

        Log::channel('app')->info('sending alert email', [
            'alert_id' => $alert->id,
            'to' => $to,
            'event_count' => $notification['totalEventCount'],
        ]);

        Mail::to($to)->send(new AlertBatchedMail($alert, $notification));
    }
}
