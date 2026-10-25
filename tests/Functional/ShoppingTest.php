<?php

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Entity\ShoppingItem;
use App\Repository\ShoppingItemRepository;
use Doctrine\ORM\EntityManagerInterface;

/** What the house is missing: noticed in a few letters from the home page, bought by anyone. */
final class ShoppingTest extends AppTestCase
{
    public function testSomethingMissingIsNoticedFromTheHomePageAndBoughtByAnyone(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $this->client->loginUser($leo);

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#summary-missing', 'Il ne manque rien');
        self::assertSelectorExists('#summary-missing a[href="/courses?ajouter=1"]');

        // From the "+" of the strip, the field is ready, and comes back ready for the next one.
        $this->client->request('GET', '/courses?ajouter=1');
        self::assertSelectorExists('#shopping-name[autofocus]');
        $this->client->submitForm('Ajouter', ['name' => '  papier   toilette ', 'urgent' => true]);
        self::assertResponseRedirects('/courses?ajouter=1');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'C’est noté : papier toilette.');
        self::assertSelectorTextContains('[aria-labelledby="shopping-urgent-title"]', 'Papier toilette');
        self::assertSelectorTextContains('[aria-labelledby="shopping-urgent-title"]', 'Léo · aujourd’hui');

        // The same thing, whatever its capitals, accents or plural, is not written twice.
        $this->client->submitForm('Ajouter', ['name' => 'Éponge']);
        $this->client->followRedirect();
        $this->client->submitForm('Ajouter', ['name' => 'eponges']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Éponge est déjà sur la liste.');
        self::assertCount(2, $this->missing($leo));

        // The home page shows it all at a glance, the urgent first, with a coral dot.
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#summary-missing', 'Il manque 2 choses');
        self::assertSelectorTextContains('#summary-missing', 'Papier toilette · Éponge');
        self::assertSelectorExists('#summary-missing .tile-dot');

        // Anyone ticks it once bought; it goes under the list, where it can be put back.
        $paper = $this->missing($leo)[0];
        $this->client->loginUser($this->reload($robin));
        $this->client->request('GET', '/courses');
        $this->submitAction('/courses/'.$paper->getId().'/achete');
        self::assertResponseRedirects('/courses');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Merci Robin ! Papier toilette : c’est réglé.');
        self::assertSelectorNotExists('[aria-labelledby="shopping-urgent-title"]');
        self::assertSelectorTextContains('#shopping-bought', 'Papier toilette');
        self::assertSelectorTextContains('#shopping-bought', 'Robin · aujourd’hui');
        self::assertSame(['Éponge'], array_map(static fn (ShoppingItem $item): string => $item->getName(), $this->missing($leo)));

        $this->submitAction('/courses/'.$paper->getId().'/remettre');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Remis sur la liste : papier toilette.');
        $paper = $this->item($paper->getId());
        self::assertTrue($paper->isMissing());
        self::assertFalse($paper->isUrgent());
        self::assertSame('Robin', $paper->getNoticedBy()?->getName());
    }

    public function testWhatWasBoughtBeforeIsSuggestedFirstThenWhatEveryHouseRunsOutOf(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);

        $this->client->request('GET', '/courses');
        self::assertSame(['Papier toilette', 'Éponges', 'Liquide vaisselle', 'Sacs poubelle', 'Lessive', 'Vinaigre blanc', 'Essuie-tout', 'Savon'], $this->suggestions());

        // Coffee was bought a while ago, the vinegar is missing, the soap was just bought.
        $household = $this->reload($leo)->getHousehold();
        $member = $this->reload($leo);
        $coffee = new ShoppingItem($household, 'Café');
        $coffee->buy($member, new \DateTimeImmutable('-12 days'));
        $vinegar = new ShoppingItem($household, 'Vinaigre blanc');
        $vinegar->notice($member, new \DateTimeImmutable('-1 hour'));
        $soap = new ShoppingItem($household, 'Savon');
        $soap->buy($member, new \DateTimeImmutable('-1 day'));
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ([$coffee, $vinegar, $soap] as $item) {
            $entityManager->persist($item);
        }
        $entityManager->flush();

        $this->client->request('GET', '/courses');
        self::assertSame(['Café', 'Papier toilette', 'Éponges', 'Liquide vaisselle', 'Sacs poubelle', 'Lessive', 'Essuie-tout'], $this->suggestions());

        // One tap, and it is on the list.
        $this->client->submit($this->client->getCrawler()->filter('button[aria-label="Il manque : Café"]')->form());
        self::assertResponseRedirects('/courses');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[aria-labelledby="shopping-missing-title"]', 'Café');
        self::assertNotContains('Café', $this->suggestions());
    }

    public function testAnItemIsRenamedMadeUrgentAndRemoved(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/courses');
        $this->client->submitForm('Ajouter', ['name' => 'Ampoule']);
        $bulb = $this->missing($leo)[0];

        $this->client->request('GET', '/courses/'.$bulb->getId());
        self::assertSelectorTextContains('h1', 'Ampoule');
        $this->client->submitForm('Enregistrer', ['shopping_item[name]' => 'Ampoule E27 (salle de bain)', 'shopping_item[urgent]' => true]);
        self::assertResponseRedirects('/courses');
        $bulb = $this->item($bulb->getId());
        self::assertSame('Ampoule E27 (salle de bain)', $bulb->getName());
        self::assertTrue($bulb->isUrgent());

        // Never bought: a slip of the keyboard, forgotten for good.
        $this->client->request('GET', '/courses/'.$bulb->getId());
        $this->client->submitForm('Retirer de la liste');
        self::assertResponseRedirects('/courses');
        self::assertNull(static::getContainer()->get(ShoppingItemRepository::class)->find($bulb->getId()));

        // Bought before: off the list, but still suggested.
        $this->client->request('GET', '/courses');
        $this->client->submitForm('Ajouter', ['name' => 'Lessive']);
        $this->client->followRedirect();
        $washing = $this->missing($leo)[0];
        $this->submitAction('/courses/'.$washing->getId().'/achete');
        $this->client->followRedirect();
        $this->submitAction('/courses/'.$washing->getId().'/remettre');
        $this->client->request('GET', '/courses/'.$washing->getId());
        $this->client->submitForm('Retirer de la liste');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Retiré de la liste : lessive.');
        self::assertFalse($this->item($washing->getId())->isMissing());
        self::assertSelectorTextContains('#shopping-bought', 'Lessive');
    }

    public function testTheListOfAnotherHouseholdIsOutOfReach(): void
    {
        $zoe = $this->foundHousehold('Zoé', household: 'Une autre coloc');
        $this->client->loginUser($zoe);
        $this->client->request('GET', '/courses');
        $this->client->submitForm('Ajouter', ['name' => 'Papier toilette']);
        $theirs = $this->missing($zoe)[0];

        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/courses/'.$theirs->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/courses/'.$theirs->getId().'/achete', ['_csrf_token' => 'csrf-token']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/courses');
        self::assertSelectorNotExists('#missing-'.$theirs->getId());
    }

    /** @return list<ShoppingItem> */
    private function missing(Member $member): array
    {
        return static::getContainer()->get(ShoppingItemRepository::class)->findMissing($this->reload($member)->getHousehold());
    }

    private function item(?int $id): ShoppingItem
    {
        return static::getContainer()->get(ShoppingItemRepository::class)->find($id) ?? throw new \LogicException('Item not found.');
    }

    /** @return list<string> */
    private function suggestions(): array
    {
        return $this->client->getCrawler()->filter('ul[aria-label="Suggestions"] input[name="name"]')->each(static fn ($input): string => (string) $input->attr('value'));
    }
}
