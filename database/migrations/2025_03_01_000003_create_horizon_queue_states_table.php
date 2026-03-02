<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_queue_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('queue', 255);
            $table->boolean('is_paused')->default(false);
            $table->timestamps();
            $table->unique(['service_id', 'queue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_queue_states');
    }
};
