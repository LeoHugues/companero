<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '« En un geste » : l’ordre des tâches express sur l’accueil, et celles qu’on n’y montre pas';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD COLUMN quick_position SMALLINT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD COLUMN quick_hidden BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP COLUMN quick_position');
        $this->addSql('ALTER TABLE task DROP COLUMN quick_hidden');
    }
}
