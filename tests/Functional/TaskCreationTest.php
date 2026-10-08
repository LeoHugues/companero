<?php

namespace App\Tests\Functional;

use App\Entity\Completion;
use App\Repository\CompletionRepository;
use App\Repository\TaskRepository;

/** The "+" button: say first what it is about, then fill in only what matters. */
final class TaskCreationTest extends AppTestCase
{
    public function testThePlusButtonAsksWhatItIsAbout(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/taches/nouvelle');

        self::assertSelectorExists('a[href="/taches/nouvelle/a-faire"]');
        self::assertSelectorExists('a[href="/taches/nouvelle/deja-fait"]');
        self::assertSelectorExists('a[href="/taches/nouvelle/modele"]');
        self::assertSelectorTextContains('main', 'Étape 1/2');
    }

    public function testATaskToDoIsDoneOnceAndATemplateComesBack(): void
    {
        $this->client->loginUser($this->foundHousehold());

        // Something to do once: no question about how it comes back.
        $this->client->request('GET', '/taches/nouvelle/a-faire');
        self::assertSelectorTextContains('main', 'Étape 2/2');
        self::assertSelectorNotExists('input[name="task[kind]"]');
        self::assertSelectorExists('input[name="task[reserve]"]');

        // A template comes back: never a one-off task.
        $this->client->request('GET', '/taches/nouvelle/modele');
        self::assertSelectorExists('input[name="task[kind]"][value=rolling]');
        self::assertSelectorExists('input[name="task[kind]"][value=quick]');
        self::assertSelectorNotExists('input[name="task[kind]"][value=one_off]');
        self::assertSelectorNotExists('input[name="task[reserve]"]');
        $this->client->submitForm('Créer le modèle', ['task[title]' => 'Serpillière', 'task[kind]' => 'rolling', 'task[rhythmDays]' => '7']);
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role=status]', 'Le modèle « Serpillière » est créé');
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
