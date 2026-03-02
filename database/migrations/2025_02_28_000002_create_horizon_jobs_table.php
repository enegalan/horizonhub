<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('job_uuid')->index();
            $table->string('queue')->index();
            $table->json('payload')->nullable();
            $table->string('status', 50)->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('name')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('horizon_jobs', function (Blueprint $table) {
            $table->unique(['service_id', 'job_uuid']);
            $table->index(['service_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_jobs');
    }
};
