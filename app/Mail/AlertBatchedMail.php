<?php

namespace App\Mail;

use App\Models\Alert;
use App\Models\Service;
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
     * The enriched events.
     *
     * @var array<int, array{service_id: int, job_uuid: string|null, triggered_at: string, job_class: string|null, queue: string|null, failed_at: string|null, exception: string|null, attempts: int|null}>
     */
    public array $enrichedEvents;

    /**
     * The mail subject.
     */
    public string $mailSubject;

    /**
     * The service.
     */
    public ?Service $service;

    /**
     * Total number of events (may exceed count of enrichedEvents when capped).
     */
    public int $totalEventCount;

    /**
     * Construct the alert batched mail.
     *
     * @param  array<int, array{service_id: int, job_uuid: string|null, triggered_at: string, job_class: string|null, queue: string|null, failed_at: string|null, exception: string|null, attempts: int|null}>  $enrichedEvents
     */
    public function __construct(Alert $alert, array $enrichedEvents, ?Service $service, string $mailSubject, int $totalEventCount)
    {
        $this->alert = $alert;
        $this->enrichedEvents = $enrichedEvents;
        $this->service = $service;
        $this->mailSubject = $mailSubject;
        $this->totalEventCount = $totalEventCount;
    }

    /**
     * Get the content for the email.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.alert-batched',
            text: 'emails.alert-batched-text'
        );
    }

    /**
     * Get the envelope for the email.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject
        );
    }
}
