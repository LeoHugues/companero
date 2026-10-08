<?php

namespace App\Tests\Functional;

use App\Entity\Completion;
use App\Entity\Member;
use App\Entity\Task;
use App\Enum\TaskKind;
use App\Repository\CompletionRepository;
use App\Repository\PointEntryRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CompletionEditTest extends AppTestCase
{
    public function testATaskCanBeNotedOnceAlreadyDone(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $doneAt = new \DateTimeImmutable('-2 days 10:00');

        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', [
            'task[title]' => 'Serpillière',
            'task[kind]' => 'rolling',
            'task[rhythmDays]' => '7',
            'task[points]' => '30',
            'task[done]' => '1',
            'task[doneAt]' => $doneAt->format('Y-m-d\TH:i'),
        ]);
        self::assertResponseRedirects('/');

        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Serpillière']);
        self::assertInstanceOf(Task::class, $task);
        self::assertSame($doneAt->format('Y-m-d H:i'), $task->getLastCompletedAt()?->format('Y-m-d H:i'));
        self::assertNull($task->reservedByAt(new \DateTimeImmutable()));

        $completion = $this->completionOf($task);
        self::assertSame($doneAt->format('Y-m-d H:i'), $completion->getCompletedAt()->format('Y-m-d H:i'));
        // The points count on that day.
        self::assertSame(30, static::getContainer()->get(PointEntryRepository::class)->totalFor($leo));
        foreach (static::getContainer()->get(PointEntryRepository::class)->findBy(['completion' => $completion]) as $entry) {
            self::assertSame($doneAt->format('Y-m-d H:i'), $entry->getOccurredAt()->format('Y-m-d H:i'));
        }

        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-casa-target=speech]', 'Merci Léo ! +30 pts');
    }

    public function testACompletionCanBeMovedToAnotherDayAndMember(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $task = $this->quickTask($leo, 'Vider le lave-vaisselle', 10);
        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$task->getId().'/fait');
        $completion = $this->completionOf($task);

        $this->client->request('GET', '/fait');
        self::assertSelectorExists(\sprintf('a[href="/realisations/%d/modifier"]', $completion->getId()));

        $doneAt = new \DateTimeImmutable('-3 days 18:30');
        $this->client->request('GET', '/realisations/'.$completion->getId().'/modifier');
        self::assertResponseIsSuccessful();
        self::assertSame('10', $this->client->getCrawler()->filter('#completion_points')->attr('value'));
        $this->client->submitForm('Enregistrer', [
            'completion[completedAt]' => $doneAt->format('Y-m-d\TH:i'),
            'completion[member]' => (string) $robin->getId(),
            'completion[points]' => '25',
        ]);
        self::assertResponseRedirects('/fait?qui=coloc&jours=7');

        $completion = $this->completionOf($task);
        self::assertSame($doneAt->format('Y-m-d H:i'), $completion->getCompletedAt()->format('Y-m-d H:i'));
        self::assertSame($robin->getId(), $completion->getMember()->getId());

        $points = static::getContainer()->get(PointEntryRepository::class);
        self::assertSame(0, $points->totalFor($this->reload($leo)));
        self::assertSame(25, $points->totalFor($this->reload($robin)));
        // The task starts again from that day.
        $task = static::getContainer()->get(TaskRepository::class)->find($task->getId());
        self::assertSame($doneAt->format('Y-m-d H:i'), $task?->getLastCompletedAt()?->format('Y-m-d H:i'));
        self::assertSame($robin->getId(), $task?->getLastCompletedBy()?->getId());
    }

    public function testMovedToTheCleaningDayItEarnsItsBoost(): void
    {
        $leo = $this->foundHousehold(cleaningDayBoost: true);
        $cleaningDay = new \DateTimeImmutable('-2 days 11:00');
        $leo->getHousehold()->setCleaningDay((int) $cleaningDay->format('N'));
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $task = $this->quickTask($leo, 'Aspirateur', 30);
        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$task->getId().'/fait');
        self::assertSame(30, static::getContainer()->get(PointEntryRepository::class)->totalFor($this->reload($leo)));

        $this->client->request('GET', '/realisations/'.$this->completionOf($task)->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['completion[completedAt]' => $cleaningDay->format('Y-m-d\TH:i')]);

        // +1 pt every 3 pts on the cleaning day.
        self::assertSame(40, static::getContainer()->get(PointEntryRepository::class)->totalFor($this->reload($leo)));
    }

    public function testMovingAnOlderCompletionKeepsTheLatestOneAsReference(): void
    {
        $leo = $this->foundHousehold();
        $task = $this->quickTask($leo, 'Faire le verre', 20);
        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$task->getId().'/fait');
        $first = $this->completionOf($task)->getId();
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$task->getId().'/fait');
        $latest = static::getContainer()->get(TaskRepository::class)->find($task->getId())?->getLastCompletedAt();
        self::assertNotNull($latest);

        $this->client->request('GET', '/realisations/'.$first.'/modifier');
        $this->client->submitForm('Enregistrer', ['completion[completedAt]' => (new \DateTimeImmutable('-2 days 09:00'))->format('Y-m-d\TH:i')]);
        self::assertResponseRedirects();

        $task = static::getContainer()->get(TaskRepository::class)->find($task->getId());
        self::assertSame($latest->format('Y-m-d H:i:s'), $task?->getLastCompletedAt()?->format('Y-m-d H:i:s'));
        self::assertSame(40, static::getContainer()->get(PointEntryRepository::class)->totalFor($this->reload($leo)));
    }

    public function testACompletionCannotBeMovedToTheFuture(): void
    {
        $leo = $this->foundHousehold();
        $task = $this->quickTask($leo, 'Vider le lave-vaisselle', 10);
        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$task->getId().'/fait');

        $this->client->request('GET', '/realisations/'.$this->completionOf($task)->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['completion[completedAt]' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d\TH:i')]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Pas dans le futur');
    }

    public function testMembersCannotTouchAnotherHouseholdsCompletions(): void
    {
        $leo = $this->foundHousehold();
        $task = $this->quickTask($leo, 'Vider le lave-vaisselle', 10);
        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$task->getId().'/fait');

        $this->client->loginUser($this->foundHousehold('Zoé', 'zoe@example.com', 'Une autre coloc'));
        $this->client->request('GET', '/realisations/'.$this->completionOf($task)->getId().'/modifier');

        self::assertResponseStatusCodeSame(403);
    }

    /** The task's first completion. */
    private function completionOf(Task $task): Completion
    {
        return static::getContainer()->get(CompletionRepository::class)->findOneBy(['task' => $task->getId()], ['id' => 'ASC']) ?? throw new \LogicException('No completion.');
    }

    private function quickTask(Member $author, string $title, int $points): Task
    {
        $task = new Task($author->getHousehold(), $author, new \DateTimeImmutable('-7 days'));
        $task->setTitle($title);
        $task->setKind(TaskKind::Quick);
        $task->setPoints($points);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($task);
        $entityManager->flush();

        return $task;
    }
}
