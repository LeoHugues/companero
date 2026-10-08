<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Présence en jours par semaine (remplace les absences datées) et « à la maison »';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE presence (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, week_start DATE NOT NULL, days SMALLINT NOT NULL, member_id INTEGER NOT NULL, CONSTRAINT FK_6977C7A57597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX presence_week_unique ON presence (member_id, week_start)');
        $this->addSql('CREATE INDEX IDX_6977C7A57597D3FE ON presence (member_id)');
        $this->addSql('DROP TABLE absence');
        $this->addSql('ALTER TABLE member ADD COLUMN at_home BOOLEAN DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE member ADD COLUMN at_home_changed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE absence (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, label VARCHAR(60) NOT NULL COLLATE "BINARY", starts_on DATE NOT NULL, ends_on DATE NOT NULL, member_id INTEGER NOT NULL, CONSTRAINT FK_765AE0C97597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_765AE0C97597D3FE ON absence (member_id)');
        $this->addSql('DROP TABLE presence');
        $this->addSql('CREATE TEMPORARY TABLE __temp__member AS SELECT id, name, email, password, color, weekly_goal, notify_cleaning_day, notify_overdue, notify_weekly_review, joined_at, household_id FROM member');
        $this->addSql('DROP TABLE member');
        $this->addSql('CREATE TABLE member (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(40) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, color VARCHAR(7) NOT NULL, weekly_goal SMALLINT NOT NULL, notify_cleaning_day BOOLEAN NOT NULL, notify_overdue BOOLEAN NOT NULL, notify_weekly_review BOOLEAN NOT NULL, joined_at DATETIME NOT NULL, household_id INTEGER NOT NULL, CONSTRAINT FK_70E4FA78E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO member (id, name, email, password, color, weekly_goal, notify_cleaning_day, notify_overdue, notify_weekly_review, joined_at, household_id) SELECT id, name, email, password, color, weekly_goal, notify_cleaning_day, notify_overdue, notify_weekly_review, joined_at, household_id FROM __temp__member');
        $this->addSql('DROP TABLE __temp__member');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70E4FA78E7927C74 ON member (email)');
        $this->addSql('CREATE INDEX IDX_70E4FA78E79FF843 ON member (household_id)');
    }
}
