<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Un carton jaune se justifie en un vrai commentaire : 200 caractères au lieu de 80';
    }

    public function up(Schema $schema): void
    {
        $this->resize(200);
    }

    public function down(Schema $schema): void
    {
        $this->resize(80);
    }

    private function resize(int $length): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__yellow_card AS SELECT id, reason, given_at, seen_at, given_by_id, given_to_id FROM yellow_card');
        $this->addSql('DROP TABLE yellow_card');
        $this->addSql("CREATE TABLE yellow_card (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, reason VARCHAR({$length}) NOT NULL, given_at DATETIME NOT NULL, seen_at DATETIME DEFAULT NULL, given_by_id INTEGER NOT NULL, given_to_id INTEGER NOT NULL, CONSTRAINT FK_2FFCB4A6EBC53EE FOREIGN KEY (given_by_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2FFCB4AF46E398F FOREIGN KEY (given_to_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)");
        $this->addSql("INSERT INTO yellow_card (id, reason, given_at, seen_at, given_by_id, given_to_id) SELECT id, substr(reason, 1, {$length}), given_at, seen_at, given_by_id, given_to_id FROM __temp__yellow_card");
        $this->addSql('DROP TABLE __temp__yellow_card');
        $this->addSql('CREATE INDEX IDX_2FFCB4AF46E398F ON yellow_card (given_to_id)');
        $this->addSql('CREATE INDEX IDX_2FFCB4A6EBC53EE ON yellow_card (given_by_id)');
    }
}
