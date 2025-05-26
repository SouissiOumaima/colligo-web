<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250521170453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        //$this->addSql('CREATE TABLE word_entry (id INT AUTO_INCREMENT NOT NULL, right_word VARCHAR(255) NOT NULL, wrong_word VARCHAR(255) NOT NULL, theme VARCHAR(255) NOT NULL, language VARCHAR(50) NOT NULL, level VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        //$this->addSql('DROP TABLE parent');
        $this->addSql('DROP INDEX `primary` ON `admin`');
        $this->addSql('ALTER TABLE `admin` CHANGE admin_id adminId INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_880E0D76E7927C74 ON `admin` (email)');
        $this->addSql('ALTER TABLE `admin` ADD PRIMARY KEY (adminId)');
        $this->addSql('ALTER TABLE child DROP FOREIGN KEY child_ibfk_1');
        //$this->addSql('ALTER TABLE child CHANGE childId childId INT NOT NULL, CHANGE parentId parentId INT DEFAULT NULL, CHANGE age age INT NOT NULL, CHANGE language language VARCHAR(50) NOT NULL, CHANGE avatar avatar VARCHAR(255) NOT NULL, CHANGE name name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE child ADD CONSTRAINT FK_22B3542910EE4CEE FOREIGN KEY (parentId) REFERENCES parents (parentId) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE child RENAME INDEX child_ibfk_1 TO IDX_22B3542910EE4CEE');
        $this->addSql('ALTER TABLE dragdrop CHANGE id id INT NOT NULL, CHANGE phrase phrase LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE fill_in_the_blank CHANGE questionText questionText VARCHAR(255) NOT NULL, CHANGE correctAnswer correctAnswer VARCHAR(100) NOT NULL, CHANGE level level INT NOT NULL, CHANGE language language VARCHAR(50) NOT NULL, CHANGE theme theme VARCHAR(100) NOT NULL, CHANGE allAnswers allAnswers JSON NOT NULL');
        $this->addSql('DROP INDEX name ON game');
        $this->addSql('ALTER TABLE game CHANGE id id INT NOT NULL');
        $this->addSql('DROP INDEX image_url ON images');
        $this->addSql('ALTER TABLE images CHANGE id id INT NOT NULL');
        $this->addSql('ALTER TABLE jeudedevinette CHANGE thème thème VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY level_ibfk_1');
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY level_ibfk_2');
        $this->addSql('ALTER TABLE level CHANGE time time INT NOT NULL');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT FK_9AEACC132FD6B47 FOREIGN KEY (childId) REFERENCES child (childId) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT FK_9AEACC13EC55B7A4 FOREIGN KEY (gameId) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE level RENAME INDEX level_ibfk_1 TO IDX_9AEACC132FD6B47');
        $this->addSql('ALTER TABLE level RENAME INDEX level_ibfk_2 TO IDX_9AEACC13EC55B7A4');
        $this->addSql('ALTER TABLE matching_game CHANGE id id INT NOT NULL, CHANGE words words LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE parents CHANGE verification_code verification_code VARCHAR(6) DEFAULT NULL, CHANGE is_verified is_verified TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE parents RENAME INDEX email TO UNIQ_FD501D6AE7927C74');
        $this->addSql('ALTER TABLE pronunciation_content CHANGE content content LONGTEXT NOT NULL, CHANGE langue langue VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parent (parentId INT AUTO_INCREMENT NOT NULL, email VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, password VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_0900_ai_ci`, verification_code VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, is_verified VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, UNIQUE INDEX email (email), PRIMARY KEY(parentId)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE word_entry');
        $this->addSql('DROP INDEX UNIQ_880E0D76E7927C74 ON `admin`');
        $this->addSql('DROP INDEX `PRIMARY` ON `admin`');
        $this->addSql('ALTER TABLE `admin` CHANGE adminId admin_id INT NOT NULL');
        $this->addSql('ALTER TABLE `admin` ADD PRIMARY KEY (admin_id)');
        $this->addSql('ALTER TABLE child DROP FOREIGN KEY FK_22B3542910EE4CEE');
        $this->addSql('ALTER TABLE child CHANGE childId childId INT AUTO_INCREMENT NOT NULL, CHANGE age age INT DEFAULT NULL, CHANGE language language VARCHAR(50) DEFAULT NULL, CHANGE avatar avatar VARCHAR(255) DEFAULT NULL, CHANGE name name VARCHAR(100) DEFAULT NULL, CHANGE parentId parentId INT NOT NULL');
        $this->addSql('ALTER TABLE child ADD CONSTRAINT child_ibfk_1 FOREIGN KEY (parentId) REFERENCES parents (parentId) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE child RENAME INDEX idx_22b3542910ee4cee TO child_ibfk_1');
        $this->addSql('ALTER TABLE pronunciation_content CHANGE content content TEXT NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE langue langue VARCHAR(50) NOT NULL COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE matching_game CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE words words TEXT NOT NULL');
        $this->addSql('ALTER TABLE jeudedevinette CHANGE thème thème VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE dragdrop CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE phrase phrase TEXT NOT NULL');
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY FK_9AEACC132FD6B47');
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY FK_9AEACC13EC55B7A4');
        $this->addSql('ALTER TABLE level CHANGE time time INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT level_ibfk_1 FOREIGN KEY (childId) REFERENCES child (childId) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT level_ibfk_2 FOREIGN KEY (gameId) REFERENCES game (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE level RENAME INDEX idx_9aeacc13ec55b7a4 TO level_ibfk_2');
        $this->addSql('ALTER TABLE level RENAME INDEX idx_9aeacc132fd6b47 TO level_ibfk_1');
        $this->addSql('ALTER TABLE parents CHANGE verification_code verification_code VARCHAR(255) DEFAULT NULL, CHANGE is_verified is_verified VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE parents RENAME INDEX uniq_fd501d6ae7927c74 TO email');
        $this->addSql('ALTER TABLE fill_in_the_blank CHANGE questionText questionText TEXT DEFAULT NULL, CHANGE correctAnswer correctAnswer VARCHAR(255) DEFAULT NULL, CHANGE allAnswers allAnswers JSON DEFAULT NULL, CHANGE theme theme VARCHAR(50) DEFAULT NULL, CHANGE level level INT DEFAULT NULL, CHANGE language language VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE game CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX name ON game (name)');
        $this->addSql('ALTER TABLE images CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX image_url ON images (image_url)');
    }
}
