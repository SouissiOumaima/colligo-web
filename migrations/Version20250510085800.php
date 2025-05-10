<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250510085800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE word_entry (id INT AUTO_INCREMENT NOT NULL, right_word VARCHAR(255) NOT NULL, wrong_word VARCHAR(255) NOT NULL, theme VARCHAR(255) NOT NULL, language VARCHAR(50) NOT NULL, level VARCHAR(50) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE game CHANGE id id INT NOT NULL, CHANGE name name VARCHAR(50) NOT NULL
        SQL);
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
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE word_entry
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE game CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE name name VARCHAR(255) NOT NULL
        SQL);
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
