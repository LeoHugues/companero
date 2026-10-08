<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Délai minimal avant de refaire une tâche ; qui l’a faite en dernier';
    }

    public function up(Schema $schema): void
    {
        // Added in place: rebuilding the task table would cascade-delete its completions.
        $this->addSql('ALTER TABLE task ADD COLUMN cooldown_hours SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD COLUMN last_completed_by_id INTEGER DEFAULT NULL REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_527EDB256F579E46 ON task (last_completed_by_id)');
        $this->addSql('UPDATE task SET last_completed_by_id = (SELECT c.member_id FROM completion c WHERE c.task_id = task.id ORDER BY c.completed_at DESC LIMIT 1)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_527EDB256F579E46');
        $this->addSql('ALTER TABLE task DROP COLUMN cooldown_hours');
        $this->addSql('ALTER TABLE task DROP COLUMN last_completed_by_id');
    }
}
