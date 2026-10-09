<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Une tâche à faire : une note et des points ajustés pour cette fois seulement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task ADD COLUMN note VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD COLUMN points_adjustment SMALLINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP COLUMN note');
        $this->addSql('ALTER TABLE task DROP COLUMN points_adjustment');
    }
}
