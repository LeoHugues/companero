<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Une précision sur le modèle de tâche, toujours sur la carte, que la note d’une fois remplace';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD COLUMN description VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP COLUMN description');
    }
}
