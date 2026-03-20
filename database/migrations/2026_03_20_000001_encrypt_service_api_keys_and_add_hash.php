<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('api_key_hash', 64)->nullable()->after('api_key');
        });

        $rows = DB::table('services')->select('id', 'api_key')->get();
        foreach ($rows as $row) {
            if ($row->api_key === null || $row->api_key === '') {
                continue;
            }
            $plain = (string) $row->api_key;
            DB::table('services')->where('id', $row->id)->update([
                'api_key_hash' => hash('sha256', $plain),
                'api_key' => Crypt::encryptString($plain),
            ]);
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['api_key']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->text('api_key')->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('api_key_hash', 64)->nullable(false)->change();
            $table->unique('api_key_hash');
        });
    }

    public function down(): void
    {
        $rows = DB::table('services')->select('id', 'api_key')->get();
        foreach ($rows as $row) {
            if ($row->api_key === null || $row->api_key === '') {
                continue;
            }
            try {
                $plain = Crypt::decryptString((string) $row->api_key);
            } catch (Throwable $e) {
                continue;
            }
            DB::table('services')->where('id', $row->id)->update([
                'api_key' => $plain,
            ]);
        }

        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique(['api_key_hash']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('api_key_hash');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('api_key', 64)->change();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unique('api_key');
        });
    }
};
