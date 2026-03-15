<?php

namespace App\Mail;

use App\Models\Alert;
use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AlertBatchedMail extends Mailable {
    use Queueable, SerializesModels;

    /**
     * The alert.
     *
     * @var Alert
     */
    public Alert $alert;

    /**
     * The enriched events.
     *
     * @var array<int, array{service_id: int, job_id: int|null, triggered_at: string, job_class: string|null, queue: string|null, failed_at: string|null, exception: string|null, attempts: int|null}>
     */
    public array $enrichedEvents;

    /**
     * The service.
     *
     * @var Service|null
     */
    public ?Service $service;

    /**
     * The mail subject.
     *
     * @var string
     */
    public string $mailSubject;

    /**
     * Total number of events (may exceed count of enrichedEvents when capped).
     *
     * @var int
     */
    public int $totalEventCount;

    /**
     * Construct the alert batched mail.
     *
     * @param Alert $alert
     * @param array<int, array{service_id: int, job_id: int|null, triggered_at: string, job_class: string|null, queue: string|null, failed_at: string|null, exception: string|null, attempts: int|null}> $enrichedEvents
     * @param Service|null $service
     * @param string $mailSubject
     * @param int $totalEventCount
     */
    public function __construct(
        Alert $alert,
        array $enrichedEvents,
        ?Service $service,
        string $mailSubject,
        int $totalEventCount
    ) {
        $this->alert = $alert;
        $this->enrichedEvents = $enrichedEvents;
        $this->service = $service;
        $this->mailSubject = $mailSubject;
        $this->totalEventCount = $totalEventCount;
    }

    /**
     * Get the envelope for the email.
     *
     * @return Envelope
     */
    public function envelope(): Envelope {
        return new Envelope(
            subject: $this->mailSubject
        );
    }

    /**
     * Get the content for the email.
     *
     * @return Content
     */
    public function content(): Content {
        return new Content(
            view: 'emails.alert-batched',
            text: 'emails.alert-batched-text'
        );
    }
}
