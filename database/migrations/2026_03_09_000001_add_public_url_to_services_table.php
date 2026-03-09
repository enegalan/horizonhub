<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('public_url')->nullable()->after('base_url');
        });
    }

    public function down(): void {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('public_url');
        });
    }
};
