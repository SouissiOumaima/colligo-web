<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250516115305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE game (id INT NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE level (id INT NOT NULL, score INT NOT NULL, nbtries INT NOT NULL, time INT NOT NULL, childId INT NOT NULL, gameId INT NOT NULL, INDEX IDX_9AEACC132FD6B47 (childId), INDEX IDX_9AEACC13EC55B7A4 (gameId), PRIMARY KEY(id, childId, gameId)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE themes (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, language VARCHAR(2) NOT NULL, level VARCHAR(50) NOT NULL, stage INT NOT NULL, is_validated TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE words (id INT AUTO_INCREMENT NOT NULL, theme_id INT NOT NULL, word VARCHAR(255) NOT NULL, synonym VARCHAR(255) NOT NULL, INDEX IDX_717D1E8C59027487 (theme_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD CONSTRAINT FK_9AEACC132FD6B47 FOREIGN KEY (childId) REFERENCES child (childId) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD CONSTRAINT FK_9AEACC13EC55B7A4 FOREIGN KEY (gameId) REFERENCES game (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE words ADD CONSTRAINT FK_717D1E8C59027487 FOREIGN KEY (theme_id) REFERENCES themes (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child MODIFY id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child DROP FOREIGN KEY FK_22B35429B706B6D3
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_22B35429B706B6D3 ON child
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `primary` ON child
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child ADD age INT NOT NULL, ADD language VARCHAR(50) NOT NULL, ADD avatar VARCHAR(255) NOT NULL, ADD parentId INT DEFAULT NULL, DROP id, CHANGE parents_id child_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child ADD CONSTRAINT FK_22B3542910EE4CEE FOREIGN KEY (parentId) REFERENCES parents (parentId) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_22B3542910EE4CEE ON child (parentId)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child ADD PRIMARY KEY (child_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents MODIFY id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `primary` ON parents
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents CHANGE id parent_id INT AUTO_INCREMENT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents ADD PRIMARY KEY (parent_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP FOREIGN KEY FK_9AEACC132FD6B47
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP FOREIGN KEY FK_9AEACC13EC55B7A4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE words DROP FOREIGN KEY FK_717D1E8C59027487
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE game
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE level
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE themes
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE words
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child DROP FOREIGN KEY FK_22B3542910EE4CEE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_22B3542910EE4CEE ON child
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child ADD id INT AUTO_INCREMENT NOT NULL, ADD parents_id INT NOT NULL, DROP child_id, DROP age, DROP language, DROP avatar, DROP parentId, DROP PRIMARY KEY, ADD PRIMARY KEY (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE child ADD CONSTRAINT FK_22B35429B706B6D3 FOREIGN KEY (parents_id) REFERENCES parents (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_22B35429B706B6D3 ON child (parents_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents MODIFY parent_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `PRIMARY` ON parents
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents CHANGE parent_id id INT AUTO_INCREMENT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents ADD PRIMARY KEY (id)
        SQL);
    }
}
