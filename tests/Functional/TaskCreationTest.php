<?php

namespace App\Tests\Functional;

use App\Entity\Completion;
use App\Repository\CatalogItemRepository;
use App\Repository\CompletionRepository;
use App\Repository\TaskRepository;

/** The "+" button: a sheet of what can be added, then only what matters for it, the rest folded away. */
final class TaskCreationTest extends AppTestCase
{
    public function testThePlusButtonOpensASheetOfWhatCanBeAdded(): void
    {
        $this->client->loginUser($this->foundHousehold());

        // On any page, the "+" of the bottom bar opens a sheet, loaded the first time it opens.
        $this->client->request('GET', '/');
        self::assertSelectorExists('.nav-bar dialog.add-sheet turbo-frame#add-sheet[src="/taches/nouvelle"][loading=lazy]');

        $this->client->request('GET', '/taches/nouvelle', server: ['HTTP_TURBO_FRAME' => 'add-sheet', 'HTTP_REFERER' => 'http://localhost/courses']);
        self::assertSelectorExists('turbo-frame#add-sheet[target=_top]');
        self::assertSelectorNotExists('main');
        foreach (['/taches/nouvelle/a-faire', '/taches/nouvelle/deja-fait', '/comptes/depense', '/taches/nouvelle/modele'] as $choice) {
            self::assertSelectorExists('a.add-choice[href="'.$choice.'"]');
        }

        // What is missing is said right there, and leads back to the page it was said on.
        self::assertSelectorExists('input[name="_retour"][value="/courses"]');
        $this->client->request('GET', '/taches/nouvelle', server: ['HTTP_TURBO_FRAME' => 'add-sheet', 'HTTP_REFERER' => 'http://localhost/taches']);
        $this->client->submitForm('Ajouter à ce qui manque', ['name' => 'Piles']);
        self::assertResponseRedirects('/taches');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'C’est noté : piles.');

        // A page of its own, in the app and without scripts.
        $this->client->request('GET', '/taches/nouvelle');
        self::assertSelectorTextContains('h1', 'Ajouter');
        self::assertSelectorExists('input[name="_retour"][value="/taches/nouvelle"]');
    }

    public function testATaskToDoIsDoneOnceAndATemplateComesBack(): void
    {
        $this->client->loginUser($this->foundHousehold());

        // Something to do once, in a few words: what, when, who; the rest folded away, nothing about coming back.
        $this->client->request('GET', '/taches/nouvelle/a-faire');
        self::assertSelectorNotExists('input[name="task[kind]"]');
        self::assertSelectorNotExists('input[name="task[rhythmDays]"]');
        self::assertSelectorExists('button[data-due-when-param=today]');
        self::assertSelectorExists('input[name="task[reserve]"]');
        self::assertSelectorExists('details#task-more:not([open]) input[name="task[points]"]');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Appeler le proprio']);
        // Free, it waits in the list of the coloc; taken, in my hand on the home page.
        self::assertResponseRedirects('/taches');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', '« Appeler le proprio » est dans la liste. Je préviens la coloc.');
        $this->client->request('GET', '/taches/nouvelle/a-faire');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Monter l’étagère', 'task[reserve]' => true]);
        self::assertResponseRedirects('/');

        // A template comes back: never a one-off task.
        $this->client->request('GET', '/taches/nouvelle/modele');
        self::assertSelectorExists('input[name="task[kind]"][value=rolling]');
        self::assertSelectorExists('input[name="task[kind]"][value=quick]');
        self::assertSelectorNotExists('input[name="task[kind]"][value=one_off]');
        self::assertSelectorNotExists('input[name="task[reserve]"]');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Serpillière', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '7']);
        self::assertResponseRedirects('/taches');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'Le modèle « Serpillière » est créé');
    }

    public function testATaskToDoCanBeEditedWhenItsAlertsAreTheSame(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/taches/nouvelle/a-faire');
        // Orange a day before, red a day after: the two durations used to be compared field by field, endlessly.
        $this->client->submitForm('Ajouter la tâche', [
            'task[title]' => 'Déchetterie',
            'task[dueAt]' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d\TH:i'),
            'task[warningHours][amount]' => '1',
            'task[warningHours][unit]' => 'days',
            'task[marginHours][amount]' => '1',
            'task[marginHours][unit]' => 'days',
        ]);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Déchetterie']);

        $this->client->request('GET', '/taches/'.$task?->getId().'/modifier');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Enregistrer', ['task[title]' => 'Aller à la déchetterie']);
        self::assertResponseRedirects('/taches/'.$task?->getId());
    }

    public function testATaskToDoIsPickedAmongKnownOnesOrCreatedForNextTime(): void
    {
        $this->client->loginUser($this->foundHousehold());

        // As the title is typed, what the house already knows shows up under it.
        $crawler = $this->client->request('GET', '/taches/nouvelle/a-faire');
        self::assertSelectorExists('input[name="task[title]"][data-filter-target=input]');
        $known = $crawler->filter('a[href^="/taches/nouvelle/a-faire?modele="]');
        self::assertGreaterThan(0, $known->count());

        // A known one: its form comes filled in, and it does not join the catalogue twice.
        $this->client->click($known->first()->link());
        self::assertSelectorNotExists('input[name="task[catalog]"]');

        // A new one joins the catalogue, unless said otherwise.
        $catalog = static::getContainer()->get(CatalogItemRepository::class);
        $before = \count($catalog->findAll());
        $this->client->request('GET', '/taches/nouvelle/a-faire?titre=D%C3%A9tartrer+la+bouilloire');
        self::assertInputValueSame('task[title]', 'Détartrer la bouilloire');
        $this->client->submitForm('Ajouter la tâche');
        self::assertResponseRedirects('/taches');
        self::assertNotNull($catalog->findOneBy(['title' => 'Détartrer la bouilloire']));

        $this->client->request('GET', '/taches/nouvelle/a-faire');
        $this->client->submitForm('Ajouter la tâche', ['task[title]' => 'Appeler le proprio', 'task[catalog]' => false]);
        self::assertNull($catalog->findOneBy(['title' => 'Appeler le proprio']));
        self::assertCount($before + 1, $catalog->findAll());

        // Typed again: the house knows it now.
        $this->client->request('GET', '/taches/nouvelle/a-faire');
        self::assertAnySelectorTextContains('a[href^="/taches/nouvelle/a-faire?modele="]', 'Détartrer la bouilloire');
    }

    public function testNotingWhatWasAlreadyDone(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Aspirateur', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '7', 'task[points]' => '20']);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Aspirateur']);
        $doneAt = new \DateTimeImmutable('-2 days 18:00');

        $this->client->request('GET', '/taches/nouvelle/deja-fait');
        $this->client->submitForm('C’est fait', [
            'log_completion[task]' => (string) $task?->getId(),
            'log_completion[doneAt]' => $doneAt->format('Y-m-d\TH:i'),
        ]);
        self::assertResponseRedirects('/');

        $completion = static::getContainer()->get(CompletionRepository::class)->findOneBy(['task' => $task?->getId()]);
        self::assertInstanceOf(Completion::class, $completion);
        self::assertSame($doneAt->format('Y-m-d H:i'), $completion->getCompletedAt()->format('Y-m-d H:i'));
        self::assertSame($leo->getId(), $completion->getMember()->getId());

        // Something else, done once: the one-off form, already ticked "done".
        $this->client->request('GET', '/taches/nouvelle/deja-fait');
        $this->client->clickLink('Noter autre chose');
        self::assertSame('checked', $this->client->getCrawler()->filter('#task_done')->attr('checked'));
    }

    public function testOldLinksToTheCatalogueStillWork(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/taches/nouvelle?modele=42');

        self::assertResponseRedirects('/taches/nouvelle/a-faire?modele=42');
    }
}
