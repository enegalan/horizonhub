<?php

use App\Models\Alert;
use App\Services\Alerts\Rules\Strategies\FailureCount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Merge redundant job failure rule types into failure_count (same semantics via Job optional + threshold).
     */
    public function up(): void
    {
        Alert::whereIn('rule_type', ['job_specific_failure', 'job_type_failure'])
            ->orderBy('id')
            ->chunkById(100, function ($alerts): void {
                /** @var Alert $alert */
                foreach ($alerts as $alert) {
                    $threshold = $alert->threshold;
                    if (! \is_array($threshold)) {
                        $threshold = [];
                    }
                    $count = (int) ($threshold['count'] ?? config('horizonhub.alerts.default_count'));
                    $threshold['count'] = $count;
                    $minutes = (int) ($threshold['minutes'] ?? config('horizonhub.alerts.default_minutes'));
                    $threshold['minutes'] = $minutes;

                    $alert->rule_type = FailureCount::type();
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
