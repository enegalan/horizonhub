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
    public function applies_when_explicit_service_id_listed(): void
    {
        $service = Service::factory()->create();
        $other = Service::factory()->create();
        $alert = Alert::factory()->create([
            'service_ids' => [$service->id],
        ]);

        $this->assertTrue($alert->appliesToServiceId($service->id));
        $this->assertFalse($alert->appliesToServiceId($other->id));
    }

    #[Test]
    public function does_not_apply_when_scope_empty(): void
    {
        $service = Service::factory()->create();
        $alert = Alert::factory()->create([
            'service_ids' => [],
        ]);

        $this->assertFalse($alert->appliesToServiceId($service->id));
        $this->assertSame([], $alert->resolvedServiceIds());
    }

    #[Test]
    public function resolved_enabled_service_ids_returns_explicit_selection(): void
    {
        $included = Service::factory()->create();
        Service::factory()->create(['enabled' => false]);

        $alert = Alert::factory()->create([
            'service_ids' => [$included->id],
        ]);

        $this->assertSame([$included->id], $alert->resolvedServiceIds());
    }
}
