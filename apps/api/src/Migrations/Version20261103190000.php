<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store latest observed value for each SCADA tag';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'PostgreSQL only.');
        $this->addSql('CREATE TABLE tag_value (tag_id VARCHAR(36) NOT NULL, value JSON NOT NULL, observed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(tag_id))');
        $this->addSql('ALTER TABLE tag_value ADD CONSTRAINT FK_TAG_VALUE_TAG FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tag_value');
    }
}
