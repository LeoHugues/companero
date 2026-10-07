<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007172622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial de Companero';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(60) NOT NULL, starts_on DATE NOT NULL, ends_on DATE NOT NULL, member_id INTEGER NOT NULL, CONSTRAINT FK_765AE0C97597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_765AE0C97597D3FE ON absence (member_id)');
        $this->addSql('CREATE TABLE catalog_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(120) NOT NULL, category VARCHAR(255) NOT NULL, points SMALLINT NOT NULL, household_id INTEGER NOT NULL, CONSTRAINT FK_6AAE9568E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6AAE9568E79FF843 ON catalog_item (household_id)');
        $this->addSql('CREATE TABLE completion (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, completed_at DATETIME NOT NULL, urgency VARCHAR(255) NOT NULL, task_id INTEGER NOT NULL, member_id INTEGER NOT NULL, CONSTRAINT FK_38D6377E8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_38D6377E7597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX completion_date_idx ON completion (completed_at)');
        $this->addSql('CREATE INDEX IDX_38D6377E8DB60186 ON completion (task_id)');
        $this->addSql('CREATE INDEX IDX_38D6377E7597D3FE ON completion (member_id)');
        $this->addSql('CREATE TABLE earned_title (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, week_start DATE NOT NULL, name VARCHAR(80) NOT NULL, reason VARCHAR(120) NOT NULL, member_id INTEGER NOT NULL, CONSTRAINT FK_CB5D68617597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX earned_title_week_unique ON earned_title (member_id, week_start)');
        $this->addSql('CREATE INDEX IDX_CB5D68617597D3FE ON earned_title (member_id)');
        $this->addSql('CREATE TABLE household (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, cleaning_day SMALLINT NOT NULL, invite_token VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_54C32FC05242FFC4 ON household (invite_token)');
        $this->addSql('CREATE TABLE member (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(40) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, color VARCHAR(7) NOT NULL, weekly_goal SMALLINT NOT NULL, notify_cleaning_day BOOLEAN NOT NULL, notify_overdue BOOLEAN NOT NULL, notify_weekly_review BOOLEAN NOT NULL, joined_at DATETIME NOT NULL, household_id INTEGER NOT NULL, CONSTRAINT FK_70E4FA78E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70E4FA78E7927C74 ON member (email)');
        $this->addSql('CREATE INDEX IDX_70E4FA78E79FF843 ON member (household_id)');
        $this->addSql('CREATE TABLE point_entry (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reason VARCHAR(255) NOT NULL, points SMALLINT NOT NULL, label VARCHAR(120) NOT NULL, occurred_at DATETIME NOT NULL, member_id INTEGER NOT NULL, completion_id INTEGER DEFAULT NULL, CONSTRAINT FK_88141DE37597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_88141DE3C7995787 FOREIGN KEY (completion_id) REFERENCES completion (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX point_entry_date_idx ON point_entry (occurred_at)');
        $this->addSql('CREATE INDEX IDX_88141DE37597D3FE ON point_entry (member_id)');
        $this->addSql('CREATE INDEX IDX_88141DE3C7995787 ON point_entry (completion_id)');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(120) NOT NULL, kind VARCHAR(255) NOT NULL, category VARCHAR(255) NOT NULL, points SMALLINT NOT NULL, rhythm_days SMALLINT DEFAULT NULL, weekly_commitment SMALLINT DEFAULT NULL, scheduled_weekday SMALLINT DEFAULT NULL, scheduled_time TIME DEFAULT NULL, due_at DATETIME DEFAULT NULL, margin_hours SMALLINT NOT NULL, created_at DATETIME NOT NULL, last_completed_at DATETIME DEFAULT NULL, reserved_until DATETIME DEFAULT NULL, archived_at DATETIME DEFAULT NULL, household_id INTEGER NOT NULL, zone_id INTEGER DEFAULT NULL, created_by_id INTEGER NOT NULL, reserved_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_527EDB25E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_527EDB259F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_527EDB25BCDB4AF4 FOREIGN KEY (reserved_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX task_active_idx ON task (household_id, archived_at)');
        $this->addSql('CREATE INDEX IDX_527EDB25E79FF843 ON task (household_id)');
        $this->addSql('CREATE INDEX IDX_527EDB259F2C3FAB ON task (zone_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25B03A8386 ON task (created_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25BCDB4AF4 ON task (reserved_by_id)');
        $this->addSql('CREATE TABLE zone (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(60) NOT NULL, private BOOLEAN NOT NULL, household_id INTEGER NOT NULL, CONSTRAINT FK_A0EBC007E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A0EBC007E79FF843 ON zone (household_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE catalog_item');
        $this->addSql('DROP TABLE completion');
        $this->addSql('DROP TABLE earned_title');
        $this->addSql('DROP TABLE household');
        $this->addSql('DROP TABLE member');
        $this->addSql('DROP TABLE point_entry');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE zone');
    }
}
