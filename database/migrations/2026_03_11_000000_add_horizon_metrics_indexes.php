<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('horizon_failed_jobs', function (Blueprint $table): void {
            $table->index(
                ['failed_at', 'service_id', 'queue'],
                'horizon_failed_failed_service_queue_idx'
            );
        });
    }

    public function down(): void {
        Schema::table('horizon_failed_jobs', function (Blueprint $table): void {
            $table->dropIndex('horizon_failed_failed_service_queue_idx');
        });
    }
};
