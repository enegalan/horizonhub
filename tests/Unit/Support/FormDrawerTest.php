<?php

namespace Tests\Unit\Support;

use App\Support\FormDrawer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class FormDrawerTest extends TestCase
{
    public function test_complete_redirect_sets_turbo_frame_top_for_drawer_submissions(): void
    {
        $request = Request::create('/horizon/services', 'POST');
        $request->headers->set('Turbo-Frame', FormDrawer::FRAME_ID);

        $response = FormDrawer::onRedirect(
            redirect()->route('horizon.services.index'),
            $request,
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('_top', $response->headers->get('Turbo-Frame'));
    }

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
}
