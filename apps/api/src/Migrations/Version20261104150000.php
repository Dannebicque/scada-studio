<?php
declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UNS semantic assets and mappings to canonical tags';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE uns_asset (id VARCHAR(36) NOT NULL, workspace_id VARCHAR(36) NOT NULL, parent_id VARCHAR(36) DEFAULT NULL, asset_key VARCHAR(100) NOT NULL, name VARCHAR(150) NOT NULL, type VARCHAR(40) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_uns_asset_workspace ON uns_asset (workspace_id)');
        $this->addSql('CREATE INDEX idx_uns_asset_parent ON uns_asset (parent_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_uns_asset_parent_key ON uns_asset (workspace_id, parent_id, asset_key)');
        $this->addSql('ALTER TABLE uns_asset ADD CONSTRAINT fk_uns_asset_workspace FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE uns_asset ADD CONSTRAINT fk_uns_asset_parent FOREIGN KEY (parent_id) REFERENCES uns_asset (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE uns_asset_tag (asset_id VARCHAR(36) NOT NULL, tag_id VARCHAR(36) NOT NULL, PRIMARY KEY(asset_id, tag_id))');
        $this->addSql('CREATE INDEX idx_uns_asset_tag_tag ON uns_asset_tag (tag_id)');
        $this->addSql('ALTER TABLE uns_asset_tag ADD CONSTRAINT fk_uns_asset_tag_asset FOREIGN KEY (asset_id) REFERENCES uns_asset (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE uns_asset_tag ADD CONSTRAINT fk_uns_asset_tag_tag FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE uns_asset_tag');
        $this->addSql('DROP TABLE uns_asset');
    }
}
