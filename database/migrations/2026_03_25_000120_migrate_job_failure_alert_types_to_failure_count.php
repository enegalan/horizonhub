<?php

use App\Models\Alert;
use App\Support\ConfigHelper;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Merge redundant job failure rule types into failure_count (same semantics via Job optional + threshold).
     */
    public function up(): void
    {
        Alert::query()
            ->whereIn('rule_type', ['job_specific_failure', 'job_type_failure'])
            ->orderBy('id')
            ->chunkById(100, function ($alerts): void {
                /** @var Alert $alert */
                foreach ($alerts as $alert) {
                    $threshold = $alert->threshold;
                    if (! \is_array($threshold)) {
                        $threshold = [];
                    }
                    $count = (int) ($threshold['count'] ?? ConfigHelper::getIntWithMin('horizonhub.alerts.default_count', 1));
                    $threshold['count'] = $count;
                    $minutes = (int) ($threshold['minutes'] ?? ConfigHelper::getIntWithMin('horizonhub.alerts.default_minutes', 1));
                    $threshold['minutes'] = $minutes;

                    $alert->rule_type = Alert::RULE_FAILURE_COUNT;
                    $alert->threshold = $threshold;
                    $alert->save();
                }
            });
    }

    /**
     * Cannot distinguish former job_specific_failure from job_type_failure rows.
     */
    public function down(): void {}
};
