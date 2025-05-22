<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250522124245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP INDEX `primary` ON level
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD childId INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD CONSTRAINT FK_9AEACC132FD6B47 FOREIGN KEY (childId) REFERENCES child (childId) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_9AEACC132FD6B47 ON level (childId)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD PRIMARY KEY (id, childId, gameId)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP FOREIGN KEY FK_9AEACC132FD6B47
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_9AEACC132FD6B47 ON level
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX `PRIMARY` ON level
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level DROP childId
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE level ADD PRIMARY KEY (id, gameId)
        SQL);
    }
}
