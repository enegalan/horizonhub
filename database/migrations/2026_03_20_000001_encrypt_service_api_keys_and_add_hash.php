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
        if (! Schema::hasColumn('services', 'api_key_hash')) {
            Schema::table('services', function (Blueprint $table) {
                $table->string('api_key_hash', 64)->nullable()->after('api_key');
            });
        }

        if ($this->driverIsNotMysql() || $this->mysqlUniqueNonPrimaryIndexExistsOnColumn('api_key')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropUnique(['api_key']);
            });
        }

        Schema::table('services', function (Blueprint $table) {
            $table->text('api_key')->change();
        });

        $rows = DB::table('services')->select('id', 'api_key', 'api_key_hash')->get();
        foreach ($rows as $row) {
            if ($row->api_key === null || $row->api_key === '') {
                continue;
            }
            $apiKeyValue = (string) $row->api_key;
            try {
                $plain = Crypt::decryptString($apiKeyValue);
                $alreadyEncrypted = true;
            } catch (Throwable $e) {
                $plain = $apiKeyValue;
                $alreadyEncrypted = false;
            }

            $hash = hash('sha256', $plain);

            if ($alreadyEncrypted) {
                if ($row->api_key_hash === null || $row->api_key_hash === '') {
                    DB::table('services')->where('id', $row->id)->update([
                        'api_key_hash' => $hash,
                    ]);
                }

                continue;
            }

            DB::table('services')->where('id', $row->id)->update([
                'api_key_hash' => $hash,
                'api_key' => Crypt::encryptString($plain),
            ]);
        }

        Schema::table('services', function (Blueprint $table) {
            $table->string('api_key_hash', 64)->nullable(false)->change();
        });

        if ($this->driverIsNotMysql() || ! $this->mysqlUniqueNonPrimaryIndexExistsOnColumn('api_key_hash')) {
            Schema::table('services', function (Blueprint $table) {
                $table->unique('api_key_hash');
            });
        }
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

        if ($this->driverIsNotMysql() || $this->mysqlUniqueNonPrimaryIndexExistsOnColumn('api_key_hash')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropUnique(['api_key_hash']);
            });
        }

        if (Schema::hasColumn('services', 'api_key_hash')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('api_key_hash');
            });
        }

        Schema::table('services', function (Blueprint $table) {
            $table->string('api_key', 64)->change();
        });

        if ($this->driverIsNotMysql() || ! $this->mysqlUniqueNonPrimaryIndexExistsOnColumn('api_key')) {
            Schema::table('services', function (Blueprint $table) {
                $table->unique('api_key');
            });
        }
    }

    private function servicesPhysicalTableName(): string
    {
        return Schema::getConnection()->getTablePrefix().'services';
    }

    private function driverIsNotMysql(): bool
    {
        return Schema::getConnection()->getDriverName() !== 'mysql';
    }

    /**
     * MySQL only: true if a unique (non-primary) index exists on the column. Used for idempotent reruns.
     */
    private function mysqlUniqueNonPrimaryIndexExistsOnColumn(string $column): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', Schema::getConnection()->getDatabaseName())
            ->where('table_name', $this->servicesPhysicalTableName())
            ->where('column_name', $column)
            ->where('non_unique', 0)
            ->where('index_name', '!=', 'PRIMARY')
            ->exists();
    }
};
