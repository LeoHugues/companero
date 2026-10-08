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
        // Its points and nothing more: only the cleaning day and the boosts add to a task.
        self::assertSelectorTextContains('[data-casa-target=speech]', 'Merci Léo ! +30 pts');

        $points = static::getContainer()->get(PointEntryRepository::class);
        self::assertSame(30, $points->totalFor($leo));
        self::assertFalse($points->hasEntry($leo, PointReason::Punctuality, new \DateTimeImmutable('-1 hour'), new \DateTimeImmutable('+1 hour')));
    }

    public function testTheMoreATaskIsWorthTheRarerItsCard(): void
    {
        $this->client->loginUser($this->foundHousehold());
        foreach (['Ranger le salon' => '10', 'Grand ménage de la cuisine' => '60'] as $title => $points) {
            $this->client->request('GET', '/taches/nouvelle');
            $this->client->submitForm('Ajouter la tâche', ['task[title]' => $title, 'task[kind]' => 'rolling', 'task[rhythmDays]' => '7', 'task[points]' => $points]);
        }
        $tasks = static::getContainer()->get(TaskRepository::class);
        $small = $tasks->findOneBy(['title' => 'Ranger le salon']);
        $big = $tasks->findOneBy(['title' => 'Grand ménage de la cuisine']);

        $this->client->request('GET', '/taches');
        self::assertSelectorExists(\sprintf('#task-%d[data-rarity=common]', $small?->getId()));
        self::assertSelectorTextContains('#task-'.$small?->getId(), 'Commune');
        self::assertSelectorExists(\sprintf('#task-%d[data-rarity=legendary]', $big?->getId()));
        self::assertSelectorTextContains('#task-'.$big?->getId(), 'Légendaire');

        // Never done, both are due: the home page shows them with their rarity too.
        $this->client->request('GET', '/');
        self::assertSelectorExists(\sprintf('#task-%d.rarity-legendary', $big?->getId()));
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

    public function testQuickTaskIsDoneInOneTapAndStays(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Vider le lave-vaisselle', 'task[kind]' => 'quick', 'task[points]' => '10']);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Vider le lave-vaisselle']);

        // Never pressing, but always one tap away on the home page.
        $this->client->followRedirect();
        self::assertSelectorTextContains('#quick-title + ul #task-'.$task?->getId(), 'Vider le lave-vaisselle');
        self::assertSelectorTextNotContains('[aria-labelledby=todo-title]', 'Vider le lave-vaisselle');

        $this->submitAction('/taches/'.$task?->getId().'/fait');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-casa-target=speech]', '+10 pts');
        self::assertSelectorExists('#quick-title + ul #task-'.$task?->getId());
        self::assertSame(10, static::getContainer()->get(PointEntryRepository::class)->totalFor($leo));
    }

    public function testAQuickTaskWaitsForItsCooldown(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle');
        $this->client->submitForm('Ajouter la tâche', [
            'task[title]' => 'Vider le lave-vaisselle',
            'task[kind]' => 'quick',
            'task[points]' => '10',
            'task[cooldownHours][amount]' => '4',
            'task[cooldownHours][unit]' => 'hours',
        ]);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Vider le lave-vaisselle']);
        self::assertSame(4, $task?->getCooldownHours());

        $this->client->followRedirect();
        $this->submitAction('/taches/'.$task->getId().'/fait');
        $this->client->followRedirect();
        // Done: no more button until the cooldown is over.
        self::assertSelectorNotExists('form[action="/taches/'.$task->getId().'/fait"]');
        self::assertSelectorTextContains('#task-'.$task->getId(), 'Léo · possible à');

        $this->client->request('POST', '/taches/'.$task->getId().'/fait', ['_csrf_token' => 'csrf-token']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'vient d’être fait par Léo');
        self::assertSame(10, static::getContainer()->get(PointEntryRepository::class)->totalFor($leo));
    }

    public function testACooldownCanBeSetInDays(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/taches/nouvelle');
        $this->client->submitForm('Ajouter la tâche', [
            'task[title]' => 'Faire le verre',
            'task[kind]' => 'quick',
            'task[cooldownHours][amount]' => '2',
            'task[cooldownHours][unit]' => 'days',
        ]);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Faire le verre']);
        self::assertSame(48, $task?->getCooldownHours());

        $this->client->request('GET', '/taches/'.$task->getId().'/modifier');
        self::assertSame('2', $this->client->getCrawler()->filter('#task_cooldownHours_amount')->attr('value'));
        self::assertSame('checked', $this->client->getCrawler()->filter('#task_cooldownHours_unit_1')->attr('checked'));
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
