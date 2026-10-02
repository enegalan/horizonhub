<?php

namespace Tests\Feature;

use App\Enums\NotificationProviderType;
use App\Models\NotificationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormDrawerDiscardConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_save_button_stays_blocked_when_there_are_no_providers(): void
    {
        $this->get(route('horizon.alerts.create'), ['Turbo-Frame' => 'form-drawer'])
            ->assertOk()
            ->assertSee('data-form-drawer-submit-blocked="true"', false)
            ->assertSee('disabled', false);

        NotificationProvider::create([
            'name' => 'mail',
            'type' => NotificationProviderType::Email->value,
            'config' => ['to' => ['ops@example.test']],
        ]);

        $this->get(route('horizon.alerts.create'), ['Turbo-Frame' => 'form-drawer'])
            ->assertOk()
            ->assertSee('data-form-drawer-submit-blocked="false"', false);
    }

    public function test_form_drawer_discard_confirmation_offers_discard_and_keep_editing_actions(): void
    {
        $this->get(route('horizon.services.index'))
            ->assertOk()
            ->assertSee('Keep editing', false)
            ->assertSee('@click="$dispatch(\'close-modal\')"', false)
            ->assertSee('data-form-drawer-discard', false);
    }

    public function test_form_drawer_renders_the_discard_confirmation_modal(): void
    {
        $this->get(route('horizon.services.index'))
            ->assertOk()
            ->assertSee('Discard unsaved changes?', false)
            ->assertSee('x-show="showFormDrawerDiscardModal"', false)
            ->assertSee('x-on:form-drawer-discard-request.window="showFormDrawerDiscardModal = true"', false)
            ->assertSee('x-on:close-modal.window="showFormDrawerDiscardModal = false"', false);
    }

    public function test_form_drawer_save_buttons_are_tracked_for_the_unsaved_changes_check(): void
    {
        $this->get(route('horizon.alerts.create'), ['Turbo-Frame' => 'form-drawer'])
            ->assertOk()
            ->assertSee('data-form-drawer-submit', false);

        $this->get(route('horizon.services.create'), ['Turbo-Frame' => 'form-drawer'])
            ->assertOk()
            ->assertSee('data-form-drawer-submit', false);

        $this->get(route('horizon.providers.create'), ['Turbo-Frame' => 'form-drawer'])
            ->assertOk()
            ->assertSee('data-form-drawer-submit', false);
    }

    public function test_form_drawer_shell_exposes_the_discard_confirmation_state(): void
    {
        $this->get(route('horizon.services.index'))
            ->assertOk()
            ->assertSee('x-data="{ showFormDrawerDiscardModal: false }"', false);
    }
}
