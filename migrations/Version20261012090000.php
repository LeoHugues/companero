<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tâches occasionnelles (signalées quand ça arrive) ; Gizmo, le second chat de la coloc de Tishka';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD COLUMN raised_at DATETIME DEFAULT NULL');
        // Our coloc was created with Tishka only: Gizmo joins her, so that both show up around the Casa.
        $this->addSql("UPDATE pet SET name = 'Gizmo' WHERE name = 'Gros chat'");
        $this->addSql("INSERT INTO pet (household_id, name, species, description)
            SELECT household_id, 'Gizmo', 'cat', 'Gros chat, presque un maine coon : brun foncé tigré, plus clair vers le ventre' FROM pet
            WHERE name = 'Tishka' AND household_id NOT IN (SELECT household_id FROM pet WHERE name = 'Gizmo')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP COLUMN raised_at');
    }
}
