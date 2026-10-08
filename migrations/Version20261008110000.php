<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cadeaux de niveau, boosts, séries de semaines et boost du jour de ménage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE boost (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, kind VARCHAR(255) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, household_id INTEGER NOT NULL, beneficiary_id INTEGER DEFAULT NULL, granted_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_56642768E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_56642768ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_566427683151C11F FOREIGN KEY (granted_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX boost_period_idx ON boost (household_id, ends_at)');
        $this->addSql('CREATE INDEX IDX_56642768E79FF843 ON boost (household_id)');
        $this->addSql('CREATE INDEX IDX_56642768ECCAAFA0 ON boost (beneficiary_id)');
        $this->addSql('CREATE INDEX IDX_566427683151C11F ON boost (granted_by_id)');
        $this->addSql('CREATE TABLE gift (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, kind VARCHAR(255) NOT NULL, reason VARCHAR(80) NOT NULL, earned_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, owner_id INTEGER NOT NULL, offered_by_id INTEGER DEFAULT NULL, pet_id INTEGER DEFAULT NULL, CONSTRAINT FK_A47C990D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A47C990DC68D530F FOREIGN KEY (offered_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A47C990D966F7FB6 FOREIGN KEY (pet_id) REFERENCES pet (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A47C990D7E3C61F9 ON gift (owner_id)');
        $this->addSql('CREATE INDEX IDX_A47C990DC68D530F ON gift (offered_by_id)');
        $this->addSql('CREATE INDEX IDX_A47C990D966F7FB6 ON gift (pet_id)');
        $this->addSql('ALTER TABLE household ADD COLUMN cleaning_day_boost BOOLEAN DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE member ADD COLUMN gifted_level SMALLINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE member ADD COLUMN streak SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE member ADD COLUMN best_streak SMALLINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE boost');
        $this->addSql('DROP TABLE gift');
        $this->addSql('ALTER TABLE household DROP COLUMN cleaning_day_boost');
        $this->addSql('ALTER TABLE member DROP COLUMN gifted_level');
        $this->addSql('ALTER TABLE member DROP COLUMN streak');
        $this->addSql('ALTER TABLE member DROP COLUMN best_streak');
    }
}
