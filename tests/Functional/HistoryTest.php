<?php

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Entity\Task;
use App\Enum\TaskKind;
use Doctrine\ORM\EntityManagerInterface;

final class HistoryTest extends AppTestCase
{
    public function testMyTasksAndThoseOfTheHousehold(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $dishes = $this->quickTask($leo, 'Vider le lave-vaisselle', 10);
        $glass = $this->quickTask($leo, 'Faire le verre', 20);

        $this->client->loginUser($robin);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$glass->getId().'/fait');
        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $this->submitAction('/taches/'.$dishes->getId().'/fait');

        $this->client->request('GET', '/fait');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ce que j’ai fait');
        self::assertSelectorTextContains('[aria-labelledby=days-title]', 'Vider le lave-vaisselle');
        self::assertSelectorTextNotContains('[aria-labelledby=days-title]', 'Faire le verre');
        self::assertSelectorTextContains('[aria-label="En résumé"]', '10');

        $this->client->request('GET', '/fait?qui=coloc&jours=30');
        self::assertSelectorTextContains('h1', 'Ce que la coloc a fait');
        self::assertSelectorTextContains('[aria-labelledby=days-title]', 'Faire le verre');
        self::assertSelectorTextContains('[aria-labelledby=share-title] ul', 'Robin');
        self::assertCount(30, $this->client->getCrawler()->filter('[aria-label="Points par jour"] li'));
    }

    private function quickTask(Member $author, string $title, int $points): Task
    {
        $task = new Task($author->getHousehold(), $author, new \DateTimeImmutable('-1 day'));
        $task->setTitle($title);
        $task->setKind(TaskKind::Quick);
        $task->setPoints($points);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($task);
        $entityManager->flush();

        return $task;
    }
}
