<?php

use App\Models\Alert;
use App\Models\Service;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $enabledServiceIds = Service::enabled()->pluck('id')->all();

        if (empty($enabledServiceIds)) {
            return;
        }

        \sort($enabledServiceIds);

        Alert::orderBy('id')
            ->each(function (Alert $alert) use ($enabledServiceIds): void {
                if (! empty($alert->service_ids)) {
                    return;
                }

                $alert->forceFill(['service_ids' => $enabledServiceIds])->saveQuietly();
            });
    }

    public function down(): void
    {
        // Intentionally irreversible: empty scope no longer means all services.
    }
};
