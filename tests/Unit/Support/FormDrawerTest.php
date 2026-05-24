<?php

namespace Tests\Unit\Support;

use App\Support\FormDrawer;
use Illuminate\Http\Request;
use Tests\TestCase;

class FormDrawerTest extends TestCase
{
    public function test_in_frame_detects_form_drawer_turbo_frame_header(): void
    {
        $request = Request::create('/horizon/services/create', 'GET');
        $request->headers->set('Turbo-Frame', FormDrawer::FRAME_ID);

        $this->assertTrue(FormDrawer::inFrame($request));
    }

    public function test_in_frame_is_false_without_turbo_frame_header(): void
    {
        $request = Request::create('/horizon/services/create', 'GET');

        $this->assertFalse(FormDrawer::inFrame($request));
    }

    public function test_pull_frame_src_builds_url_from_flashed_route(): void
    {
        session()->flash('form_drawer_open', [
            'route' => 'horizon.services.create',
            'params' => [],
        ]);

        $this->assertSame(route('horizon.services.create'), FormDrawer::pullFrameSrc());
        $this->assertNull(FormDrawer::pullFrameSrc());
    }

    public function test_pull_frame_src_returns_null_for_invalid_route(): void
    {
        session()->flash('form_drawer_open', [
            'route' => 'not.a.real.route',
            'params' => [],
        ]);

        $this->assertNull(FormDrawer::pullFrameSrc());
    }
}
