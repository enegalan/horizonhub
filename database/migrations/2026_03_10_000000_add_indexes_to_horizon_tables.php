<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('horizon_jobs', function (Blueprint $table): void {
            if (! $this->indexExists('horizon_jobs', 'idx_horizon_jobs_status_processed_at')) {
                $table->index(['status', 'processed_at'], 'idx_horizon_jobs_status_processed_at');
            }
            if (! $this->indexExists('horizon_jobs', 'idx_horizon_jobs_failed_at')) {
                $table->index('failed_at', 'idx_horizon_jobs_failed_at');
            }
            if (! $this->indexExists('horizon_jobs', 'idx_horizon_jobs_service_id')) {
                $table->index('service_id', 'idx_horizon_jobs_service_id');
            }
            if (! $this->indexExists('horizon_jobs', 'idx_horizon_jobs_queue')) {
                $table->index('queue', 'idx_horizon_jobs_queue');
            }
        });

        Schema::table('horizon_failed_jobs', function (Blueprint $table): void {
            if (! $this->indexExists('horizon_failed_jobs', 'idx_horizon_failed_jobs_failed_at')) {
                $table->index('failed_at', 'idx_horizon_failed_jobs_failed_at');
            }
            if (! $this->indexExists('horizon_failed_jobs', 'idx_horizon_failed_jobs_service_id')) {
                $table->index('service_id', 'idx_horizon_failed_jobs_service_id');
            }
            if (! $this->indexExists('horizon_failed_jobs', 'idx_horizon_failed_jobs_queue')) {
                $table->index('queue', 'idx_horizon_failed_jobs_queue');
            }
        });
    }

    public function down(): void {
        Schema::table('horizon_jobs', function (Blueprint $table): void {
            if ($this->indexExists('horizon_jobs', 'idx_horizon_jobs_status_processed_at')) {
                $table->dropIndex('idx_horizon_jobs_status_processed_at');
            }
            if ($this->indexExists('horizon_jobs', 'idx_horizon_jobs_failed_at')) {
                $table->dropIndex('idx_horizon_jobs_failed_at');
            }
            if ($this->indexExists('horizon_jobs', 'idx_horizon_jobs_service_id')) {
                $table->dropIndex('idx_horizon_jobs_service_id');
            }
            if ($this->indexExists('horizon_jobs', 'idx_horizon_jobs_queue')) {
                $table->dropIndex('idx_horizon_jobs_queue');
            }
        });

        Schema::table('horizon_failed_jobs', function (Blueprint $table): void {
            if ($this->indexExists('horizon_failed_jobs', 'idx_horizon_failed_jobs_failed_at')) {
                $table->dropIndex('idx_horizon_failed_jobs_failed_at');
            }
            if ($this->indexExists('horizon_failed_jobs', 'idx_horizon_failed_jobs_service_id')) {
                $table->dropIndex('idx_horizon_failed_jobs_service_id');
            }
            if ($this->indexExists('horizon_failed_jobs', 'idx_horizon_failed_jobs_queue')) {
                $table->dropIndex('idx_horizon_failed_jobs_queue');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool {
        $connection = Schema::getConnection()->getName();
        $database = Schema::getConnection()->getDatabaseName();

        if ($connection === 'mysql') {
            $result = DB::selectOne(
                'SELECT COUNT(*) AS cnt FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
                [$database, $table, $indexName]
            );

            return (int) ($result->cnt ?? 0) > 0;
        }

        return false;
    }
};
