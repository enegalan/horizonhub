<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->unsignedInteger('trigger_count')->default(1)->after('service_id');
            $table->json('job_uuids')->nullable()->after('trigger_count');
        });
    }

    public function down(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->dropColumn(['trigger_count', 'job_uuids']);
        });
    }
};
