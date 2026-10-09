<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Household\Charter;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trois règles de cuisine dans la charte : l’égouttoir, l’éponge, l’évier';
    }

    public function up(Schema $schema): void
    {
        // Where a rule is missing: the charter changed, everyone is invited to read it again (the server's time, as the app's).
        [$first] = Charter::KITCHEN_RULES[0];
        $this->addSql("UPDATE household SET charter_updated_at = datetime('now', 'localtime') WHERE NOT EXISTS (SELECT 1 FROM charter_rule r WHERE r.household_id = household.id AND r.text = ?)", [$first]);

        // Right after « Je fais ma vaisselle… », the first rule: the others move down.
        foreach (Charter::KITCHEN_RULES as $offset => [$text, $why]) {
            $position = 1 + $offset;
            $missing = 'NOT EXISTS (SELECT 1 FROM charter_rule r WHERE r.household_id = h.id AND r.text = ?)';
            $this->addSql("UPDATE charter_rule SET position = position + 1 WHERE position >= ? AND household_id IN (SELECT h.id FROM household h WHERE {$missing})", [$position, $text]);
            $this->addSql("INSERT INTO charter_rule (household_id, text, why, position) SELECT h.id, ?, ?, ? FROM household h WHERE {$missing}", [$text, $why, $position, $text]);
        }
    }

    public function down(Schema $schema): void
    {
        foreach (Charter::KITCHEN_RULES as [$text]) {
            $this->addSql('DELETE FROM charter_rule WHERE text = ?', [$text]);
        }
    }
}
