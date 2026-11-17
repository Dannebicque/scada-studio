<?php
declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Numeric tag history (tag_sample) and last history sample time on tag_value';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tag_sample (tag_id VARCHAR(36) NOT NULL, observed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, value DOUBLE PRECISION NOT NULL, PRIMARY KEY(tag_id, observed_at))');
        $this->addSql('CREATE INDEX idx_tag_sample_observed_at ON tag_sample (observed_at)');
        $this->addSql('ALTER TABLE tag_sample ADD CONSTRAINT FK_TAG_SAMPLE_TAG FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tag_value ADD sampled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag_value DROP sampled_at');
        $this->addSql('DROP TABLE tag_sample');
    }
}
