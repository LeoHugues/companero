<?php

namespace App\Tests\Functional;

use App\Calendar\Week;
use App\Entity\Gift;
use App\Entity\Member;
use App\Entity\PointEntry;
use App\Entity\Task;
use App\Enum\GiftKind;
use App\Enum\PointReason;
use App\Enum\TaskKind;
use App\Repository\GiftRepository;
use App\Repository\PointEntryRepository;
use App\Review\WeekCloser;
use Doctrine\ORM\EntityManagerInterface;

final class RewardTest extends AppTestCase
{
    public function testTheCleaningDayBoostsEveryonesTasks(): void
    {
        $leo = $this->foundHousehold(cleaningDayBoost: true);
        $leo->getHousehold()->setCleaningDay((int) (new \DateTimeImmutable())->format('N'));
        $task = $this->quickTask($leo, 30);

        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('[aria-label="Boosts en cours"]', 'Jour de ménage : +1 pt tous les 3 pts');
        self::assertSelectorTextContains('#task-'.$task->getId(), '+40');

        $this->submitAction('/taches/'.$task->getId().'/fait');
        self::assertSame(40, static::getContainer()->get(PointEntryRepository::class)->totalFor($leo));
    }

    public function testReachingALevelOpensAGiftAndItsBoostHelpsEveryone(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        // 200 XP: level 2, whose gift is a boost for the whole household.
        $big = $this->quickTask($leo, 200, 'Grand ménage');
        $small = $this->quickTask($leo, 30, 'Vider le lave-vaisselle');

        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$big->getId().'/fait');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Niveau 2 ! Un cadeau pour toi');

        $this->client->request('GET', '/profil');
        self::assertSelectorTextContains('[aria-labelledby=gifts-title]', 'Boost coloc');
        $this->client->submitForm('Activer');
        self::assertResponseRedirects();

        $this->client->loginUser($robin);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('[aria-label="Boosts en cours"]', 'Boost coloc de Léo');
        $this->submitAction('/taches/'.$small->getId().'/fait');
        self::assertSame(40, static::getContainer()->get(PointEntryRepository::class)->totalFor($this->reload($robin)));
    }

    public function testATargetedBoostIsForSomeoneElse(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $gift = $this->gift($leo, GiftKind::FriendBoost);

        $this->client->loginUser($leo);
        $this->client->request('POST', '/cadeaux/'.$gift->getId().'/activer', ['_csrf_token' => 'csrf-token', 'pour' => $leo->getId()]);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('GET', '/profil');
        $this->client->submitForm('Robin');
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('[aria-label="Boosts en cours"]');

        $this->client->loginUser($robin);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('[aria-label="Boosts en cours"]', 'Offert par Léo');
    }

    public function testAnXpBoostDoesNotCountForTheWeeklyGoal(): void
    {
        $leo = $this->foundHousehold();
        $gift = $this->gift($leo, GiftKind::XpBoost);
        $task = $this->quickTask($leo, 30);

        $this->client->loginUser($leo);
        $this->client->request('POST', '/cadeaux/'.$gift->getId().'/activer', ['_csrf_token' => 'csrf-token']);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$task->getId().'/fait');

        $points = static::getContainer()->get(PointEntryRepository::class);
        $week = Week::containing(new \DateTimeImmutable());
        self::assertSame(40, $points->totalFor($leo));
        self::assertSame(30, $points->sumByMember($leo->getHousehold(), $week->start, $week->end())[$leo->getId()]);
    }

    public function testATreatForThePetsAndAFreezeForAFriend(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $this->client->loginUser($leo);
        $this->client->request('GET', '/coloc');
        $this->client->submitForm('Ajouter l’animal', ['pet[name]' => 'Tishka']);
        $this->gift($leo, GiftKind::Treat);
        $freeze = $this->gift($leo, GiftKind::StreakFreeze);

        $this->client->request('GET', '/profil');
        $this->client->submitForm('Tishka');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'Tishka se régale');

        $this->client->submitForm('Robin');
        self::assertSame($robin->getId(), static::getContainer()->get(GiftRepository::class)->find($freeze->getId())?->getOwner()->getId());

        $this->client->request('GET', '/coloc');
        self::assertSelectorTextContains('[aria-label="Dernières friandises"]', 'Léo a donné une friandise à Tishka');
    }

    public function testTheStreakGrowsIsSavedByAFreezeThenEnds(): void
    {
        $leo = $this->foundHousehold();
        $closer = static::getContainer()->get(WeekCloser::class);
        $first = Week::containing(new \DateTimeImmutable())->previous()->previous()->previous();

        // Week 1: goal reached.
        $this->points($leo, $first, 250);
        $closer->close($leo->getHousehold(), $first);
        self::assertSame(1, $leo->getStreak());

        // Week 2: missed, but a streak freeze saves it.
        $freeze = $this->gift($leo, GiftKind::StreakFreeze);
        $closer->close($leo->getHousehold(), $first->next());
        self::assertSame(1, $leo->getStreak());
        self::assertTrue($freeze->isUsed());

        // Week 3: missed, no freeze left.
        $closer->close($leo->getHousehold(), $first->next()->next());
        self::assertSame(0, $leo->getStreak());
        self::assertSame(1, $leo->getBestStreak());
    }

    public function testTheHouseholdHasItsOwnGoalAndStreak(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $household = $leo->getHousehold();
        $household->setWeeklyGoal(200);
        $closer = static::getContainer()->get(WeekCloser::class);
        $first = Week::containing(new \DateTimeImmutable())->previous()->previous();

        // Together they reach the household goal, without each reaching their own.
        $this->points($leo, $first, 60);
        $this->points($robin, $first, 150);
        $closer->close($household, $first);
        $closer->close($household, $first);
        self::assertSame(1, $household->getStreak());
        self::assertSame(0, $leo->getStreak());
        self::assertSame(60 + WeekCloser::TEAM_BONUS, static::getContainer()->get(PointEntryRepository::class)->totalFor($leo));

        $closer->close($household, $first->next());
        self::assertSame(0, $household->getStreak());
        self::assertSame(1, $household->getBestStreak());
    }

    private function quickTask(Member $author, int $points, string $title = 'Vider le lave-vaisselle'): Task
    {
        $task = new Task($author->getHousehold(), $author, new \DateTimeImmutable('-1 day'));
        $task->setTitle($title);
        $task->setKind(TaskKind::Quick);
        $task->setPoints($points);
        $this->persist($task);

        return $task;
    }

    private function gift(Member $owner, GiftKind $kind): Gift
    {
        $gift = new Gift($this->reload($owner), $kind, 'Niveau 2', new \DateTimeImmutable());
        $this->persist($gift);

        return $gift;
    }

    private function points(Member $member, Week $week, int $points): void
    {
        $this->persist(new PointEntry($member, PointReason::Task, $points, 'Grand ménage', $week->start->modify('+2 days')));
    }

    private function persist(object $entity): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();
    }
}
