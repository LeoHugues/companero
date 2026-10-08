<?php

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Entity\Task;
use App\Enum\PetSpecies;
use App\Household\MemberRegistrar;
use App\Household\Registration;
use App\Presence\PresenceRecorder;
use App\Repository\MemberRepository;
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

        $this->client->request('GET', '/taches/nouvelle');
        $form = $this->client->getCrawler()->selectButton('Ajouter la tâche')->form();
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

        $leo = static::getContainer()->get(MemberRepository::class)->find($leo->getId());
        static::getContainer()->get(PresenceRecorder::class)->setAtHome($leo, false);
        $this->client->loginUser($robin);
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('[aria-labelledby=mine-title]', 'Léo n’est pas là : on compte sur toi');

        $this->client->request('GET', '/api/rappels');
        $reminders = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Nourrir les chats', $reminders[0]['title']);
        self::assertSame('Léo', $reminders[0]['standingInFor']);
    }

    private function register(Member $founder, string $name): Member
    {
        $registration = new Registration();
        $registration->name = $name;
        $registration->email = strtolower($name).'@example.com';
        $registration->plainPassword = 'companero';

        return static::getContainer()->get(MemberRegistrar::class)->register($founder->getHousehold(), $registration);
    }
}
