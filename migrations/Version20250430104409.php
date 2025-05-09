<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250430104409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create new tables and update existing schema for child, level, and other entities';
    }

    public function up(Schema $schema): void
    {
        // Create new tables with IF NOT EXISTS to avoid errors if they already exist
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS dragdrop (id INT NOT NULL, phrase LONGTEXT NOT NULL, niveau INT NOT NULL, langue VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS images (id INT NOT NULL, word VARCHAR(255) NOT NULL, image_url VARCHAR(512) NOT NULL, french_translation VARCHAR(45) NOT NULL, spanish_translation VARCHAR(45) NOT NULL, german_translation VARCHAR(45) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS jeudedevinette (id INT NOT NULL, rightword VARCHAR(255) NOT NULL, wrongword VARCHAR(255) NOT NULL, language VARCHAR(50) NOT NULL, level VARCHAR(50) NOT NULL, thème VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS matching_game (id INT NOT NULL, langue VARCHAR(50) NOT NULL, niveau VARCHAR(20) NOT NULL, words LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        // Rename indexes on admin and parents tables
        $this->addSql(<<<'SQL'
            ALTER TABLE `admin` RENAME INDEX email TO UNIQ_49CF2272E7927C74
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE parents RENAME INDEX email TO UNIQ_32ED24F6E7927C74
        SQL);

        // Update child table
        $this->addSql(<<<'SQL'
            ALTER TABLE child RENAME INDEX FK_22B3542910EE4CEE TO IDX_22B3542910EE4CEE
        SQL);

        // Update fill_in_the_blank table
        $this->addSql(<<<'SQL'
            ALTER TABLE fill_in_the_blank CHANGE questionText questionText VARCHAR(255) NOT NULL, CHANGE correctAnswer correctAnswer VARCHAR(100) NOT NULL, CHANGE theme theme VARCHAR(100) NOT NULL
        SQL);

        // Update game table
        $this->addSql(<<<'SQL'
            ALTER TABLE game CHANGE id id INT NOT NULL, CHANGE name name VARCHAR(50) NOT NULL
        SQL);

        // Update level table
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP FOREIGN KEY level_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP FOREIGN KEY level_ibfk_2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level CHANGE time time INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD CONSTRAINT FK_9AEACC132FD6B47 FOREIGN KEY (childId) REFERENCES child (childId) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD CONSTRAINT FK_9AEACC13EC55B7A4 FOREIGN KEY (gameId) REFERENCES game (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level RENAME INDEX level_ibfk_1 TO IDX_9AEACC132FD6B47
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level RENAME INDEX level_ibfk_2 TO IDX_9AEACC13EC55B7A4
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Drop new tables
        $this->addSql(<<<'SQL'
            DROP TABLE dragdrop
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE images
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE jeudedevinette
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE matching_game
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);

        // Revert index renaming on admin and parents
        $this->addSql(<<<'SQL'
            ALTER TABLE `Admin` RENAME INDEX uniq_49cf2272e7927c74 TO email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE Parents RENAME INDEX uniq_32ed24f6e7927c74 TO email
        SQL);

        // Revert child table changes
        $this->addSql(<<<'SQL'
            ALTER TABLE child RENAME INDEX IDX_22B3542910EE4CEE TO FK_22B3542910EE4CEE
        SQL);

        // Revert fill_in_the_blank changes
        $this->addSql(<<<'SQL'
            ALTER TABLE fill_in_the_blank CHANGE questionText questionText TEXT NOT NULL, CHANGE correctAnswer correctAnswer VARCHAR(255) NOT NULL, CHANGE theme theme VARCHAR(50) NOT NULL
        SQL);

        // Revert game changes
        $this->addSql(<<<'SQL'
            ALTER TABLE game CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE name name VARCHAR(255) NOT NULL
        SQL);

        // Revert level changes
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP FOREIGN KEY FK_9AEACC132FD6B47
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP FOREIGN KEY FK_9AEACC13EC55B7A4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level CHANGE time time INT DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD CONSTRAINT level_ibfk_1 FOREIGN KEY (childId) REFERENCES child (childId) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD CONSTRAINT level_ibfk_2 FOREIGN KEY (gameId) REFERENCES game (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level RENAME INDEX idx_9aeacc132fd6b47 TO level_ibfk_1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level RENAME INDEX idx_9aeacc13ec55b7a4 TO level_ibfk_2
        SQL);
    }
}