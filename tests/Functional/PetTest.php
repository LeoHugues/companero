<?php

namespace App\Tests\Functional;

use App\Entity\Task;
use App\Enum\PetSpecies;
use App\Presence\PresenceRecorder;
use App\Repository\TaskRepository;

final class PetTest extends AppTestCase
{
    public function testAddingAPet(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/coloc');
        $this->client->submitForm('Ajouter l’animal', ['pet[name]' => 'Tishka', 'pet[description]' => 'Rousse, à poils longs']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('[aria-labelledby=pets-title]', 'Tishka');
        self::assertSelectorTextContains('[aria-labelledby=pets-title]', 'Chat · Rousse, à poils longs');
    }

    public function testThePetTaskGoesToTheBackupWhenTheAssigneeIsAway(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $this->client->loginUser($leo);
        $this->client->request('GET', '/coloc');
        $this->client->submitForm('Ajouter l’animal', ['pet[name]' => 'Tishka', 'pet[species]' => PetSpecies::Cat->value]);

        $this->client->request('GET', '/taches/nouvelle/modele');
        $form = $this->client->getCrawler()->selectButton('Créer le modèle')->form();
        $this->client->submit($form, [
            'task[title]' => 'Nourrir les chats',
            'task[kind]' => 'scheduled',
            'task[scheduledWeekday]' => (string) Task::EVERY_DAY,
            'task[scheduledTime]' => (new \DateTimeImmutable('+1 hour'))->format('H:i'),
            'task[category]' => 'pets',
            'task[pet]' => $form['task[pet]']->availableOptionValues()[1],
            'task[assignee]' => (string) $leo->getId(),
            'task[backup]' => (string) $robin->getId(),
        ]);
        self::assertResponseRedirects('/');
        $task = static::getContainer()->get(TaskRepository::class)->findOneBy(['title' => 'Nourrir les chats']);
        self::assertSame('Tishka', $task?->getPet()?->getName());

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('[aria-labelledby=mine-title]', 'C’est toi qui t’en charges');

        $leo = $this->reload($leo);
        static::getContainer()->get(PresenceRecorder::class)->setAtHome($leo, false);
        $this->client->loginUser($robin);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('[aria-labelledby=mine-title]', 'Léo n’est pas là : on compte sur toi');

        $this->client->request('GET', '/api/rappels');
        $reminders = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Nourrir les chats', $reminders[0]['title']);
        self::assertSame('Léo', $reminders[0]['standingInFor']);
    }

    public function testAPetsHumansAreRemindedFirstAndTheOthersWhenTheyAreAway(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $this->client->loginUser($leo);
        $this->client->request('GET', '/coloc');
        $this->client->submitForm('Ajouter l’animal', ['pet[name]' => 'Tishka', 'pet[owners]' => [(string) $leo->getId()]]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[aria-labelledby=pets-title]', 'À Léo');

        $this->client->request('GET', '/taches/nouvelle/modele');
        $form = $this->client->getCrawler()->selectButton('Créer le modèle')->form();
        $this->client->submit($form, [
            'task[title]' => 'Litière',
            'task[kind]' => 'rolling',
            'task[rhythmDays]' => '2',
            'task[category]' => 'pets',
            'task[pet]' => $form['task[pet]']->availableOptionValues()[1],
        ]);

        // Léo's cat: his notification, not Robin's.
        $this->client->request('GET', '/api/notifications');
        self::assertSame(['Litière'], array_column($this->json(), 'title'));
        $this->client->loginUser($robin);
        $this->client->request('GET', '/api/notifications');
        self::assertSame([], $this->json());

        // Léo is out: Robin is on.
        static::getContainer()->get(PresenceRecorder::class)->setAtHome($this->reload($leo), false);
        $this->client->request('GET', '/api/notifications');
        $notices = $this->json();
        self::assertSame('Litière', $notices[0]['title']);
        self::assertStringContainsString('Léo n’est pas là : on compte sur toi', $notices[0]['body']);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('[aria-labelledby=mine-title]', 'Léo n’est pas là : on compte sur toi');

        // A test first, on demand (without the cleaning day's or the review's, which it would count on their day); nothing about tasks for whoever turned them off.
        $this->client->request('GET', '/profil');
        $this->client->submitForm('Enregistrer', ['profile[notifyCleaningDay]' => false, 'profile[notifyWeeklyReview]' => false]);
        $this->client->request('GET', '/api/notifications?test=1');
        self::assertStringStartsWith('Les notifications marchent ! 1 rappel', $this->json()[0]['body']);
        $this->client->request('GET', '/profil');
        $this->client->submitForm('Enregistrer', ['profile[notifyOverdue]' => false]);
        $this->client->request('GET', '/api/notifications');
        self::assertSame([], $this->json());
    }

    /**
     * @return list<array{key: string, title: string, body: string, path: string}> the notices about tasks and tests
     *                                                                             (not the cleaning day's or the review's: they depend on the day the tests run)
     */
    private function json(): array
    {
        self::assertResponseIsSuccessful();
        $notices = json_decode((string) $this->client->getResponse()->getContent(), true);

        return array_values(array_filter($notices, static fn (array $notice): bool => !str_starts_with($notice['key'], 'menage-') && !str_starts_with($notice['key'], 'bilan-')));
    }
}
