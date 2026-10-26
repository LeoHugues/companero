<?php

namespace App\Tests\Unit\Household;

use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Pet;
use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\PetSpecies;
use App\Enum\TaskKind;
use App\Household\StarterKit;
use PHPUnit\Framework\TestCase;

final class StarterKitTest extends TestCase
{
    private Household $household;
    private Member $leo;

    protected function setUp(): void
    {
        $this->household = new Household('La coloc', new \DateTimeImmutable('2026-10-01'));
        $this->leo = new Member($this->household, 'Léo', '#E8692C', new \DateTimeImmutable('2026-10-01'));
        foreach (['Cuisine', 'Salon', 'WC'] as $room) {
            new Zone($this->household, $room);
        }
    }

    public function testWhatIsOfferedFollowsTheHouse(): void
    {
        $keys = array_column((new StarterKit())->offer($this->household), 'key');

        self::assertContains('aspirateur', $keys);
        self::assertContains('lave-vaisselle', $keys);
        // No bathroom, no garden, no animal: nothing for them.
        self::assertNotContains('salle-de-bain', $keys);
        self::assertNotContains('pelouse', $keys);
        self::assertNotContains('litiere', $keys);
        self::assertNotContains('nourrir', $keys);
    }

    public function testTheRoomsOfAChoreAreThoseOfTheHouse(): void
    {
        $offer = array_column((new StarterKit())->offer($this->household), null, 'key');

        self::assertSame(['Salon', 'Cuisine'], $offer['aspirateur']['rooms']);
        self::assertSame('le mardi soir', $offer['poubelles']['rule']);
        self::assertTrue($offer['aspirateur']['ticked']);
        self::assertFalse($offer['vitres']['ticked']);
    }

    public function testAnOnlyAnimalIsNamedAndLookedAfter(): void
    {
        $tishka = new Pet($this->household, 'Tishka', PetSpecies::Cat);

        $tasks = $this->tasks(['litiere', 'nourrir', 'promener']);

        self::assertSame(['Changer la litière de Tishka', 'Nourrir Tishka'], array_keys($tasks));
        self::assertSame($tishka, $tasks['Nourrir Tishka']->getPet());
    }

    public function testSeveralAnimalsAreLookedAfterTogether(): void
    {
        new Pet($this->household, 'Tishka', PetSpecies::Cat);
        new Pet($this->household, 'Mochi', PetSpecies::Cat);
        new Pet($this->household, 'Rex', PetSpecies::Dog);

        $tasks = $this->tasks(['litiere', 'nourrir', 'promener']);

        self::assertSame(['Changer la litière', 'Nourrir les animaux', 'Promener Rex'], array_keys($tasks));
        self::assertNull($tasks['Changer la litière']->getPet());
        self::assertSame('Rex', $tasks['Promener Rex']->getPet()?->getName());
    }

    public function testEachRhythmBecomesItsKindOfTask(): void
    {
        $tasks = $this->tasks(['aspirateur', 'poubelles', 'lave-vaisselle', 'inconnue']);

        self::assertCount(3, $tasks);
        self::assertSame(TaskKind::Rolling, $tasks['Aspirateur et balai']->getKind());
        self::assertSame(7, $tasks['Aspirateur et balai']->getRhythmDays());
        self::assertSame(TaskKind::Scheduled, $tasks['Sortir les poubelles']->getKind());
        self::assertSame(2, $tasks['Sortir les poubelles']->getScheduledWeekday());
        self::assertSame(TaskKind::Quick, $tasks['Vider le lave-vaisselle']->getKind());
    }

    public function testTheFirstChoresComeDueSpreadOut(): void
    {
        // Created on a Thursday; the cleaning day is Saturday.
        $this->household->setCleaningDay(6);

        $tasks = $this->tasks(['cuisine', 'aspirateur', 'vitres']);

        self::assertNull($tasks['Plans de travail et évier']->getFirstDueAt());
        self::assertEquals(new \DateTimeImmutable('2026-10-03 09:00'), $tasks['Aspirateur et balai']->getFirstDueAt());
        self::assertEquals(new \DateTimeImmutable('2026-10-10 09:00'), $tasks['Nettoyer les vitres']->getFirstDueAt());
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, Task> by title
     */
    private function tasks(array $keys): array
    {
        $tasks = [];
        foreach ((new StarterKit())->apply($this->household, $this->leo, new \DateTimeImmutable('2026-10-01 10:00'), $keys) as $task) {
            $tasks[$task->getTitle()] = $task;
        }

        return $tasks;
    }
}
