<?php
declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Quality (good, uncertain, bad) of the latest value of a tag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tag_value ADD quality VARCHAR(10) DEFAULT 'good' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tag_value DROP quality');
    }
}
