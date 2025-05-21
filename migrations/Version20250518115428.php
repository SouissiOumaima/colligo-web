<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250518115428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE child (child_id INT AUTO_INCREMENT NOT NULL, age INT NOT NULL, language VARCHAR(50) NOT NULL, avatar VARCHAR(255) NOT NULL, name VARCHAR(100) NOT NULL, parentId INT DEFAULT NULL, INDEX IDX_22B3542910EE4CEE (parentId), PRIMARY KEY(child_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE game (id INT NOT NULL, name VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE level (id INT NOT NULL, score INT NOT NULL, nbtries INT NOT NULL, time INT NOT NULL, gameId INT NOT NULL, INDEX IDX_9AEACC13EC55B7A4 (gameId), PRIMARY KEY(id, gameId)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE parents (parent_id INT AUTO_INCREMENT NOT NULL, email VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, verification_code VARCHAR(6) DEFAULT NULL, is_verified TINYINT(1) NOT NULL, signup_token VARCHAR(255) DEFAULT NULL, reset_code VARCHAR(6) DEFAULT NULL, reset_password_token VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_FD501D6AE7927C74 (email), PRIMARY KEY(parent_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
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
            ALTER TABLE child ADD CONSTRAINT FK_22B3542910EE4CEE FOREIGN KEY (parentId) REFERENCES parents (parentId) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD CONSTRAINT FK_9AEACC13EC55B7A4 FOREIGN KEY (gameId) REFERENCES game (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE words ADD CONSTRAINT FK_717D1E8C59027487 FOREIGN KEY (theme_id) REFERENCES themes (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE child DROP FOREIGN KEY FK_22B3542910EE4CEE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP FOREIGN KEY FK_9AEACC13EC55B7A4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE words DROP FOREIGN KEY FK_717D1E8C59027487
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE child
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE game
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE level
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE parents
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
    }
}
