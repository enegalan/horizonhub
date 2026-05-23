<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;

class RedirectFormToDrawerTest extends TestCase
{
    public function test_service_create_without_frame_redirects_to_index_with_drawer_query(): void
    {
        $drawerPath = parse_url(route('horizon.services.create'), PHP_URL_PATH);

        $response = $this->get(route('horizon.services.create'));

        $response->assertRedirect(route('horizon.services.index', [
            'drawer' => $drawerPath,
        ]));
    }
}
