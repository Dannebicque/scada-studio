<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add workspace dashboards and JSON widget layouts';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL only.');
        $this->addSql('CREATE TABLE dashboard (id VARCHAR(36) NOT NULL, workspace_id VARCHAR(36) NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, widgets JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_dashboard_workspace_slug ON dashboard (workspace_id, slug)');
        $this->addSql('ALTER TABLE dashboard ADD CONSTRAINT FK_DASHBOARD_WORKSPACE FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE dashboard');
    }
}
