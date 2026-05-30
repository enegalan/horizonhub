<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropIndex(['queue']);
            $table->dropIndex(['job_type']);
            $table->dropColumn(['queue', 'job_type']);
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->string('queue')->nullable()->index()->after('threshold');
            $table->string('job_type')->nullable()->index()->after('queue');
        });
    }
};
