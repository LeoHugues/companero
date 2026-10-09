<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261015090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notre coloc n’a plus d’entrée : elle fait partie du salon, ses tâches y passent';
    }

    public function up(Schema $schema): void
    {
        // Only where both rooms exist, in the same household; nothing happens elsewhere.
        $salon = "(SELECT s.id FROM zone s WHERE s.household_id = e.household_id AND s.name = 'Salon')";
        $entrances = "SELECT e.id FROM zone e WHERE e.name = 'Entrée' AND EXISTS {$salon}";
        foreach (['task', 'catalog_item'] as $table) {
            $this->addSql("UPDATE {$table} SET zone_id = (SELECT {$salon} FROM zone e WHERE e.id = {$table}.zone_id) WHERE zone_id IN ({$entrances})");
        }
        $this->addSql("DELETE FROM zone WHERE id IN ({$entrances})");
    }

    public function down(Schema $schema): void
    {
        // The tasks stay in the living room.
    }
}
