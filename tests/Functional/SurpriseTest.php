<?php

namespace App\Tests\Functional;

use App\Bounty\WeekPlanner;
use App\Calendar\Week;
use App\Entity\Bounty;
use App\Entity\Gift;
use App\Entity\Member;
use App\Entity\Task;
use App\Enum\BountyKind;
use App\Enum\GiftKind;
use App\Enum\PointReason;
use App\Enum\TaskKind;
use App\Repository\BountyRepository;
use App\Repository\GiftRepository;
use App\Repository\PointEntryRepository;
use App\Repository\TaskRepository;
use App\Repository\YellowCardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;

/** The surprises hidden in the cards each week, and the yellow cards. */
final class SurpriseTest extends AppTestCase
{
    public function testPlanningAWeekHidesAFewSurprisesInTheCardsOnce(): void
    {
        $leo = $this->foundHousehold();
        foreach (range(1, 12) as $i) {
            $this->task($leo, 'Tâche '.$i);
        }
        $this->task($leo, 'Vider le lave-vaisselle', TaskKind::Quick);
        $planner = $this->planner();
        $week = Week::containing(new \DateTimeImmutable());

        $bounties = $planner->plan($leo->getHousehold(), $week);
        // One card in five, at most four; never an express task.
        self::assertCount(2, $bounties);
        self::assertNotContains('Vider le lave-vaisselle', array_map(static fn (Bounty $bounty): string => $bounty->getTask()->getTitle(), $bounties));
        self::assertSame([], $planner->plan($leo->getHousehold(), $week));
        self::assertCount(2, $planner->plan($leo->getHousehold(), $week->next()), 'Next week has its own surprises.');
    }

    public function testTheFirstToDoTheTaskFindsTheSurpriseAndTheCardGoesUpOneRarity(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $task = $this->task($leo, 'Serpillière');
        $this->bounty($task, BountyKind::Points, 15);

        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        // A common card hiding a surprise looks rare until someone finds it.
        self::assertSelectorExists('#task-'.$task->getId().'.rarity-rare');
        self::assertSelectorExists('#task-'.$task->getId().' [aria-label="Surprise cachée"]');

        $this->submitAction('/taches/'.$task->getId().'/fait');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#bounty-title', 'Une surprise était cachée dans « Serpillière »');
        self::assertSelectorTextContains('dialog', '+15 pts');
        self::assertSelectorTextContains('[data-casa-target=speech]', 'Merci Léo ! +35 pts');
        $this->client->request('GET', '/taches/'.$task->getId());
        self::assertSelectorExists('#task-'.$task->getId().'.rarity-common');
        self::assertSelectorNotExists('.bounty-tag');

        $points = static::getContainer()->get(PointEntryRepository::class);
        self::assertSame(35, $points->totalFor($leo));
        $week = Week::containing(new \DateTimeImmutable());
        self::assertSame(35, $points->sumByMember($leo->getHousehold(), $week->start, $week->end())[$leo->getId()], 'A surprise of points counts for the weekly goal.');

        // Found once: Robin, doing it again, only earns its points.
        $this->client->loginUser($robin);
        static::getContainer()->get(EntityManagerInterface::class)->getRepository(Task::class)->find($task->getId())?->restoreLastCompletion(null, null);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->client->request('GET', '/taches/'.$task->getId());
        $this->submitAction('/taches/'.$task->getId().'/fait');
        $this->client->followRedirect();
        self::assertSelectorNotExists('#bounty-title');
        self::assertSame(20, $points->totalFor($this->reload($robin)));
    }

    public function testASurpriseCanBeAGiftOrXpThatDoesNotCountForTheGoal(): void
    {
        $leo = $this->foundHousehold();
        $card = $this->task($leo, 'Vitres');
        $xp = $this->task($leo, 'Four');
        $this->bounty($card, BountyKind::YellowCard);
        $this->bounty($xp, BountyKind::Xp, 30);

        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$card->getId().'/fait');
        $this->client->followRedirect();
        self::assertSelectorTextContains('dialog', 'Un carton jaune !');
        self::assertCount(1, static::getContainer()->get(GiftRepository::class)->findUnused($this->reload($leo), GiftKind::YellowCard));

        $this->submitAction('/taches/'.$xp->getId().'/fait');
        $points = static::getContainer()->get(PointEntryRepository::class);
        $week = Week::containing(new \DateTimeImmutable());
        self::assertSame(70, $points->totalFor($leo));
        self::assertSame(40, $points->sumByMember($leo->getHousehold(), $week->start, $week->end())[$leo->getId()]);
    }

    public function testASurpriseFoundStaysWhenTheCompletionIsPutRight(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $task = $this->task($leo, 'Serpillière');
        $this->bounty($task, BountyKind::Points, 10);

        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$task->getId().'/fait');

        self::assertTrue(static::getContainer()->get(BountyRepository::class)->findOneBy([])?->isClaimed());
        $entry = static::getContainer()->get(PointEntryRepository::class)->findOneBy(['reason' => PointReason::Bounty]);
        self::assertNotNull($entry);
        $this->client->request('GET', '/realisations/'.$entry->getCompletion()?->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['completion[member]' => $robin->getId()]);

        self::assertSame(30, static::getContainer()->get(PointEntryRepository::class)->totalFor($this->reload($robin)));
    }

    public function testAYellowCardIsGivenToSomeoneAboutSomethingAndShownOnceInBig(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $gift = new Gift($this->reload($leo), GiftKind::YellowCard, 'Niveau 3', new \DateTimeImmutable());
        $this->persist($gift);

        $this->client->loginUser($leo);
        $this->client->request('GET', '/profil');
        // A real word of justification, not only a few words.
        $why = 'La vaisselle qui traîne depuis mardi dans l’évier, alors que c’était ton tour cette semaine : la poêle commence à avoir une vie à elle.';
        self::assertSelectorExists('textarea[name=motif][required]');
        $this->client->submitForm('Siffler le carton', ['pour' => $robin->getId(), 'motif' => $why]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'Carton jaune pour Robin !');
        self::assertTrue(static::getContainer()->get(GiftRepository::class)->find($gift->getId())?->isUsed());

        $this->client->loginUser($robin);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('dialog', 'Carton jaune de Léo !');
        self::assertSelectorTextContains('dialog', '« '.$why.' »');
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.reward-dialog-card');

        $this->client->request('GET', '/recompenses');
        self::assertSelectorTextContains('[aria-labelledby=cards-title]', 'Reçu de Léo');
        self::assertCount(1, static::getContainer()->get(YellowCardRepository::class)->findReceivedBy($this->reload($robin)));
    }

    public function testAYellowCardIsForSomeoneElseAndSaysWhatFor(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $gift = new Gift($this->reload($leo), GiftKind::YellowCard, 'Niveau 3', new \DateTimeImmutable());
        $this->persist($gift);

        $this->client->loginUser($leo);
        $this->client->request('POST', '/cadeaux/'.$gift->getId().'/carton', ['_csrf_token' => 'csrf-token', 'pour' => $leo->getId(), 'motif' => 'Moi-même']);
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/cadeaux/'.$gift->getId().'/carton', ['_csrf_token' => 'csrf-token', 'pour' => $robin->getId(), 'motif' => '  ']);
        self::assertFalse(static::getContainer()->get(GiftRepository::class)->find($gift->getId())?->isUsed());
    }

    public function testTheRewardsPageShowsTheRoadOfLevelsAndTheWeeksSurprises(): void
    {
        $leo = $this->foundHousehold();
        $task = $this->task($leo, 'Serpillière');
        $this->bounty($task, BountyKind::TeamBoost);

        $this->client->loginUser($leo);
        $this->client->request('GET', '/recompenses');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[aria-labelledby=surprises-title]', '1 cachée dans les cartes · 0 trouvée');
        self::assertSelectorTextContains('[aria-labelledby=road-title]', 'Niveau 4 · Plumeau curieux');
        self::assertSelectorTextContains('[aria-labelledby=road-title]', 'Carton jaune');
    }

    private function planner(): WeekPlanner
    {
        $container = static::getContainer();

        return new WeekPlanner($container->get(EntityManagerInterface::class), $container->get(TaskRepository::class), new Randomizer(new Mt19937(7)));
    }

    private function task(Member $author, string $title, TaskKind $kind = TaskKind::Rolling): Task
    {
        $task = new Task($author->getHousehold(), $author, new \DateTimeImmutable('-30 days'));
        $task->setTitle($title);
        $task->setKind($kind);
        $task->setPoints(20);
        if (TaskKind::Rolling === $kind) {
            $task->setRhythmDays(7);
        }
        $this->persist($task);

        return $task;
    }

    private function bounty(Task $task, BountyKind $kind, int $amount = 0): Bounty
    {
        $bounty = new Bounty($task, Week::containing(new \DateTimeImmutable())->start, $kind, $amount);
        $this->persist($bounty);

        return $bounty;
    }

    private function persist(object $entity): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();
    }
}
