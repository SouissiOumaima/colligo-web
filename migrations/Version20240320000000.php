<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240320000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create completed_sentences table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE completed_sentences (
            id INT AUTO_INCREMENT NOT NULL,
            child_id INT NOT NULL,
            sentence_id INT NOT NULL,
            completed_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_COMPLETED_SENTENCES_CHILD FOREIGN KEY (child_id) REFERENCES child (childId),
            CONSTRAINT FK_COMPLETED_SENTENCES_SENTENCE FOREIGN KEY (sentence_id) REFERENCES dragdrop (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        
        $this->addSql('CREATE INDEX IDX_COMPLETED_SENTENCES_CHILD ON completed_sentences (child_id)');
        $this->addSql('CREATE INDEX IDX_COMPLETED_SENTENCES_SENTENCE ON completed_sentences (sentence_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS completed_sentences');
    }
} 