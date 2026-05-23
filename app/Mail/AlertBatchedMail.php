<?php

namespace App\Mail;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertBatchedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The alert.
     */
    public Alert $alert;

    /**
     * The notification.
     */
    public array $notification;

    /**
     * Create a new alert batched mail instance.
     *
     * @param Alert $alert The alert.
     * @param array $notification The notification.
     */
    public function __construct(Alert $alert, array $notification)
    {
        $this->alert = $alert;
        $this->notification = $notification;
    }

    /**
     * Get the content for the email.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.alert-batched',
            text: 'emails.alert-batched-text',
            with: ['mailSubject' => $this->private__emailSubject($this->notification)],
        );
    }

    /**
     * Get the envelope for the email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->private__emailSubject($this->notification),
        );
    }

    /**
     * Get the email subject.
     *
     * @param array $notification The notification.
     *
     * @return string The email subject.
     */
    private function private__emailSubject(array $notification): string
    {
        $subject = "[{$notification['appName']}] {$notification['alertName']}: {$notification['ruleLabel']} – {$notification['serviceName']}";

        if ($notification['totalEventCount'] > 1) {
            $subject .= " ({$notification['totalEventCount']} events)";
        }

        return $subject;
    }
}
