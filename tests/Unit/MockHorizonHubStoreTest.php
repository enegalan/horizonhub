<?php

namespace Tests\Unit;

use App\Support\HorizonHub\MockHorizonHubStore;
use Tests\TestCase;

class MockHorizonHubStoreTest extends TestCase
{
    public function test_alerts_index_stream_data_includes_service_labels(): void
    {
        $store = new MockHorizonHubStore;
        $data = $store->alertsIndexStreamData();

        $this->assertGreaterThanOrEqual(2, $data['alerts']->count());
        $this->assertContains('billing-api', $data['labelsByAlertId'][1] ?? []);
    }

    public function test_enabled_services_include_demo_catalog_names(): void
    {
        $store = new MockHorizonHubStore;
        $names = $store->enabledServicesOrdered()->pluck('name')->all();

        $this->assertContains('billing-api', $names);
        $this->assertContains('notifications', $names);
        $this->assertContains('reporting', $names);
    }

    public function test_matching_tag_service_ids_filters_by_tag(): void
    {
        $store = new MockHorizonHubStore;

        $ids = $store->matchingTagServiceIds(['billing']);

        $this->assertSame([1], $ids);
    }

    public function test_resolve_enabled_service_ids_respects_enabled_flag(): void
    {
        $store = new MockHorizonHubStore;

        $this->assertSame([1, 2, 3], $store->resolveEnabledServiceIds([1, 2, 3]));
    }

    public function test_demo_catalog_has_large_varied_dataset_by_default(): void
    {
        $store = new MockHorizonHubStore;

        $this->assertGreaterThanOrEqual(90, $store->enabledServices()->count());
        $this->assertGreaterThanOrEqual(58, $store->enabledAlerts()->count());
        $this->assertGreaterThanOrEqual(1000, $store->alertLogTotalCount());
    }
}
