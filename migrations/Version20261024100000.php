<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Les comptes : qui est dedans, ce qui a été payé, pour qui et combien ; prévenir des dépenses partagées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE expense (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, kind VARCHAR(10) NOT NULL, title VARCHAR(80) NOT NULL, amount INTEGER NOT NULL, currency VARCHAR(3) NOT NULL, exchange_rate NUMERIC(14, 6) DEFAULT NULL, converted_amount INTEGER NOT NULL, spent_on DATE NOT NULL, category VARCHAR(20) DEFAULT NULL, split_mode VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, ledger_id INTEGER NOT NULL, paid_by_id INTEGER NOT NULL, created_by_id INTEGER DEFAULT NULL, updated_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_2D3A8DA6A7B913DD FOREIGN KEY (ledger_id) REFERENCES ledger (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA67F9BC654 FOREIGN KEY (paid_by_id) REFERENCES ledger_participant (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA6B03A8386 FOREIGN KEY (created_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2D3A8DA6896DBBDE FOREIGN KEY (updated_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA6A7B913DD ON expense (ledger_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA67F9BC654 ON expense (paid_by_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA6B03A8386 ON expense (created_by_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA6896DBBDE ON expense (updated_by_id)');
        $this->addSql('CREATE TABLE expense_share (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, weight SMALLINT DEFAULT 1 NOT NULL, amount INTEGER NOT NULL, converted_amount INTEGER NOT NULL, expense_id INTEGER NOT NULL, participant_id INTEGER NOT NULL, CONSTRAINT FK_4C0E3A60F395DB7B FOREIGN KEY (expense_id) REFERENCES expense (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4C0E3A609D1C3019 FOREIGN KEY (participant_id) REFERENCES ledger_participant (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4C0E3A60F395DB7B ON expense_share (expense_id)');
        $this->addSql('CREATE INDEX IDX_4C0E3A609D1C3019 ON expense_share (participant_id)');
        $this->addSql('CREATE TABLE ledger (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(60) NOT NULL, description VARCHAR(200) DEFAULT NULL, currency VARCHAR(3) NOT NULL, everyone BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, household_id INTEGER NOT NULL, CONSTRAINT FK_C07BA4BCE79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C07BA4BCE79FF843 ON ledger (household_id)');
        $this->addSql('CREATE TABLE ledger_participant (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(40) NOT NULL, position SMALLINT NOT NULL, ledger_id INTEGER NOT NULL, member_id INTEGER DEFAULT NULL, CONSTRAINT FK_F0C7967A7B913DD FOREIGN KEY (ledger_id) REFERENCES ledger (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F0C79677597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_F0C7967A7B913DD ON ledger_participant (ledger_id)');
        $this->addSql('CREATE INDEX IDX_F0C79677597D3FE ON ledger_participant (member_id)');
        $this->addSql('ALTER TABLE member ADD COLUMN notify_expenses BOOLEAN DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE expense_share');
        $this->addSql('DROP TABLE expense');
        $this->addSql('DROP TABLE ledger_participant');
        $this->addSql('DROP TABLE ledger');
        $this->addSql('ALTER TABLE member DROP COLUMN notify_expenses');
    }
}
