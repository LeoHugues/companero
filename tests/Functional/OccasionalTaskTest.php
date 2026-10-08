<?php

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Entity\Task;
use App\Enum\TaskCategory;
use App\Enum\TaskKind;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Occasional tasks, and pages refreshed in place after a "C'est fait". */
final class OccasionalTaskTest extends AppTestCase
{
    public function testAnOccasionalTaskSleepsUntilItIsRaisedThenGoesBackToSleepOnceDone(): void
    {
        $leo = $this->foundHousehold();
        $task = $this->occasional($leo, 'Nettoyer le vomi de Gizmo');

        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        // Asleep: no card, a chip to say it happens.
        self::assertSelectorNotExists('#task-'.$task->getId());
        self::assertSelectorTextContains('[aria-labelledby=raise-title]', 'Nettoyer le vomi de Gizmo');

        $this->submitAction('/taches/'.$task->getId().'/ca-arrive');
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[aria-labelledby=todo-title] #task-'.$task->getId(), 'Ça vient d’arriver');
        self::assertSelectorNotExists('[aria-labelledby=raise-title]');

        $this->submitAction('/taches/'.$task->getId().'/fait');
        $this->client->followRedirect();
        self::assertSelectorNotExists('#task-'.$task->getId());
        self::assertSelectorTextContains('[aria-labelledby=raise-title]', 'Nettoyer le vomi de Gizmo');
        self::assertFalse(static::getContainer()->get(TaskRepository::class)->find($task->getId())?->isRaised());
    }

    public function testOccasionalIsAKindOfTemplate(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);
        $this->client->request('GET', '/taches/nouvelle/modele');
        $this->client->submitForm('Créer le modèle', [
            'task[title]' => 'Réparer la chasse d’eau',
            'task[kind]' => 'occasional',
            'task[category]' => 'repair',
            'task[points]' => '40',
        ]);
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Réparer la chasse d’eau']);
        self::assertSame(TaskKind::Occasional, $task?->getKind());

        $this->client->request('GET', '/taches');
        self::assertSelectorTextContains('[aria-labelledby=occasional-title]', 'Réparer la chasse d’eau');
        $this->client->request('GET', '/taches/'.$task->getId().'/modele');
        self::assertSelectorTextContains('main', 'Ça arrive !');
    }

    public function testOnlyAnOccasionalTaskCanBeRaised(): void
    {
        $leo = $this->foundHousehold();
        $task = new Task($leo->getHousehold(), $leo, new \DateTimeImmutable('-1 day'));
        $task->setTitle('Aspirateur');
        $task->setKind(TaskKind::Rolling);
        $task->setRhythmDays(7);
        $this->persist($task);

        $this->client->loginUser($leo);
        $this->client->request('POST', '/taches/'.$task->getId().'/ca-arrive', ['_csrf_token' => 'csrf-token']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDoneFromTheHomePageTheAppRefreshesItInPlace(): void
    {
        $leo = $this->foundHousehold();
        $task = $this->occasional($leo, 'Nettoyer le vomi de Gizmo');
        $task->raise(new \DateTimeImmutable('-1 hour'));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->client->loginUser($leo);
        $this->client->request('GET', '/');
        $form = $this->client->getCrawler()->filter('form[action="/taches/'.$task->getId().'/fait"]')->form();
        // Turbo asks for a stream: the new home page comes back to be morphed in, celebration included.
        $this->client->request('POST', $form->getUri(), $form->getPhpValues(), [], ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html', 'HTTP_REFERER' => 'http://localhost/']);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/vnd.turbo-stream.html; charset=UTF-8');
        $stream = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<turbo-stream action="update" method="morph" targets="body > main">', $stream);
        self::assertStringContainsString('reward-toast', $stream);
        self::assertStringContainsString('Merci Léo ! +15 pts', $stream);

        // Sent back to another page, it stays a redirect.
        $this->client->request('POST', '/taches/'.$task->getId().'/ca-arrive', ['_csrf_token' => 'csrf-token', '_back' => 'tasks'], [], ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html', 'HTTP_REFERER' => 'http://localhost/']);
        self::assertResponseRedirects('/taches');
    }

    private function occasional(Member $author, string $title): Task
    {
        $task = new Task($author->getHousehold(), $author, new \DateTimeImmutable('-30 days'));
        $task->setTitle($title);
        $task->setKind(TaskKind::Occasional);
        $task->setCategory(TaskCategory::Pets);
        $task->setPoints(15);
        $this->persist($task);

        return $task;
    }

    private function persist(object $entity): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();
    }
}
