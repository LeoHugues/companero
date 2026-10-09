<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Une tâche peut être liée à plusieurs pièces (l’aspirateur du salon passe aussi dans la cuisine et les toilettes)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_zone (task_id INTEGER NOT NULL, zone_id INTEGER NOT NULL, PRIMARY KEY (task_id, zone_id), CONSTRAINT FK_D358547C8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_D358547C9F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D358547C8DB60186 ON task_zone (task_id)');
        $this->addSql('CREATE INDEX IDX_D358547C9F2C3FAB ON task_zone (zone_id)');
        // Each task keeps its room.
        $this->addSql('INSERT INTO task_zone (task_id, zone_id) SELECT id, zone_id FROM task WHERE zone_id IS NOT NULL AND zone_id IN (SELECT id FROM zone)');
        // Ours: the living room's vacuum goes through the open kitchen and the toilets next to it.
        foreach (['Cuisine', 'WC'] as $room) {
            $this->addSql("INSERT INTO task_zone (task_id, zone_id)
                SELECT t.id, z.id FROM task t JOIN zone s ON s.id = t.zone_id AND s.name = 'Salon' JOIN zone z ON z.household_id = t.household_id AND z.name = '{$room}'
                WHERE t.title = 'Aspirateur salon et cuisine' AND NOT EXISTS (SELECT 1 FROM task_zone x WHERE x.task_id = t.id AND x.zone_id = z.id)");
        }
        // The single room column goes (SQLite rebuilds the table; its foreign keys are not enforced here).
        $this->addSql('CREATE TEMPORARY TABLE __temp__task AS SELECT id, title, kind, category, points, rhythm_days, weekly_commitment, scheduled_weekday, scheduled_time, due_at, margin_hours, created_at, last_completed_at, reserved_until, archived_at, household_id, created_by_id, reserved_by_id, pet_id, assignee_id, backup_id, cooldown_hours, last_completed_by_id, rarity, warning_hours, raised_at, note, points_adjustment, quick_position, quick_hidden FROM task');
        $this->addSql('DROP TABLE task');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(120) NOT NULL, kind VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, points SMALLINT NOT NULL, rhythm_days SMALLINT DEFAULT NULL, weekly_commitment SMALLINT DEFAULT NULL, scheduled_weekday SMALLINT DEFAULT NULL, scheduled_time TIME DEFAULT NULL, due_at DATETIME DEFAULT NULL, margin_hours SMALLINT NOT NULL, created_at DATETIME NOT NULL, last_completed_at DATETIME DEFAULT NULL, reserved_until DATETIME DEFAULT NULL, archived_at DATETIME DEFAULT NULL, household_id INTEGER NOT NULL, created_by_id INTEGER NOT NULL, reserved_by_id INTEGER DEFAULT NULL, pet_id INTEGER DEFAULT NULL, assignee_id INTEGER DEFAULT NULL, backup_id INTEGER DEFAULT NULL, cooldown_hours SMALLINT DEFAULT NULL, last_completed_by_id INTEGER DEFAULT NULL, rarity VARCHAR(255) DEFAULT \'common\' NOT NULL, warning_hours SMALLINT DEFAULT NULL, raised_at DATETIME DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, points_adjustment SMALLINT DEFAULT 0 NOT NULL, quick_position SMALLINT DEFAULT NULL, quick_hidden BOOLEAN DEFAULT 0 NOT NULL, FOREIGN KEY (pet_id) REFERENCES pet (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY (assignee_id) REFERENCES member (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY (backup_id) REFERENCES member (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, FOREIGN KEY (last_completed_by_id) REFERENCES member (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_527EDB25E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES member (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_527EDB25BCDB4AF4 FOREIGN KEY (reserved_by_id) REFERENCES member (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task (id, title, kind, category, points, rhythm_days, weekly_commitment, scheduled_weekday, scheduled_time, due_at, margin_hours, created_at, last_completed_at, reserved_until, archived_at, household_id, created_by_id, reserved_by_id, pet_id, assignee_id, backup_id, cooldown_hours, last_completed_by_id, rarity, warning_hours, raised_at, note, points_adjustment, quick_position, quick_hidden) SELECT id, title, kind, category, points, rhythm_days, weekly_commitment, scheduled_weekday, scheduled_time, due_at, margin_hours, created_at, last_completed_at, reserved_until, archived_at, household_id, created_by_id, reserved_by_id, pet_id, assignee_id, backup_id, cooldown_hours, last_completed_by_id, rarity, warning_hours, raised_at, note, points_adjustment, quick_position, quick_hidden FROM __temp__task');
        $this->addSql('DROP TABLE __temp__task');
        $this->addSql('CREATE INDEX IDX_527EDB256F579E46 ON task (last_completed_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB2593BD6749 ON task (backup_id)');
        $this->addSql('CREATE INDEX IDX_527EDB2559EC7D60 ON task (assignee_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25966F7FB6 ON task (pet_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25BCDB4AF4 ON task (reserved_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25B03A8386 ON task (created_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25E79FF843 ON task (household_id)');
        $this->addSql('CREATE INDEX task_active_idx ON task (household_id, archived_at)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('A task in several rooms cannot go back to a single one.');
    }
}
