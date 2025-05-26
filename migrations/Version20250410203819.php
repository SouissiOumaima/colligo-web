<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250410203819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `admin` MODIFY adminId INT NOT NULL');
        //$this->addSql('DROP INDEX email ON `admin`');
        $this->addSql('DROP INDEX `primary` ON `admin`');
        $this->addSql('ALTER TABLE `admin` ADD admin_id INT NOT NULL, DROP adminId');
        $this->addSql('ALTER TABLE `admin` ADD PRIMARY KEY (admin_id)');
        $this->addSql('ALTER TABLE child MODIFY childId INT NOT NULL');
        $this->addSql('ALTER TABLE child DROP FOREIGN KEY child_ibfk_1');
        $this->addSql('DROP INDEX `primary` ON child');
        $this->addSql('ALTER TABLE child ADD child_id INT NOT NULL, DROP childId, CHANGE parentId parentId INT DEFAULT NULL, CHANGE age age INT NOT NULL, CHANGE language language VARCHAR(50) NOT NULL, CHANGE avatar avatar VARCHAR(255) NOT NULL, CHANGE name name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE child ADD CONSTRAINT FK_22B3542910EE4CEE FOREIGN KEY (parentId) REFERENCES parents (parentId) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE child ADD PRIMARY KEY (child_id)');
        $this->addSql('ALTER TABLE child RENAME INDEX child_ibfk_1 TO IDX_22B3542910EE4CEE');
        $this->addSql('ALTER TABLE dragdrop CHANGE id id INT NOT NULL, CHANGE phrase phrase LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE fill_in_the_blank ADD question_text LONGTEXT NOT NULL, ADD correct_answer VARCHAR(255) NOT NULL, ADD all_answers VARCHAR(255) NOT NULL, DROP questionText, DROP correctAnswer, DROP allAnswers, CHANGE id id INT NOT NULL, CHANGE level level INT NOT NULL, CHANGE language language VARCHAR(50) NOT NULL, CHANGE theme theme VARCHAR(50) NOT NULL');
        $this->addSql('DROP INDEX name ON game');
        $this->addSql('ALTER TABLE game CHANGE id id INT NOT NULL');
        $this->addSql('DROP INDEX image_url ON images');
        $this->addSql('ALTER TABLE images CHANGE id id INT NOT NULL');
        $this->addSql('ALTER TABLE jeudedevinette CHANGE id id INT NOT NULL, CHANGE thème thème VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY level_ibfk_1');
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY level_ibfk_2');
        $this->addSql('ALTER TABLE level CHANGE time time INT NOT NULL');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT FK_9AEACC132FD6B47 FOREIGN KEY (childId) REFERENCES child (childId) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT FK_9AEACC13EC55B7A4 FOREIGN KEY (gameId) REFERENCES game (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE level RENAME INDEX level_ibfk_1 TO IDX_9AEACC132FD6B47');
        $this->addSql('ALTER TABLE level RENAME INDEX level_ibfk_2 TO IDX_9AEACC13EC55B7A4');
        $this->addSql('ALTER TABLE matching_game CHANGE id id INT NOT NULL, CHANGE words words LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE parents MODIFY parentId INT NOT NULL');
        $this->addSql('DROP INDEX email ON parents');
        $this->addSql('DROP INDEX `primary` ON parents');
        $this->addSql('ALTER TABLE parents ADD parent_id INT NOT NULL, DROP parentId, CHANGE verification_code verification_code VARCHAR(255) NOT NULL, CHANGE is_verified is_verified VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE parents ADD PRIMARY KEY (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP INDEX `PRIMARY` ON `admin`');
        $this->addSql('ALTER TABLE `admin` ADD adminId INT AUTO_INCREMENT NOT NULL, DROP admin_id');
        $this->addSql('CREATE UNIQUE INDEX email ON `admin` (email)');
        $this->addSql('ALTER TABLE `admin` ADD PRIMARY KEY (adminId)');
        $this->addSql('ALTER TABLE child DROP FOREIGN KEY FK_22B3542910EE4CEE');
        $this->addSql('DROP INDEX `PRIMARY` ON child');
        $this->addSql('ALTER TABLE child ADD childId INT AUTO_INCREMENT NOT NULL, DROP child_id, CHANGE age age INT DEFAULT NULL, CHANGE language language VARCHAR(50) DEFAULT NULL, CHANGE avatar avatar VARCHAR(255) DEFAULT NULL, CHANGE name name VARCHAR(100) DEFAULT NULL, CHANGE parentId parentId INT NOT NULL');
        $this->addSql('ALTER TABLE child ADD CONSTRAINT child_ibfk_1 FOREIGN KEY (parentId) REFERENCES parents (parentId) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE child ADD PRIMARY KEY (childId)');
        $this->addSql('ALTER TABLE child RENAME INDEX idx_22b3542910ee4cee TO child_ibfk_1');
        $this->addSql('ALTER TABLE dragdrop CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE phrase phrase TEXT NOT NULL');
        $this->addSql('ALTER TABLE game CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX name ON game (name)');
        $this->addSql('ALTER TABLE matching_game CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE words words TEXT NOT NULL');
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY FK_9AEACC132FD6B47');
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY FK_9AEACC13EC55B7A4');
        $this->addSql('ALTER TABLE level CHANGE time time INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT level_ibfk_1 FOREIGN KEY (childId) REFERENCES child (childId) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT level_ibfk_2 FOREIGN KEY (gameId) REFERENCES game (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE level RENAME INDEX idx_9aeacc132fd6b47 TO level_ibfk_1');
        $this->addSql('ALTER TABLE level RENAME INDEX idx_9aeacc13ec55b7a4 TO level_ibfk_2');
        $this->addSql('ALTER TABLE images CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX image_url ON images (image_url)');
        $this->addSql('ALTER TABLE fill_in_the_blank ADD questionText TEXT DEFAULT NULL, ADD correctAnswer VARCHAR(255) DEFAULT NULL, ADD allAnswers JSON DEFAULT NULL, DROP question_text, DROP correct_answer, DROP all_answers, CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE level level INT DEFAULT NULL, CHANGE language language VARCHAR(50) DEFAULT NULL, CHANGE theme theme VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE jeudedevinette CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE thème thème VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP INDEX `PRIMARY` ON parents');
        $this->addSql('ALTER TABLE parents ADD parentId INT AUTO_INCREMENT NOT NULL, DROP parent_id, CHANGE verification_code verification_code VARCHAR(255) DEFAULT NULL, CHANGE is_verified is_verified VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX email ON parents (email)');
        $this->addSql('ALTER TABLE parents ADD PRIMARY KEY (parentId)');
    }
}
