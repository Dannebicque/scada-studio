<?php
declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Optional UNS topic prefix on data sources for hierarchical MQTT topics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE data_source ADD topic_prefix VARCHAR(512) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_source_topic_prefix ON data_source (topic_prefix)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_source_topic_prefix');
        $this->addSql('ALTER TABLE data_source DROP topic_prefix');
    }
}
