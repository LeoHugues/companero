<?php

namespace App\Tests\Functional;

use App\Entity\Household;
use App\Enum\TaskKind;
use App\Repository\CatalogItemRepository;
use App\Repository\HouseholdRepository;
use App\Repository\TaskRepository;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateHouseholdCommandTest extends AppTestCase
{
    public function testOurHouseholdIsCreatedFromItsDescription(): void
    {
        $tester = $this->command();
        $tester->setInputs(['companero123']);

        $tester->execute(['file' => 'config/coloc/notre-coloc.yaml', '--nom' => 'Léo', '--pseudo' => 'Léo']);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('/rejoindre/', $tester->getDisplay());
        $household = static::getContainer()->get(HouseholdRepository::class)->findOneBy(['name' => 'La coloc']);
        self::assertInstanceOf(Household::class, $household);
        self::assertSame(7, $household->getCleaningDay());
        self::assertSame(250, $household->getWeeklyGoal());
        self::assertSame('Léo', $household->getMembers()->first()->getName());
        self::assertSame('Tishka', $household->getPets()->first()->getName());

        $zones = array_map(static fn ($zone): string => $zone->getName(), $household->getZones()->toArray());
        self::assertContains('Petite terrasse', $zones);
        self::assertNotNull($household->getZones()->filter(static fn ($zone): bool => 'Salon' === $zone->getName())->first()->getPlanShape());

        $tasks = static::getContainer()->get(TaskRepository::class)->findActive($household);
        $sunday = array_filter($tasks, static fn ($task): bool => 1 === $task->getWeeklyCommitment());
        self::assertCount(10, $sunday);
        self::assertCount(3, array_filter($tasks, static fn ($task): bool => TaskKind::Quick === $task->getKind()));
        // Only our catalogue, not the default one.
        $catalog = array_map(static fn ($item): string => $item->getTitle(), static::getContainer()->get(CatalogItemRepository::class)->findForHousehold($household));
        self::assertContains('Nettoyer et ranger le tiroir à couverts', $catalog);
        self::assertNotContains('Tailler la haie', $catalog);
    }

    public function testNothingIsCreatedWithABadAccount(): void
    {
        $tester = $this->command();
        $tester->setInputs(['court']);

        $tester->execute(['file' => 'config/coloc/notre-coloc.yaml', '--nom' => 'Léo', '--pseudo' => 'Léo']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Au moins 8 caractères', $tester->getDisplay());
        self::assertNull(static::getContainer()->get(HouseholdRepository::class)->findOneBy(['name' => 'La coloc']));
    }

    private function command(): CommandTester
    {
        return new CommandTester((new Application(static::bootKernel()))->find('app:coloc:creer'));
    }
}
