<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('horizon_jobs')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update(['status' => 'unknown']);

        DB::table('horizon_jobs')
            ->whereNull('attempts')
            ->update(['attempts' => 0]);
    }

    public function down(): void
    {
        // Cannot reverse backfill
    }
};
