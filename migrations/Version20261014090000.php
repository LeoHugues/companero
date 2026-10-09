<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Le catalogue retient la pièce de ses tâches';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_item ADD COLUMN zone_id INTEGER DEFAULT NULL REFERENCES zone (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6AAE95689F2C3FAB ON catalog_item (zone_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_6AAE95689F2C3FAB');
        $this->addSql('ALTER TABLE catalog_item DROP COLUMN zone_id');
    }
}
