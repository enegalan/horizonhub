<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_notification_provider', function (Blueprint $table) {
            if (! Schema::hasColumn('alert_notification_provider', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('alert_notification_provider', function (Blueprint $table) {
            if (Schema::hasColumn('alert_notification_provider', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
