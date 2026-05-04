<?php

namespace Tests\Unit;

use App\Mail\AlertBatchedMail;
use App\Models\Alert;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertBatchedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_envelope_and_content_are_built_from_constructor_data(): void
    {
        $alert = Alert::query()->create(['name' => 'a', 'rule_type' => Alert::RULE_FAILURE_COUNT, 'enabled' => true]);
        $service = Service::query()->create(['name' => 'svc', 'base_url' => 'https://svc.test', 'status' => 'online']);
        $mail = new AlertBatchedMail(
            $alert,
            [['service_id' => $service->id, 'job_uuid' => 'u1', 'triggered_at' => now()->toIso8601String(), 'job_class' => null, 'queue' => null, 'failed_at' => null, 'exception' => null, 'attempts' => null]],
            $service,
            'Subject test',
            1,
        );

        $this->assertSame('Subject test', $mail->envelope()->subject);
        $this->assertSame('emails.alert-batched', $mail->content()->view);
        $this->assertSame('emails.alert-batched-text', $mail->content()->text);
    }
}
