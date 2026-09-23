<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('tls_client_mode', 16)->nullable()->after('tags');
            $table->string('tls_client_cert_path')->nullable()->after('tls_client_mode');
            $table->string('tls_client_key_path')->nullable()->after('tls_client_cert_path');
            $table->text('tls_client_passphrase')->nullable()->after('tls_client_key_path');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'tls_client_mode',
                'tls_client_cert_path',
                'tls_client_key_path',
                'tls_client_passphrase',
            ]);
        });
    }
};
