<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250504224519 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE game_results (id INT AUTO_INCREMENT NOT NULL, child_id INT NOT NULL, game_id INT NOT NULL, total_score INT NOT NULL, total_attempts_used INT NOT NULL, total_time_spent INT NOT NULL, language VARCHAR(10) NOT NULL, level VARCHAR(50) NOT NULL, played_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE themes (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, language VARCHAR(10) NOT NULL, level VARCHAR(50) NOT NULL, stage SMALLINT UNSIGNED DEFAULT 1 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE words (id INT AUTO_INCREMENT NOT NULL, theme_id INT NOT NULL, word VARCHAR(255) NOT NULL, synonym VARCHAR(255) NOT NULL, INDEX IDX_717D1E8C59027487 (theme_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', available_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', delivered_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE words ADD CONSTRAINT FK_717D1E8C59027487 FOREIGN KEY (theme_id) REFERENCES themes (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE words DROP FOREIGN KEY FK_717D1E8C59027487
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE game_results
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
