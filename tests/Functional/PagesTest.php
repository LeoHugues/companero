<?php

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;

final class PagesTest extends AppTestCase
{
    /** @return iterable<array{string, string}> */
    public static function pages(): iterable
    {
        yield ['/', 'La Casa'];
        yield ['/taches', 'Toutes les tâches'];
        yield ['/taches/nouvelle', 'Nouvelle tâche'];
        yield ['/bilan', 'Bilan de la semaine'];
        yield ['/profil', 'Léo'];
        yield ['/coloc', 'La coloc'];
    }

    #[DataProvider('pages')]
    public function testPageRenders(string $url, string $heading): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $heading);
    }

    public function testDeclaringAnAbsenceLowersTheWeeklyGoal(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $monday = new \DateTimeImmutable('monday this week');

        $this->client->request('GET', '/profil');
        $this->client->submitForm('Ajouter', [
            'absence[label]' => 'Week-end',
            'absence[startsOn]' => $monday->format('Y-m-d'),
            'absence[endsOn]' => $monday->modify('+2 days')->format('Y-m-d'),
        ]);
        self::assertResponseRedirects('/profil');

        $this->client->request('GET', '/bilan');
        // 200 pts × 4 days present / 7.
        self::assertSelectorTextContains('#team-title + p', '/ 114 pts');
    }

    public function testAddingAZone(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/coloc');
        $this->client->submitForm('Ajouter la zone', ['zone[name]' => 'Salle de bain du haut']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('body', 'Salle de bain du haut');
    }
}
