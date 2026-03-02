<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horizon_supervisor_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->unique(['service_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horizon_supervisor_states');
    }
};
