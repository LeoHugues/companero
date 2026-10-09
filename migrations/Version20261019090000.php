<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Household\Charter;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Un pseudo au lieu d’un e-mail, des profils à réclamer, la découverte de l’appli et la charte de la coloc';
    }

    public function up(Schema $schema): void
    {
        // The username is what came before the @ of the e-mail: leo@… logs in as « leo ».
        $this->addSql('ALTER TABLE member ADD COLUMN username VARCHAR(30) DEFAULT NULL');
        $this->addSql("UPDATE member SET username = lower(substr(email, 1, instr(email, '@') - 1))");
        $this->addSql('DROP INDEX UNIQ_70E4FA78E7927C74');
        $this->addSql('ALTER TABLE member DROP COLUMN email');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70E4FA78F85E0677 ON member (username)');

        // Everyone already here goes through the tour at their next visit.
        $this->addSql('ALTER TABLE member ADD COLUMN onboarded_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE member ADD COLUMN charter_accepted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE household ADD COLUMN charter_updated_at DATETIME DEFAULT NULL');

        $this->addSql('CREATE TABLE charter_rule (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, text VARCHAR(200) NOT NULL, why CLOB DEFAULT NULL, position SMALLINT NOT NULL, household_id INTEGER NOT NULL, CONSTRAINT FK_44DFF0DFE79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_44DFF0DFE79FF843 ON charter_rule (household_id)');
        foreach (Charter::DEFAULT_RULES as $position => [$text, $why]) {
            $this->addSql('INSERT INTO charter_rule (household_id, text, why, position) SELECT id, ?, ?, ? FROM household', [$text, $why, $position]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE charter_rule');
        $this->addSql('ALTER TABLE household DROP COLUMN charter_updated_at');
        $this->addSql('ALTER TABLE member DROP COLUMN charter_accepted_at');
        $this->addSql('ALTER TABLE member DROP COLUMN onboarded_at');
        $this->addSql('DROP INDEX UNIQ_70E4FA78F85E0677');
        $this->addSql("ALTER TABLE member ADD COLUMN email VARCHAR(180) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE member SET email = coalesce(username, 'membre' || id) || '@companero.local'");
        $this->addSql('ALTER TABLE member DROP COLUMN username');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_70E4FA78E7927C74 ON member (email)');
    }
}
