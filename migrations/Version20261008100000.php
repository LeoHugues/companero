<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Animaux de la coloc ; tâches avec un animal, une personne qui s’en charge et un remplaçant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pet (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(40) NOT NULL, species VARCHAR(255) NOT NULL, description VARCHAR(120) DEFAULT NULL, household_id INTEGER NOT NULL, CONSTRAINT FK_E4529B85E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_E4529B85E79FF843 ON pet (household_id)');
        // Added in place: rebuilding the task table would cascade-delete its completions.
        $this->addSql('ALTER TABLE task ADD COLUMN pet_id INTEGER DEFAULT NULL REFERENCES pet (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE task ADD COLUMN assignee_id INTEGER DEFAULT NULL REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE task ADD COLUMN backup_id INTEGER DEFAULT NULL REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_527EDB25966F7FB6 ON task (pet_id)');
        $this->addSql('CREATE INDEX IDX_527EDB2559EC7D60 ON task (assignee_id)');
        $this->addSql('CREATE INDEX IDX_527EDB2593BD6749 ON task (backup_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_527EDB25966F7FB6');
        $this->addSql('DROP INDEX IDX_527EDB2559EC7D60');
        $this->addSql('DROP INDEX IDX_527EDB2593BD6749');
        $this->addSql('ALTER TABLE task DROP COLUMN pet_id');
        $this->addSql('ALTER TABLE task DROP COLUMN assignee_id');
        $this->addSql('ALTER TABLE task DROP COLUMN backup_id');
        $this->addSql('DROP TABLE pet');
    }
}
