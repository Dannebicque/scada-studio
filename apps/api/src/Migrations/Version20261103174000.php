<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103174000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial SCADA Studio workspace, source, unit and tag catalog';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()::class !== \Doctrine\DBAL\Platforms\PostgreSQLPlatform::class, 'Migration only supported on PostgreSQL.');

        $this->addSql('CREATE TABLE workspace (id VARCHAR(36) NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(100) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_workspace_slug ON workspace (slug)');
        $this->addSql('CREATE TABLE engineering_unit (code VARCHAR(40) NOT NULL, label VARCHAR(100) NOT NULL, symbol VARCHAR(30) NOT NULL, PRIMARY KEY(code))');
        $this->addSql('CREATE TABLE data_source (id VARCHAR(36) NOT NULL, workspace_id VARCHAR(36) NOT NULL, source_key VARCHAR(100) NOT NULL, name VARCHAR(150) NOT NULL, connector_type VARCHAR(100) NOT NULL, enabled BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_data_source_workspace ON data_source (workspace_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_source_workspace_key ON data_source (workspace_id, source_key)');
        $this->addSql('ALTER TABLE data_source ADD CONSTRAINT fk_data_source_workspace FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE tag (id VARCHAR(36) NOT NULL, source_id VARCHAR(36) NOT NULL, unit_code VARCHAR(40) DEFAULT NULL, tag_key VARCHAR(150) NOT NULL, name VARCHAR(150) NOT NULL, value_type VARCHAR(20) NOT NULL, read_only BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_tag_source ON tag (source_id)');
        $this->addSql('CREATE INDEX idx_tag_unit ON tag (unit_code)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tag_source_key ON tag (source_id, tag_key)');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT fk_tag_source FOREIGN KEY (source_id) REFERENCES data_source (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag ADD CONSTRAINT fk_tag_unit FOREIGN KEY (unit_code) REFERENCES engineering_unit (code) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE data_source');
        $this->addSql('DROP TABLE engineering_unit');
        $this->addSql('DROP TABLE workspace');
    }
}
