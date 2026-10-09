<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Les animaux ont leurs maîtres : prévenus d’abord, et les autres quand aucun n’est là';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pet_owner (pet_id INTEGER NOT NULL, member_id INTEGER NOT NULL, PRIMARY KEY (pet_id, member_id), CONSTRAINT FK_621C26DF966F7FB6 FOREIGN KEY (pet_id) REFERENCES pet (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_621C26DF7597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_621C26DF966F7FB6 ON pet_owner (pet_id)');
        $this->addSql('CREATE INDEX IDX_621C26DF7597D3FE ON pet_owner (member_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pet_owner');
    }
}
