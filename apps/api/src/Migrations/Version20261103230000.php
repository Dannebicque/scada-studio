<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persistent user identities, workspace memberships, private dashboards and user tag preferences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE app_user (id VARCHAR(36) NOT NULL, email VARCHAR(180) NOT NULL, password_hash VARCHAR(255) NOT NULL, roles JSON NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_app_user_email ON app_user (email)');
        $this->addSql("CREATE TABLE workspace_member (workspace_id VARCHAR(36) NOT NULL, user_id VARCHAR(36) NOT NULL, role VARCHAR(20) NOT NULL, PRIMARY KEY(workspace_id, user_id))");
        $this->addSql('ALTER TABLE workspace_member ADD CONSTRAINT fk_workspace_member_workspace FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workspace_member ADD CONSTRAINT fk_workspace_member_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_workspace_member_user ON workspace_member (user_id)');
        $this->addSql("CREATE TABLE user_dashboard (id VARCHAR(36) NOT NULL, workspace_id VARCHAR(36) NOT NULL, owner_id VARCHAR(36) NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(120) NOT NULL, widgets JSON NOT NULL, PRIMARY KEY(id))");
        $this->addSql('ALTER TABLE user_dashboard ADD CONSTRAINT fk_user_dashboard_workspace FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_dashboard ADD CONSTRAINT fk_user_dashboard_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_dashboard_slug ON user_dashboard (workspace_id, owner_id, slug)');
        $this->addSql('CREATE INDEX idx_user_dashboard_owner ON user_dashboard (owner_id)');
        $this->addSql("CREATE TABLE user_tag (id VARCHAR(36) NOT NULL, owner_id VARCHAR(36) NOT NULL, tag_id VARCHAR(36) NOT NULL, display_name VARCHAR(150) DEFAULT NULL, favorite BOOLEAN NOT NULL, PRIMARY KEY(id))");
        $this->addSql('ALTER TABLE user_tag ADD CONSTRAINT fk_user_tag_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_tag ADD CONSTRAINT fk_user_tag_tag FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_tag_owner_tag ON user_tag (owner_id, tag_id)');
        $this->addSql('CREATE INDEX idx_user_tag_tag ON user_tag (tag_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_tag');
        $this->addSql('DROP TABLE user_dashboard');
        $this->addSql('DROP TABLE workspace_member');
        $this->addSql('DROP TABLE app_user');
    }
}
