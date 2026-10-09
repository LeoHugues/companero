<?php

namespace App\Tests\Functional;

use App\Calendar\Week;
use App\Repository\PresenceRepository;

final class TourTest extends AppTestCase
{
    public function testEveryPageLeadsToTheTourUntilItIsFinished(): void
    {
        $this->client->loginUser($this->foundHousehold(onboarded: false));

        foreach (['/', '/profil', '/taches', '/coloc'] as $page) {
            $this->client->request('GET', $page);
            self::assertResponseRedirects('/decouverte', message: $page);
        }

        // What the Android app fetches on its own goes on.
        $this->client->request('GET', '/api/rappels');
        self::assertResponseIsSuccessful();
    }

    public function testTheTourFromStartToFinish(): void
    {
        $leo = $this->foundHousehold(onboarded: false);
        $this->client->loginUser($leo);

        $this->client->request('GET', '/decouverte');
        self::assertResponseRedirects('/decouverte/esprit');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Bienvenue dans La coloc');

        foreach (['carte' => 'Une tâche, une carte', 'glissement' => 'Le glissement', 'rythmes' => 'Les autres rythmes', 'points' => 'Les points', 'objectifs' => 'Les objectifs'] as $step => $heading) {
            $this->client->clickLink('Suivant');
            self::assertResponseIsSuccessful();
            self::assertSame('/decouverte/'.$step, $this->client->getRequest()->getPathInfo());
            self::assertSelectorTextContains('h1', $heading);
        }

        // The settings wait for the charter.
        $this->client->request('GET', '/decouverte/reglages');
        self::assertResponseRedirects('/decouverte/charte');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'On fait pipi assis.');
        $this->client->submitForm('Ça me va');
        self::assertResponseRedirects('/decouverte/reglages');
        $this->client->followRedirect();

        // A goal must be chosen.
        $this->client->submitForm('C’est parti', ['tour_settings[presenceDays]' => '5']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Choisis ton objectif');

        $this->client->submitForm('C’est parti', [
            'tour_settings[goal]' => '140',
            'tour_settings[presenceDays]' => '5',
            'tour_settings[notifyOverdue]' => false,
        ]);
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'La Casa');

        $leo = $this->reload($leo);
        self::assertTrue($leo->isOnboarded());
        self::assertTrue($leo->hasAcceptedCharter());
        self::assertSame(140, $leo->getWeeklyGoal());
        self::assertFalse($leo->isNotifyOverdue());
        self::assertSame(5, static::getContainer()->get(PresenceRepository::class)->daysOf($leo, Week::containing(new \DateTimeImmutable())));
    }

    public function testTheTourCanBeReadAgainFromTheProfile(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/profil');
        $this->client->clickLink('Comment ça marche');
        self::assertResponseRedirects('/decouverte/esprit');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Comment ça marche');

        $this->client->request('GET', '/decouverte/objectifs');
        self::assertSelectorExists('nav a[href="/profil"]');
        self::assertSelectorTextContains('nav', 'Terminer');

        $this->client->request('GET', '/decouverte/reglages');
        self::assertResponseRedirects('/profil');
        $this->client->request('GET', '/decouverte/charte');
        self::assertResponseRedirects('/coloc/charte');
    }
}
