<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('alerts')
            ->whereNull('service_ids')
            ->update(['service_ids' => '[]']);
    }

    public function down(): void
    {
        // No-op: existing rows keep their normalized service_ids values.
    }
};
