<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horizon_jobs', function (Blueprint $table) {
            $table->index(array('service_id', 'status', 'processed_at'), 'horizon_jobs_service_status_processed_index');
        });

        Schema::table('alert_logs', function (Blueprint $table) {
            $table->index(array('alert_id', 'sent_at', 'status'), 'alert_logs_alert_sent_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('horizon_jobs', function (Blueprint $table) {
            $table->dropIndex('horizon_jobs_service_status_processed_index');
        });

        Schema::table('alert_logs', function (Blueprint $table) {
            $table->dropIndex('alert_logs_alert_sent_status_index');
        });
    }
};
