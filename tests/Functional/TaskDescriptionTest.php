<?php

namespace App\Tests\Functional;

use App\Entity\Task;
use App\Repository\TaskRepository;

final class TaskDescriptionTest extends AppTestCase
{
    public function testTheTemplatesPrecisionGivesWayToANoteForThisTime(): void
    {
        $this->client->loginUser($this->foundHousehold());
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', [
            'task[title]' => 'Aspirateur du salon',
            'task[description]' => '  Aspirateur ou balai, tapis compris  ',
            'task[kind]' => 'rolling',
            'task[rhythmDays]' => '2',
        ]);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Aspirateur du salon']);
        self::assertInstanceOf(Task::class, $task);
        $page = '/taches/'.$task->getId();

        // Always on the card.
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#task-'.$task->getId().' .task-card-note', 'Aspirateur ou balai, tapis compris');
        $this->client->request('GET', $page.'/modele');
        self::assertSelectorTextContains('main', 'Aspirateur ou balai, tapis compris');

        // A note for this time takes its place.
        $this->client->request('GET', $page);
        $this->client->submit($this->client->getCrawler()->filter(\sprintf('form[action="%s/note"]', $page))->form(['note' => 'Le tapis est au pressing']));
        $this->client->request('GET', $page);
        self::assertSelectorTextContains('#task-note', 'Le tapis est au pressing');
        self::assertSelectorTextContains('main', 'D’habitude : « Aspirateur ou balai, tapis compris »');
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#task-'.$task->getId().' .task-card-note', 'Le tapis est au pressing');

        // Done: the note goes, the precision comes back.
        $this->client->request('GET', $page);
        $this->submitAction($page.'/fait');
        $this->client->request('GET', $page);
        self::assertSelectorTextContains('#task-note', 'Aspirateur ou balai, tapis compris');
        self::assertSelectorTextNotContains('main', 'Le tapis est au pressing');
    }
}
