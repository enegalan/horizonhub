<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->dropColumn('job_id');
        });
    }

    public function down(): void
    {
        Schema::table('alert_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('job_id')->nullable()->after('alert_id');
        });
    }
};
