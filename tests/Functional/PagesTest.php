<?php

namespace App\Tests\Functional;

use App\Repository\MemberRepository;
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

    public function testDaysOfPresenceLowerTheWeeklyGoal(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/profil');
        $this->client->submitForm('Enregistrer', ['profile[presenceDays]' => '4']);
        self::assertResponseRedirects('/profil');

        $this->client->request('GET', '/bilan');
        // 200 pts × 4 days present / 7.
        self::assertSelectorTextContains('#team-title + p', '/ 114 pts');
        self::assertSelectorTextContains('[aria-labelledby=members-title]', 'là 4 j/7');

        $this->client->request('GET', '/profil');
        self::assertSame('4', $this->client->getCrawler()->filter('#profile_presenceDays')->attr('value'));
    }

    public function testNobodyAroundAllWeekIsNotAtHome(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);

        $this->client->request('GET', '/profil');
        $this->client->submitForm('Enregistrer', ['profile[presenceDays]' => '0']);

        self::assertFalse(static::getContainer()->get(MemberRepository::class)->find($leo->getId())?->isAtHome());
    }

    public function testSwitchingBetweenHomeAndOut(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);

        $this->client->request('GET', '/');
        $this->submitAction('/presence/basculer');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Pas là');

        $this->submitAction('/presence/basculer');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'Bon retour');
    }

    public function testTheAppSwitchesPresenceThroughTheApi(): void
    {
        $this->client->loginUser($this->foundHousehold());

        // Without the app's header, as a cross-site form would be.
        $this->client->request('POST', '/api/presence', content: '{"atHome": false}');
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/api/presence', server: ['HTTP_X_COMPANERO_APP' => '1'], content: '{"atHome": false}');
        self::assertResponseIsSuccessful();
        self::assertFalse(json_decode((string) $this->client->getResponse()->getContent(), true)['atHome']);

        $this->client->request('POST', '/api/presence', server: ['HTTP_X_COMPANERO_APP' => '1']);
        self::assertTrue(json_decode((string) $this->client->getResponse()->getContent(), true)['atHome']);
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
