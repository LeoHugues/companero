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
        yield ['/taches', 'Les modèles'];
        yield ['/taches/nouvelle', 'Nouvelle tâche'];
        yield ['/bilan', 'Bilan de la semaine'];
        yield ['/profil', 'Léo'];
        yield ['/coloc', 'Réglages de la coloc'];
        yield ['/plan', 'Le plan'];
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
        // 70 pts × 4 days present / 7; the household goal does not change.
        self::assertSelectorTextContains('[aria-labelledby=members-title]', '/ 40 pts');
        self::assertSelectorTextContains('[aria-labelledby=members-title]', 'là 4 j/7');
        self::assertSelectorTextContains('#team-title + p', '/ 250 pts');

        $this->client->request('GET', '/profil');
        self::assertSame('4', $this->client->getCrawler()->filter('#profile_presenceDays')->attr('value'));
    }

    public function testTheHomePageShowsTheHouseGoalWithoutRankingTheMembers(): void
    {
        $leo = $this->foundHousehold();
        $this->register($leo, 'Robin');
        $this->client->loginUser($leo);

        $this->client->request('GET', '/');
        self::assertSelectorExists('[aria-labelledby=team-title] [role=progressbar]');
        self::assertSelectorNotExists('[aria-labelledby=team-title] li');

        // The weekly review keeps everyone's share.
        $this->client->request('GET', '/bilan');
        self::assertSelectorTextContains('[aria-labelledby=team-title] ul', 'Robin');
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

    public function testThePlanShowsEachRoomAndWhatItNeeds(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle');
        $form = $this->client->getCrawler()->selectButton('Ajouter la tâche')->form();
        $kitchen = array_search('Cuisine', array_map(static fn ($node) => $node->textContent, iterator_to_array($this->client->getCrawler()->filter('#task_zone label'))), true);
        $this->client->submit($form, [
            'task[title]' => 'Plans de travail',
            'task[kind]' => 'rolling',
            'task[rhythmDays]' => '7',
            'task[zone]' => $form['task[zone]']->availableOptionValues()[$kitchen],
        ]);

        $this->client->request('GET', '/plan');
        self::assertSelectorTextContains('[aria-label="Les pièces"]', 'Cuisine');
        $this->client->clickLink('Cuisine');
        self::assertSelectorTextContains('h1', 'Cuisine');
        self::assertSelectorTextContains('[aria-labelledby=zone-tasks-title]', 'Plans de travail');

        // Done from the room, back to the room.
        $room = (string) parse_url($this->client->getRequest()->getUri(), \PHP_URL_PATH);
        $this->submitAction((string) $this->client->getCrawler()->filter('[aria-labelledby=zone-tasks-title] form')->attr('action'));
        self::assertResponseRedirects($room);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[aria-labelledby=zone-done-title]', 'Plans de travail');
    }

    public function testARoomGetsItsPlaceOnThePlan(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/coloc');
        $this->client->click($this->client->getCrawler()->filter('a[aria-label="Réglages de la zone Cuisine"]')->link());

        $this->client->submitForm('Enregistrer', ['zone[planShape]' => 'n’importe quoi']);
        self::assertResponseStatusCodeSame(422);
        $this->client->submitForm('Enregistrer', ['zone[planShape]' => '0,0 20,0 20,15 0,15']);
        self::assertResponseRedirects('/coloc');

        $this->client->request('GET', '/plan');
        self::assertSelectorExists('svg[aria-label="Plan de la maison"] a[aria-label^="Cuisine"] polygon[points="0,0 20,0 20,15 0,15"]');
        // The rooms without a shape stay below, as tiles.
        self::assertSelectorTextContains('[aria-label="Les autres zones"]', 'Salon');
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
