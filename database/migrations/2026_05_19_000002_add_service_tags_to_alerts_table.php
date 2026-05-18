<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->json('service_tags')->nullable()->after('service_ids');
        });

        DB::table('alerts')
            ->whereNull('service_tags')
            ->update(['service_tags' => '[]']);
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropColumn('service_tags');
        });
    }
};
