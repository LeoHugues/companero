<?php

namespace App\Tests\Functional;

use App\Repository\MemberRepository;

final class OnboardingTest extends AppTestCase
{
    public function testAnonymousVisitorIsSentToTheLoginPage(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/connexion');
    }

    public function testFoundingAHouseholdLogsTheFounderIn(): void
    {
        $this->client->request('GET', '/bienvenue');
        $this->client->submitForm('C’est parti', [
            'founding[householdName]' => 'La coloc des Lilas',
            'founding[cleaningDay]' => '6',
            'founding[name]' => 'Léo',
            'founding[email]' => 'leo@example.com',
            'founding[plainPassword]' => 'companero',
        ]);

        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'La Casa');
        self::assertSelectorTextContains('#team-title', 'Ensemble cette semaine');
    }

    public function testFoundingFormIsValidated(): void
    {
        $this->client->request('GET', '/bienvenue');
        $this->client->submitForm('C’est parti', [
            'founding[householdName]' => '',
            'founding[name]' => 'Léo',
            'founding[email]' => 'pas-un-email',
            'founding[plainPassword]' => 'court',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Donne un nom à ta coloc.');
        self::assertSelectorTextContains('body', 'Au moins 8 caractères.');
    }

    public function testJoiningWithTheInvitationLink(): void
    {
        $founder = $this->foundHousehold();

        $this->client->request('GET', '/rejoindre/'.$founder->getHousehold()->getInviteToken());
        self::assertSelectorTextContains('h1', 'Rejoindre La coloc');
        $this->client->submitForm('Je rejoins la coloc', [
            'registration[name]' => 'Inès',
            'registration[email]' => 'ines@example.com',
            'registration[plainPassword]' => 'companero',
        ]);

        self::assertResponseRedirects('/');
        $ines = static::getContainer()->get(MemberRepository::class)->findOneBy(['email' => 'ines@example.com']);
        self::assertSame($founder->getHousehold()->getId(), $ines?->getHousehold()->getId());
    }

    public function testAnEmailCanOnlyBeUsedOnce(): void
    {
        $founder = $this->foundHousehold();

        $this->client->request('GET', '/rejoindre/'.$founder->getHousehold()->getInviteToken());
        $this->client->submitForm('Je rejoins la coloc', [
            'registration[name]' => 'Léo bis',
            'registration[email]' => 'leo@example.com',
            'registration[plainPassword]' => 'companero',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Cette adresse est déjà utilisée.');
    }

    public function testUnknownInvitationLink(): void
    {
        $this->client->request('GET', '/rejoindre/nope');

        self::assertResponseStatusCodeSame(404);
    }
}
