<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Objectif de la maison et série de la coloc ; objectifs personnels à partir de 70 pts (5 min par jour)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE household ADD COLUMN weekly_goal SMALLINT DEFAULT 250 NOT NULL');
        $this->addSql('ALTER TABLE household ADD COLUMN streak SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE household ADD COLUMN best_streak SMALLINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE household ADD COLUMN streak_counted_until DATE DEFAULT NULL');
        // The new choices: 70, 140, 210 or 280 points (5, 10, 15 or 20 minutes a day).
        $this->addSql('UPDATE member SET weekly_goal = CASE WHEN weekly_goal <= 100 THEN 70 WHEN weekly_goal <= 175 THEN 140 WHEN weekly_goal <= 245 THEN 210 ELSE 280 END');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE household DROP COLUMN weekly_goal');
        $this->addSql('ALTER TABLE household DROP COLUMN streak');
        $this->addSql('ALTER TABLE household DROP COLUMN best_streak');
        $this->addSql('ALTER TABLE household DROP COLUMN streak_counted_until');
    }
}
