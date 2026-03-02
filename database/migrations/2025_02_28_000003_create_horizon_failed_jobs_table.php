<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('job_uuid')->index();
            $table->string('queue')->index();
            $table->json('payload')->nullable();
            $table->longText('exception')->nullable();
            $table->timestamp('failed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_failed_jobs');
    }
};
