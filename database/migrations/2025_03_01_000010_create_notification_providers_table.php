<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->index();
            $table->json('config');
            $table->timestamps();
        });

        Schema::create('alert_notification_provider', function (Blueprint $table) {
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_provider_id')->constrained()->cascadeOnDelete();
            $table->primary(['alert_id', 'notification_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_notification_provider');
        Schema::dropIfExists('notification_providers');
    }
};
