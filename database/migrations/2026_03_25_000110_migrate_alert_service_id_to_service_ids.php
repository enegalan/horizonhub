<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('alerts')
            ->select(['id', 'service_id', 'service_ids'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $serviceIds = [];

                    if (\is_array($row->service_ids)) {
                        foreach ($row->service_ids as $value) {
                            if (\is_numeric($value) && (int) $value > 0) {
                                $serviceIds[(int) $value] = (int) $value;
                            }
                        }
                    } elseif (\is_string($row->service_ids) && $row->service_ids !== '') {
                        $decoded = \json_decode($row->service_ids, true);
                        if (\is_array($decoded)) {
                            foreach ($decoded as $value) {
                                if (\is_numeric($value) && (int) $value > 0) {
                                    $serviceIds[(int) $value] = (int) $value;
                                }
                            }
                        }
                    }

                    if (\is_numeric($row->service_id) && (int) $row->service_id > 0) {
                        $serviceIds[(int) $row->service_id] = (int) $row->service_id;
                    }

                    DB::table('alerts')
                        ->where('id', (int) $row->id)
                        ->update([
                            'service_ids' => \array_values($serviceIds),
                        ]);
                }
            }, 'id');

        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->foreignId('service_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('alerts')
            ->select(['id', 'service_ids'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $serviceIds = [];

                    if (\is_array($row->service_ids)) {
                        foreach ($row->service_ids as $value) {
                            if (\is_numeric($value) && (int) $value > 0) {
                                $serviceIds[(int) $value] = (int) $value;
                            }
                        }
                    } elseif (\is_string($row->service_ids) && $row->service_ids !== '') {
                        $decoded = \json_decode($row->service_ids, true);
                        if (\is_array($decoded)) {
                            foreach ($decoded as $value) {
                                if (\is_numeric($value) && (int) $value > 0) {
                                    $serviceIds[(int) $value] = (int) $value;
                                }
                            }
                        }
                    }

                    DB::table('alerts')
                        ->where('id', (int) $row->id)
                        ->update([
                            'service_id' => \array_values($serviceIds)[0] ?? null,
                        ]);
                }
            }, 'id');
    }
};
