<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Le jour où une tâche glissante jamais faite devient due : les premières tâches d’une nouvelle coloc, étalées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD COLUMN first_due_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP COLUMN first_due_at');
    }
}
