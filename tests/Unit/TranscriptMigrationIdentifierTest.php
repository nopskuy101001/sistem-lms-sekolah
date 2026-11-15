<?php

namespace Tests\Unit;

use Tests\TestCase;

class TranscriptMigrationIdentifierTest extends TestCase
{
    public function test_transcript_activity_index_uses_a_mysql_safe_explicit_name(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_10_23_000001_create_transcript_validation_tables.php'));
        $indexName = 'transcript_override_activity_idx';

        $this->assertIsString($migration);
        $this->assertLessThanOrEqual(64, strlen($indexName));
        $this->assertStringContainsString(
            "->index(['classroom_id', 'activity_type', 'activity_id'], '".$indexName."')",
            $migration
        );
        $this->assertStringNotContainsString(
            "->index(['classroom_id', 'activity_type', 'activity_id']);",
            $migration
        );
    }

    public function test_migration_keeps_partial_mysql_ddl_recovery_guards(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_10_23_000001_create_transcript_validation_tables.php'));

        $this->assertIsString($migration);
        $this->assertStringContainsString("Schema::hasTable('transcript_score_overrides')", $migration);
        $this->assertStringContainsString('private function ensureIndex(', $migration);
        $this->assertStringContainsString('Schema::getIndexes($table)', $migration);
        $this->assertStringContainsString('Tidak ada index yang dihapus.', $migration);
    }
}
