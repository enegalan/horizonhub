<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertAppliesToServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function applies_to_all_when_scope_empty(): void
    {
        $service = Service::factory()->create();
        $alert = Alert::factory()->create([
            'service_ids' => [],
            'service_tags' => [],
        ]);

        $this->assertTrue($alert->appliesToServiceId($service->id));
    }

    #[Test]
    public function applies_when_explicit_service_id_listed(): void
    {
        $service = Service::factory()->create();
        $other = Service::factory()->create();
        $alert = Alert::factory()->create([
            'service_ids' => [$service->id],
            'service_tags' => [],
        ]);

        $this->assertTrue($alert->appliesToServiceId($service->id));
        $this->assertFalse($alert->appliesToServiceId($other->id));
    }

    #[Test]
    public function applies_when_service_has_all_alert_tags(): void
    {
        $service = Service::factory()->create(['tags' => ['production', 'mailing']]);
        $partial = Service::factory()->create(['tags' => ['production']]);
        $alert = Alert::factory()->create([
            'service_ids' => [],
            'service_tags' => ['production', 'mailing'],
        ]);

        $this->assertTrue($alert->appliesToServiceId($service->id));
        $this->assertFalse($alert->appliesToServiceId($partial->id));
    }

    #[Test]
    public function resolved_enabled_service_ids_unions_explicit_and_tag_matches(): void
    {
        $explicit = Service::factory()->create(['tags' => ['local']]);
        $tagOnly = Service::factory()->create(['tags' => ['production', 'mailing']]);
        Service::factory()->create(['tags' => ['production'], 'enabled' => false]);

        $alert = Alert::factory()->create([
            'service_ids' => [$explicit->id],
            'service_tags' => ['production', 'mailing'],
        ]);

        $ids = $alert->resolvedServiceIds();

        $this->assertSame([$explicit->id, $tagOnly->id], $ids);
    }
}
