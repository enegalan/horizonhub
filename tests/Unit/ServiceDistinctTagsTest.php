<?php

namespace Tests\Unit;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceDistinctTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_distinct_tags_can_limit_to_enabled_services(): void
    {
        Service::factory()->create(['enabled' => true, 'tags' => ['live']]);
        Service::factory()->create(['enabled' => false, 'tags' => ['archived']]);

        $this->assertSame(['live'], Service::distinctTags(true));
    }

    public function test_distinct_tags_collects_normalized_tags_from_all_services(): void
    {
        Service::factory()->create(['tags' => ['prod', 'api']]);
        Service::factory()->create(['tags' => ['api', 'staging']]);

        $this->assertSame(['api', 'prod', 'staging'], Service::distinctTags());
    }
}
