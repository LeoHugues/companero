<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Surprises cachées dans les cartons de la semaine ; cartons jaunes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bounty (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, week_start DATE NOT NULL, kind VARCHAR(255) NOT NULL, amount SMALLINT NOT NULL, claimed_at DATETIME DEFAULT NULL, task_id INTEGER NOT NULL, claimed_by_id INTEGER DEFAULT NULL, completion_id INTEGER DEFAULT NULL, CONSTRAINT FK_93BE2C808DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_93BE2C80F67E7A38 FOREIGN KEY (claimed_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_93BE2C80C7995787 FOREIGN KEY (completion_id) REFERENCES completion (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX bounty_task_week_unique ON bounty (task_id, week_start)');
        $this->addSql('CREATE INDEX IDX_93BE2C808DB60186 ON bounty (task_id)');
        $this->addSql('CREATE INDEX IDX_93BE2C80F67E7A38 ON bounty (claimed_by_id)');
        $this->addSql('CREATE INDEX IDX_93BE2C80C7995787 ON bounty (completion_id)');
        $this->addSql('CREATE TABLE yellow_card (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reason VARCHAR(80) NOT NULL, given_at DATETIME NOT NULL, seen_at DATETIME DEFAULT NULL, given_by_id INTEGER NOT NULL, given_to_id INTEGER NOT NULL, CONSTRAINT FK_2FFCB4A6EBC53EE FOREIGN KEY (given_by_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2FFCB4AF46E398F FOREIGN KEY (given_to_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2FFCB4A6EBC53EE ON yellow_card (given_by_id)');
        $this->addSql('CREATE INDEX IDX_2FFCB4AF46E398F ON yellow_card (given_to_id)');
        $this->addSql('ALTER TABLE household ADD COLUMN bounties_planned_for DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bounty');
        $this->addSql('DROP TABLE yellow_card');
        $this->addSql('ALTER TABLE household DROP COLUMN bounties_planned_for');
    }
}
