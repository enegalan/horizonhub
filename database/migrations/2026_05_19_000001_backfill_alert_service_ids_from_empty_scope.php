<?php

use App\Models\Alert;
use App\Models\Service;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $enabledServiceIds = Service::query()->enabled()->pluck('id')->all();

        if ($enabledServiceIds === []) {
            return;
        }

        \sort($enabledServiceIds);

        Alert::query()
            ->orderBy('id')
            ->each(function (Alert $alert) use ($enabledServiceIds): void {
                if ($alert->service_ids !== []) {
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
