<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align legacy catalog foreign-key index names with Doctrine ORM metadata';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration only supported on PostgreSQL.');

        foreach ([
            ['data_source', 'idx_data_source_workspace', 'IDX_3F744E6A82D40A1F'],
            ['tag', 'idx_tag_source', 'IDX_389B783953C1C61'],
            ['tag', 'idx_tag_unit', 'IDX_389B783FBD3D1C2'],
        ] as [$table, $old, $new]) {
            $exists = (bool) $this->connection->fetchOne(
                'SELECT EXISTS (SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?)',
                [$table, $old],
            );
            if ($exists) {
                $this->addSql(sprintf('ALTER INDEX "%s" RENAME TO "%s"', $old, $new));
            }
        }
    }

    public function down(Schema $schema): void
    {
        // The canonical index names are deliberately preserved on rollback.
    }
}
