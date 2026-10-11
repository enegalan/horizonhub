<?php

namespace Tests\Unit;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceAllTagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    #[Test]
    public function it_excludes_disabled_service_tags_when_enabled_only(): void
    {
        Service::factory()->create(['tags' => ['production'], 'enabled' => true]);
        Service::factory()->create(['tags' => ['legacy'], 'enabled' => false]);

        $this->assertSame(['production'], Service::allTags(true));
        $this->assertSame(['legacy', 'production'], Service::allTags(false));
    }

    #[Test]
    public function it_returns_distinct_tag_values_for_many_services(): void
    {
        $tags = ['production', 'staging', 'eu', 'us', 'critical'];
        $expected = $tags;
        \sort($expected);

        for ($index = 0; $index < 400; $index++) {
            Service::factory()->create(['tags' => [$tags[$index % \count($tags)]]]);
        }

        $this->assertSame($expected, Service::allTags());
    }

    #[Test]
    public function it_serves_repeated_calls_from_the_cache(): void
    {
        Service::factory()->create(['tags' => ['production']]);

        $this->assertSame(['production'], Service::allTags());

        DB::flushQueryLog();
        DB::enableQueryLog();
        $cached = Service::allTags();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(['production'], $cached);
        $this->assertCount(0, $queries, 'Repeated calls within the TTL must be served from cache.');
    }
}
