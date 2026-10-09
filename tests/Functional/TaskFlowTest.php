<?php

namespace App\Tests\Functional;

use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\PointReason;
use App\Repository\PointEntryRepository;
use App\Repository\TaskRepository;

final class TaskFlowTest extends AppTestCase
{
    public function testCreateARecurringTaskThenDoIt(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);

        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', [
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

    public function testTheRarityOfACardIsChosenWithTheTask(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Ranger le salon', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '7', 'task[points]' => '60']);
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Grand ménage de la cuisine', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '7', 'task[points]' => '10', 'task[rarity]' => 'legendary']);
        $tasks = static::getContainer()->get(TaskRepository::class);
        $common = $tasks->findOneBy(['title' => 'Ranger le salon']);
        $legendary = $tasks->findOneBy(['title' => 'Grand ménage de la cuisine']);

        // Common unless said otherwise, whatever the points.
        $this->client->request('GET', '/taches');
        self::assertSelectorExists(\sprintf('#template-%d[data-rarity=common]', $common?->getId()));
        self::assertSelectorExists(\sprintf('#template-%d[data-rarity=legendary]', $legendary?->getId()));

        // Never done, both are due: the home page shows them with their rarity too, as the card's skin, never as a word.
        $this->client->request('GET', '/');
        self::assertSelectorExists(\sprintf('#task-%d.rarity-legendary', $legendary?->getId()));
        self::assertSelectorTextNotContains('#task-'.$legendary?->getId(), 'Légendaire');

        $this->client->request('GET', '/taches/'.$common?->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['task[rarity]' => 'epic']);
        self::assertResponseRedirects('/taches/'.$common?->getId().'/modele');
        $this->client->followRedirect();
        self::assertSelectorExists('#template-'.$common?->getId().'[data-rarity=epic]');
    }

    public function testACardShowsHowPressingItsTaskIs(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Arroser les plantes', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '3']);
        $this->client->request('GET', '/taches/nouvelle/a-faire?nouvelle=1');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Appeler le proprio', 'task[dueAt]' => (new \DateTimeImmutable('-3 days'))->format('Y-m-d\TH:i')]);
        $this->client->request('GET', '/taches/nouvelle/a-faire?nouvelle=1');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Tailler la haie']);
        $tasks = static::getContainer()->get(TaskRepository::class);

        // Everything there is to do is on the home page, one-off tasks included.
        $this->client->request('GET', '/');
        self::assertSelectorExists(\sprintf('#task-%d[data-alert=warning]', $tasks->findOneBy(['title' => 'Arroser les plantes'])?->getId()));
        self::assertSelectorExists(\sprintf('#task-%d[data-alert=danger]', $tasks->findOneBy(['title' => 'Appeler le proprio'])?->getId()));
        self::assertSelectorExists(\sprintf('#task-%d[data-alert=ok]', $tasks->findOneBy(['title' => 'Tailler la haie'])?->getId()));
    }

    public function testTheAlertsOfACardAreSetWithTheTask(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', [
            'task[title]' => 'Serpillière',
            'task[kind]' => 'rolling',
            'task[rhythmDays]' => '7',
            'task[warningHours][amount]' => '2',
            'task[warningHours][unit]' => 'days',
            'task[marginHours][amount]' => '12',
            'task[marginHours][unit]' => 'hours',
        ]);

        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Serpillière']);
        self::assertSame(48, $task?->getWarningHours());
        self::assertSame(12, $task?->getMarginHours());

        $this->client->request('GET', '/taches/'.$task->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['task[warningHours][amount]' => '', 'task[marginHours][amount]' => '']);
        $task = static::getContainer()->get(TaskRepository::class)->find($task->getId());
        self::assertNull($task?->getWarningHours());
        self::assertSame(Task::DEFAULT_MARGIN_HOURS, $task?->getMarginHours());
    }

    public function testACardOpensItsPage(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Serpillière', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '7', 'task[points]' => '30', 'task[rarity]' => 'rare']);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Serpillière']);

        $this->client->request('GET', '/');
        self::assertSelectorExists(\sprintf('#task-%d h3 a[href="/taches/%d"]', $task?->getId(), $task?->getId()));
        self::assertSelectorExists(\sprintf('#task-%d a[href="/taches/%d/modifier"]', $task?->getId(), $task?->getId()));

        $this->client->request('GET', '/taches/'.$task?->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Serpillière');
        self::assertSelectorExists('#task-'.$task?->getId().'.rarity-rare');
        self::assertSelectorTextContains('[aria-labelledby=done-title]', 'Pas encore faite');

        // Done from its page: back to it, with the latest completion.
        $this->submitAction('/taches/'.$task?->getId().'/fait');
        self::assertResponseRedirects('/taches/'.$task?->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', '+30 pts');
        self::assertSelectorTextContains('[aria-labelledby=done-title]', 'Léo');
    }

    public function testTheTemplatesAreKeptApartFromWhatThereIsToDo(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Serpillière', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '7']);
        $this->client->request('GET', '/taches/nouvelle/a-faire?nouvelle=1');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Appeler le proprio']);
        $tasks = static::getContainer()->get(TaskRepository::class);
        $mop = $tasks->findOneBy(['title' => 'Serpillière']);
        $call = $tasks->findOneBy(['title' => 'Appeler le proprio']);

        // The templates: no button to do anything, and no one-off task (that is something to do).
        $this->client->request('GET', '/taches');
        self::assertSelectorTextContains('h1', 'Les modèles');
        self::assertSelectorExists(\sprintf('#template-%d a[href="/taches/%d/modele"]', $mop?->getId(), $mop?->getId()));
        self::assertSelectorNotExists('form[action$="/fait"]');
        self::assertSelectorNotExists('#template-'.$call?->getId());

        // A template's page leads to what there is to do.
        $this->client->request('GET', '/taches/'.$mop?->getId().'/modele');
        self::assertSelectorTextContains('h1', 'Serpillière');
        self::assertSelectorTextContains('main', 'Modèle · Régulière');
        self::assertSelectorExists(\sprintf('a[href="/taches/%d"]', $mop?->getId()));
        self::assertSelectorNotExists('form[action$="/fait"]');

        // A one-off task has no template page; it has its card's.
        $this->client->request('GET', '/taches/'.$call?->getId().'/modele');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/taches/'.$call?->getId());
        self::assertSelectorTextContains('[aria-labelledby=about-title]', 'à faire une fois');
    }

    public function testADoneOneOffTaskLeavesItsPage(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/taches/nouvelle/a-faire?nouvelle=1');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Appeler le proprio']);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Appeler le proprio']);

        $this->client->request('GET', '/taches/'.$task?->getId());
        $this->submitAction('/taches/'.$task?->getId().'/fait');

        self::assertResponseRedirects('/');
        $this->client->request('GET', '/taches/'.$task?->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidRecurringTaskIsRejected(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', [
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

        $this->client->request('GET', '/taches/nouvelle/a-faire?nouvelle=1');
        $this->client->submitForm('Ajouter la tâche', [
            'task[title]' => 'Appeler le proprio',
            'task[reserve]' => '1',
        ]);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Appeler le proprio']);
        self::assertSame($leo->getId(), $task?->reservedByAt(new \DateTimeImmutable())?->getId());

        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$task->getId().'/fait');
        $this->client->followRedirect();

        self::assertSelectorNotExists('#task-'.$task->getId());
    }

    public function testQuickTaskIsDoneInOneTapAndStays(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Vider le lave-vaisselle', 'task[kind]' => 'quick', 'task[points]' => '10']);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Vider le lave-vaisselle']);

        // Never pressing, but always one tap away on the home page.
        $this->client->followRedirect();
        self::assertSelectorTextContains('[aria-labelledby=quick-title] ul #task-'.$task?->getId(), 'Vider le lave-vaisselle');
        self::assertSelectorTextNotContains('[aria-labelledby=todo-title]', 'Vider le lave-vaisselle');

        $this->submitAction('/taches/'.$task?->getId().'/fait');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-casa-target=speech]', '+10 pts');
        self::assertSelectorExists('[aria-labelledby=quick-title] ul #task-'.$task?->getId());
        self::assertSame(10, static::getContainer()->get(PointEntryRepository::class)->totalFor($leo));
    }

    public function testAQuickTaskWaitsForItsCooldown(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', [
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
        self::assertSelectorTextContains('#task-'.$task->getId(), 'Léo · possible ');

        $this->client->request('POST', '/taches/'.$task->getId().'/fait', ['_csrf_token' => 'csrf-token']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'vient d’être fait par Léo');
        self::assertSame(10, static::getContainer()->get(PointEntryRepository::class)->totalFor($leo));
    }

    public function testACooldownCanBeSetInDays(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', [
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
        $this->client->request('GET', '/taches/nouvelle/a-faire?nouvelle=1');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Racheter du PQ']);

        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Racheter du PQ']);
        // In no hurry, it still waits on the home page.
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('[aria-labelledby=later-title] #task-'.$task?->getId(), 'Je prends');

        $this->client->request('POST', '/taches/'.$task?->getId().'/je-m-en-occupe', ['_csrf_token' => 'csrf-token', '_back' => 'home']);
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorExists('#task-'.$task?->getId().' .seat-taken[title="Tu t’en occupes"]');
        self::assertSelectorTextContains('#task-'.$task?->getId().' .seat-taken', 'Pris par toi');
    }

    public function testMembersCannotTouchAnotherHouseholdsTasks(): void
    {
        $leo = $this->foundHousehold();
        $other = $this->foundHousehold('Zoé', household: 'Une autre coloc');
        $this->client->loginUser($other);
        $this->client->request('GET', '/taches/nouvelle/a-faire?nouvelle=1');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Secret']);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Secret']);

        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/'.$task?->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/taches/'.$task?->getId().'/fait', ['_csrf_token' => 'csrf-token']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheTemplatesAreFoldedByRoomCategoryOrFrequency(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $kitchen = $leo->getHousehold()->getZones()->findFirst(static fn (int $i, Zone $zone): bool => 'Cuisine' === $zone->getName());
        $this->client->request('GET', '/taches/nouvelle/modele');
        $form = $this->client->getCrawler()->selectButton('Créer le modèle')->form();
        $this->tickRooms($form, 'Cuisine', 'Salon');
        $this->client->submit($form, ['task[title]' => 'Plans de travail', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '2']);
        $living = $leo->getHousehold()->getZones()->findFirst(static fn (int $i, Zone $zone): bool => 'Salon' === $zone->getName());
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Sortir le verre', 'task[kind]' => 'quick', 'task[category]' => 'other']);

        $this->client->request('GET', '/taches');
        self::assertSelectorExists('input[type=search][data-filter-target=input]');
        self::assertSelectorTextContains('#fold-piece-zone-'.$kitchen?->getId().' summary', 'Cuisine');
        self::assertSelectorTextContains('#fold-piece-zone-'.$kitchen?->getId(), 'Plans de travail');
        // In two rooms: in both folds.
        self::assertSelectorTextContains('#fold-piece-zone-'.$living?->getId(), 'Plans de travail');
        self::assertSelectorTextContains('#fold-piece-coloc', 'Sortir le verre');

        $this->client->request('GET', '/taches?par=categorie');
        self::assertSelectorTextContains('#fold-categorie-cleaning', 'Plans de travail');
        self::assertSelectorTextContains('#fold-categorie-other', 'Sortir le verre');

        $this->client->request('GET', '/taches?par=frequence');
        self::assertSelectorTextContains('#fold-frequence-rolling', 'Plans de travail');
        self::assertSelectorTextContains('#fold-frequence-quick', 'Sortir le verre');
    }

    public function testATaskToDoHasItsOwnNoteAndPoints(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Serpillière', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '7', 'task[points]' => '20']);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Serpillière']);
        $id = $task?->getId();

        // This time it is more work: +15, then -5. The template keeps its 20 points.
        $this->client->request('GET', '/taches/'.$id);
        $this->client->submitForm('+15');
        self::assertResponseRedirects('/taches/'.$id);
        $this->client->followRedirect();
        $this->client->submitForm('−5');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#task-points', '30');
        self::assertSelectorTextContains('#task-'.$id, '+30');

        $this->client->submitForm('Ajouter', ['note' => 'Les seaux sont au garage']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('#task-note', 'Les seaux sont au garage');
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#task-'.$id, 'Les seaux sont au garage');

        $this->client->request('GET', '/taches/'.$id.'/modele');
        self::assertSelectorTextContains('main', '20 pts');

        // Done: the points of this time, then the next time starts afresh.
        $this->client->request('GET', '/taches/'.$id);
        $this->submitAction('/taches/'.$id.'/fait');
        self::assertSame(30, static::getContainer()->get(PointEntryRepository::class)->totalFor($leo));
        $task = static::getContainer()->get(TaskRepository::class)->find($id);
        self::assertSame(20, $task?->getCurrentPoints());
        self::assertNull($task?->getNote());
    }

    public function testARoomCanBecomePartOfAnother(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $zones = $leo->getHousehold()->getZones();
        $entrance = $zones->findFirst(static fn (int $i, Zone $zone): bool => 'Entrée' === $zone->getName());
        $living = $zones->findFirst(static fn (int $i, Zone $zone): bool => 'Salon' === $zone->getName());
        $this->client->request('GET', '/taches/nouvelle/modele');
        $form = $this->client->getCrawler()->selectButton('Créer le modèle')->form();
        $this->tickRooms($form, 'Entrée');
        $this->client->submit($form, ['task[title]' => 'Sortir les poubelles', 'task[kind]' => 'quick']);

        $this->client->request('GET', '/coloc/zones/'.$entrance?->getId());
        $this->client->submitForm('Rattacher', ['dans' => (string) $living?->getId()]);
        self::assertResponseRedirects('/coloc');

        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Sortir les poubelles']);
        self::assertSame(['Salon'], array_values($task?->getZones()->map(static fn (Zone $zone): string => $zone->getName())->toArray() ?? []));
        $this->client->request('GET', '/coloc');
        self::assertSelectorTextNotContains('[aria-labelledby=zones-title]', 'Entrée');
    }

    public function testTheExpressTasksOfTheHomePageAreOrderedAndChosen(): void
    {
        $this->client->loginUser($this->foundHousehold());
        foreach (['Vider le lave-vaisselle', 'Sortir le verre', 'Arroser le basilic'] as $title) {
            $this->client->request('GET', '/taches/nouvelle/modele');
            $this->client->submitForm('Créer le modèle', ['task[title]' => $title, 'task[kind]' => 'quick']);
        }
        $tasks = static::getContainer()->get(TaskRepository::class);
        $basil = $tasks->findOneBy(['title' => 'Arroser le basilic']);
        $glass = $tasks->findOneBy(['title' => 'Sortir le verre']);

        // By title at first; the dishwasher goes up to the top, the glass is left out.
        $press = function (string $label): void {
            $this->client->submit($this->client->getCrawler()->filter(\sprintf('button[aria-label="%s"]', $label))->form());
            $this->client->followRedirect();
        };
        $this->client->request('GET', '/taches/en-un-geste');
        $press('Descendre « Arroser le basilic »');
        $press('Descendre « Arroser le basilic »');
        $press('Ne plus montrer « Sortir le verre » sur l’accueil');
        self::assertSelectorExists(\sprintf('#quick-%d[data-hidden=true]', $glass?->getId()));

        $crawler = $this->client->request('GET', '/');
        $shown = $crawler->filter('[aria-labelledby=quick-title] li')->each(static fn ($li): string => (string) $li->attr('id'));
        self::assertSame(['task-'.$tasks->findOneBy(['title' => 'Vider le lave-vaisselle'])?->getId(), 'task-'.$basil?->getId()], $shown);

        // Still a template, all the same.
        $this->client->request('GET', '/taches');
        self::assertSelectorExists('#template-'.$glass?->getId());
    }
}
