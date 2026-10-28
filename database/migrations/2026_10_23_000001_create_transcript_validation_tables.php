<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotent recovery from a partially completed MySQL DDL migration.
     * MySQL can keep created tables even when Laravel has not recorded the migration.
     * Never DROP, truncate, or overwrite pre-existing data.
     */
    public function up(): void
    {
        $schemas = [
            'transcript_states' => [
                'columns' => ['id', 'classroom_id', 'finalized_by', 'finalized_at', 'snapshot', 'created_at', 'updated_at'],
                'unique' => [['classroom_id']],
                'foreign' => [
                    'classroom_id' => 'classrooms',
                    'finalized_by' => 'users',
                ],
            ],
            'transcript_score_overrides' => [
                'columns' => ['id', 'classroom_id', 'user_id', 'activity_type', 'activity_id', 'score', 'reason', 'updated_by', 'created_at', 'updated_at'],
                'unique' => [['classroom_id', 'user_id', 'activity_type', 'activity_id']],
                'foreign' => [
                    'classroom_id' => 'classrooms',
                    'user_id' => 'users',
                    'updated_by' => 'users',
                ],
            ],
            'transcript_score_audits' => [
                'columns' => ['id', 'classroom_id', 'user_id', 'actor_id', 'activity_type', 'activity_id', 'action', 'before_score', 'after_score', 'reason', 'created_at', 'updated_at'],
                'unique' => [],
                'foreign' => [
                    'classroom_id' => 'classrooms',
                    'user_id' => 'users',
                    'actor_id' => 'users',
                ],
            ],
        ];

        // Verify ALL existing tables before any new CREATE operation.
        // An unrelated / altered table with the same name must not be silently adopted.
        foreach ($schemas as $table => $requirements) {
            if (Schema::hasTable($table)) {
                $this->assertCompatible($table, $requirements);
            }
        }

        if (! Schema::hasTable('transcript_states')) {
            Schema::create('transcript_states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('classroom_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('transcript_score_overrides')) {
            Schema::create('transcript_score_overrides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('activity_type', 20);
                $table->unsignedBigInteger('activity_id');
                $table->decimal('score', 8, 2);
                $table->string('reason', 500)->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['classroom_id', 'user_id', 'activity_type', 'activity_id'], 'transcript_override_unique');
                $table->index(['classroom_id', 'activity_type', 'activity_id']);
            });
        }

        if (! Schema::hasTable('transcript_score_audits')) {
            Schema::create('transcript_score_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('activity_type', 20);
                $table->unsignedBigInteger('activity_id');
                $table->string('action', 20);
                $table->decimal('before_score', 8, 2)->nullable();
                $table->decimal('after_score', 8, 2)->nullable();
                $table->string('reason', 500)->nullable();
                $table->timestamps();
            });
        }

        foreach ($schemas as $table => $requirements) {
            $this->assertCompatible($table, $requirements);
        }
    }

    /**
     * Require expected columns, unique keys and foreign keys to prevent
     * accidentally treating an unrelated existing table as this module's table.
     */
    private function assertCompatible(string $name, array $requirements): void
    {
        $existingColumns = array_map('strtolower', Schema::getColumnListing($name));
        $missing = array_diff($requirements['columns'], $existingColumns);
        if ($missing !== []) {
            throw new \RuntimeException('TRANSKRIP_DB_SCHEMA_MISMATCH: tabel ' . $name
                . ' sudah ada tetapi kolom berikut tidak ditemukan: ' . implode(', ', $missing)
                . '. Tidak ada tabel dihapus. Periksa schema secara manual sebelum mengulang upgrade.');
        }

        $indexes = Schema::getIndexes($name);
        foreach ($requirements['unique'] as $wanted) {
            $found = false;
            foreach ($indexes as $index) {
                $columns = array_map('strtolower', $index['columns'] ?? []);
                if (($index['unique'] ?? false) && $columns === $wanted) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw new \RuntimeException('TRANSKRIP_DB_SCHEMA_MISMATCH: tabel ' . $name
                    . ' tidak memiliki unique index pada (' . implode(', ', $wanted)
                    . '). Tidak ada perubahan otomatis pada tabel yang sudah ada.');
            }
        }

        $foreignKeys = Schema::getForeignKeys($name);
        foreach ($requirements['foreign'] as $column => $targetTable) {
            $found = false;
            foreach ($foreignKeys as $key) {
                $columns = array_map('strtolower', $key['columns'] ?? []);
                $referenced = strtolower((string) ($key['foreign_table'] ?? ''));
                // Some drivers include the schema/database name before the table.
                $referenced = substr($referenced, (int) (strrpos($referenced, '.') === false ? 0 : strrpos($referenced, '.') + 1));
                if ($columns === [$column] && $referenced === $targetTable) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw new \RuntimeException('TRANSKRIP_DB_SCHEMA_MISMATCH: tabel ' . $name
                    . ' tidak memiliki foreign key ' . $column . ' -> ' . $targetTable
                    . '. Tidak ada perubahan otomatis pada tabel yang sudah ada.');
            }
        }
    }

    public function down(): void
    {
        // The tables may predate the successful migration due to partial MySQL DDL.
        // Deleting them on rollback could destroy teacher-entered scores/snapshots.
        throw new \RuntimeException('Rollback otomatis migrasi transkrip diblokir untuk melindungi data.'
            . ' Gunakan prosedur pemulihan database terverifikasi jika diperlukan.');
    }
};
