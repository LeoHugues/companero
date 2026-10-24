<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ce qui manque à la maison : ce qu’on a noté, qui l’a vu, qui l’a acheté';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shopping_item (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, missing_since DATETIME DEFAULT NULL, urgent BOOLEAN DEFAULT 0 NOT NULL, bought_at DATETIME DEFAULT NULL, household_id INTEGER NOT NULL, noticed_by_id INTEGER DEFAULT NULL, bought_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_6612795FE79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6612795FD1AF7BAD FOREIGN KEY (noticed_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6612795FDEC6D6BA FOREIGN KEY (bought_by_id) REFERENCES member (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6612795FE79FF843 ON shopping_item (household_id)');
        $this->addSql('CREATE INDEX IDX_6612795FD1AF7BAD ON shopping_item (noticed_by_id)');
        $this->addSql('CREATE INDEX IDX_6612795FDEC6D6BA ON shopping_item (bought_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shopping_item');
    }
}
