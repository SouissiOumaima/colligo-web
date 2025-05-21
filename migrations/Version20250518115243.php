<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250518115243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child MODIFY childId INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `primary` ON child
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child CHANGE childId child_id INT AUTO_INCREMENT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child ADD PRIMARY KEY (child_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child RENAME INDEX idx_parentid TO IDX_22B3542910EE4CEE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP FOREIGN KEY FK_level_childId
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_childId ON level
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `primary` ON level
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP childId
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD PRIMARY KEY (id, gameId)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level RENAME INDEX idx_gameid TO IDX_9AEACC13EC55B7A4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents MODIFY parentId INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `primary` ON parents
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents CHANGE is_verified is_verified TINYINT(1) NOT NULL, CHANGE parentId parent_id INT AUTO_INCREMENT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents ADD PRIMARY KEY (parent_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents RENAME INDEX uniq_email TO UNIQ_FD501D6AE7927C74
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE themes CHANGE is_validated is_validated TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE words RENAME INDEX idx_theme_id TO IDX_717D1E8C59027487
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child MODIFY child_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `PRIMARY` ON child
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child CHANGE child_id childId INT AUTO_INCREMENT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child ADD PRIMARY KEY (childId)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child RENAME INDEX idx_22b3542910ee4cee TO IDX_parentId
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents MODIFY parent_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `PRIMARY` ON parents
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents CHANGE is_verified is_verified TINYINT(1) DEFAULT 0 NOT NULL, CHANGE parent_id parentId INT AUTO_INCREMENT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents ADD PRIMARY KEY (parentId)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents RENAME INDEX uniq_fd501d6ae7927c74 TO UNIQ_email
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `PRIMARY` ON level
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD childId INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD CONSTRAINT FK_level_childId FOREIGN KEY (childId) REFERENCES child (childId) ON UPDATE NO ACTION ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_childId ON level (childId)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD PRIMARY KEY (id, childId, gameId)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level RENAME INDEX idx_9aeacc13ec55b7a4 TO IDX_gameId
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE words RENAME INDEX idx_717d1e8c59027487 TO IDX_theme_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE themes CHANGE is_validated is_validated TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
    }
}
