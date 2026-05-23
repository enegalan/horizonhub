<?php

namespace Tests\Feature\Http\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectFormToDrawerTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_create_without_frame_redirects_to_index_and_opens_drawer(): void
    {
        $redirect = $this->get(route('horizon.services.create'));

        $redirect->assertRedirect(route('horizon.services.index'));

        $this->get($redirect->headers->get('Location'))
            ->assertOk()
            ->assertSee('src="' . route('horizon.services.create') . '"', false);
    }

    public function test_index_drawer_query_param_is_ignored(): void
    {
        $this->get(route('horizon.services.index', [
            'drawer' => 'https://evil.example/phish',
        ]))
            ->assertOk()
            ->assertDontSee('src="https://evil.example/phish"', false);
    }
}
