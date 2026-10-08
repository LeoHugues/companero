<?php

namespace App\Tests\Functional;

use App\Entity\Task;
use App\Enum\PointReason;
use App\Repository\PointEntryRepository;
use App\Repository\TaskRepository;

final class TaskFlowTest extends AppTestCase
{
    public function testCreateARecurringTaskThenDoIt(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);

        $this->client->request('GET', '/taches/nouvelle');
        $this->client->submitForm('Ajouter la tâche', [
            'task[title]' => 'Serpillière',
            'task[kind]' => 'rolling',
            'task[rhythmDays]' => '7',
            'task[weeklyCommitment]' => '1',
            'task[category]' => 'cleaning',
            'task[points]' => '30',
        ]);
        self::assertResponseRedirects('/');

        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Serpillière']);
        self::assertInstanceOf(Task::class, $task);

        // Never done yet, so it is due right away and shows up on the home page.
        $this->client->followRedirect();
        self::assertSelectorTextContains('#task-'.$task->getId(), 'Serpillière');

        $this->submitAction('/taches/'.$task->getId().'/fait');
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-casa-target=speech]', 'Merci Léo ! +40 pts');

        $points = static::getContainer()->get(PointEntryRepository::class);
        self::assertSame(40, $points->totalFor($leo));
        self::assertTrue($points->hasEntry($leo, PointReason::Punctuality, new \DateTimeImmutable('-1 hour'), new \DateTimeImmutable('+1 hour')));
    }

    public function testInvalidRecurringTaskIsRejected(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/taches/nouvelle');
        $this->client->submitForm('Ajouter la tâche', [
            'task[title]' => 'Aspirateur',
            'task[kind]' => 'rolling',
            'task[rhythmDays]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Tous les combien de jours ?');
    }

    public function testOneOffTaskDisappearsOnceDone(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);

        $this->client->request('GET', '/taches/nouvelle');
        $this->client->submitForm('Ajouter la tâche', [
            'task[title]' => 'Appeler le proprio',
            'task[kind]' => 'one_off',
            'task[reserve]' => '1',
        ]);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Appeler le proprio']);
        self::assertSame($leo->getId(), $task?->reservedByAt(new \DateTimeImmutable())?->getId());

        $this->client->request('GET', '/taches');
        $this->submitAction('/taches/'.$task->getId().'/fait');
        $this->client->followRedirect();

        self::assertSelectorNotExists('#task-'.$task->getId());
    }

    public function testReservingATask(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Racheter du PQ', 'task[kind]' => 'one_off']);

        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Racheter du PQ']);
        $this->client->request('GET', '/taches');
        self::assertSelectorTextNotContains('#task-'.$task?->getId(), 'tu t’en occupes');

        $this->client->request('POST', '/taches/'.$task?->getId().'/je-m-en-occupe', ['_csrf_token' => 'csrf-token', '_back' => 'tasks']);
        self::assertResponseRedirects('/taches');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#task-'.$task?->getId(), 'tu t’en occupes');
    }

    public function testMembersCannotTouchAnotherHouseholdsTasks(): void
    {
        $leo = $this->foundHousehold();
        $other = $this->foundHousehold('Zoé', 'zoe@example.com', 'Une autre coloc');
        $this->client->loginUser($other);
        $this->client->request('GET', '/taches/nouvelle');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Secret', 'task[kind]' => 'one_off']);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Secret']);

        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/'.$task?->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/taches/'.$task?->getId().'/fait', ['_csrf_token' => 'csrf-token']);
        self::assertResponseStatusCodeSame(403);
    }
}
