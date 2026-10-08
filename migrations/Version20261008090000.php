<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Points ×10 : 5 minutes d’effort valent désormais 10 points';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE task SET points = points * 10');
        $this->addSql('UPDATE catalog_item SET points = points * 10');
        $this->addSql('UPDATE point_entry SET points = points * 10');
        $this->addSql('UPDATE member SET weekly_goal = weekly_goal * 10');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE task SET points = MAX(1, points / 10)');
        $this->addSql('UPDATE catalog_item SET points = MAX(1, points / 10)');
        $this->addSql('UPDATE point_entry SET points = points / 10');
        $this->addSql('UPDATE member SET weekly_goal = weekly_goal / 10');
    }
}
