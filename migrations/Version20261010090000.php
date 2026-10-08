<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rareté des cartes de tâche, choisie pour chaque tâche ; délai avant l’alerte orange';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE task ADD COLUMN rarity VARCHAR(255) DEFAULT 'common' NOT NULL");
        $this->addSql('ALTER TABLE task ADD COLUMN warning_hours SMALLINT DEFAULT NULL');
        // The cards keep the rarity they had when it came from the points.
        $this->addSql("UPDATE task SET rarity = CASE WHEN points >= 50 THEN 'legendary' WHEN points >= 30 THEN 'epic' WHEN points >= 20 THEN 'rare' ELSE 'common' END");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP COLUMN warning_hours');
        $this->addSql('ALTER TABLE task DROP COLUMN rarity');
    }
}
