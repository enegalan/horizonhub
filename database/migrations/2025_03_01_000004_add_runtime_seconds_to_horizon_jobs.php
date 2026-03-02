<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horizon_jobs', function (Blueprint $table) {
            $table->decimal('runtime_seconds', 10, 2)->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('horizon_jobs', function (Blueprint $table) {
            $table->dropColumn('runtime_seconds');
        });
    }
};
