<?php

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Entity\Task;
use App\Enum\TaskKind;
use App\Household\MemberRegistrar;
use App\Repository\MemberRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class OnboardingTest extends AppTestCase
{
    public function testAnonymousVisitorIsSentToTheLoginPage(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/connexion');
    }

    public function testFoundingAHouseholdLeadsToTheTour(): void
    {
        $this->client->request('GET', '/bienvenue');
        $this->client->submitForm('C’est parti', [
            'founding[householdName]' => 'La coloc des Lilas',
            'founding[cleaningDay]' => '6',
            'founding[name]' => 'Léo',
            'founding[username]' => 'Léo',
            'founding[plainPassword]' => 'companero',
        ]);

        self::assertResponseRedirects('/decouverte');
        $leo = $this->member('léo');
        self::assertFalse($leo->isOnboarded());
        self::assertCount(6, $leo->getHousehold()->getCharterRules());
    }

    public function testFoundingFormIsValidated(): void
    {
        $this->client->request('GET', '/bienvenue');
        $this->client->submitForm('C’est parti', [
            'founding[householdName]' => '',
            'founding[name]' => 'Léo',
            'founding[username]' => 'Léo le grand',
            'founding[plainPassword]' => 'court',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Donne un nom à ta coloc.');
        self::assertSelectorTextContains('body', 'sans espace');
        self::assertSelectorTextContains('body', 'Au moins 8 caractères.');
    }

    public function testLoggingInWithTheUsernameWhateverItsCase(): void
    {
        $this->foundHousehold();

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['pseudo' => ' LÉO ', 'password' => 'companero']);

        self::assertResponseRedirects('http://localhost/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'La Casa');
    }

    public function testJoiningWithTheInvitationLink(): void
    {
        $founder = $this->foundHousehold();

        $this->client->request('GET', '/rejoindre/'.$founder->getHousehold()->getInviteToken());
        self::assertSelectorTextContains('h1', 'Rejoindre La coloc');
        $this->client->submitForm('Je rejoins la coloc', [
            'registration[name]' => 'Inès',
            'registration[username]' => 'ines',
            'registration[plainPassword]' => 'companero',
        ]);

        self::assertResponseRedirects('/decouverte');
        self::assertSame($founder->getHousehold()->getId(), $this->member('ines')->getHousehold()->getId());
    }

    public function testAUsernameCanOnlyBeUsedOnce(): void
    {
        $founder = $this->foundHousehold();

        $this->client->request('GET', '/rejoindre/'.$founder->getHousehold()->getInviteToken());
        $this->client->submitForm('Je rejoins la coloc', [
            'registration[name]' => 'Léo bis',
            'registration[username]' => 'LÉO',
            'registration[plainPassword]' => 'companero',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Ce pseudo est déjà pris.');
    }

    public function testClaimingTheProfileWaitingThere(): void
    {
        $leo = $this->foundHousehold();
        $robin = static::getContainer()->get(MemberRegistrar::class)->addUnclaimed($leo->getHousehold(), 'Robin');
        $task = $this->taskFor($leo, $robin);
        $link = '/rejoindre/'.$leo->getHousehold()->getInviteToken();

        $this->client->request('GET', $link);
        self::assertSelectorTextContains('main', 'Je suis Robin');
        $this->client->clickLink('Je suis Robin');
        self::assertSelectorTextContains('h1', 'Je suis Robin');
        $this->client->submitForm('C’est moi', [
            'registration[username]' => 'robin',
            'registration[plainPassword]' => 'companero',
        ]);

        self::assertResponseRedirects('/decouverte');
        $claimed = $this->member('robin');
        self::assertSame($robin->getId(), $claimed->getId());
        self::assertSame($claimed->getId(), static::getContainer()->get(TaskRepository::class)->find($task->getId())?->getAssignee()?->getId());

        // Once claimed, nobody else can take it.
        $this->client->request('GET', '/deconnexion');
        $this->client->request('GET', $link);
        self::assertSelectorTextContains('h1', 'Rejoindre La coloc');
        self::assertSelectorNotExists('a[href*="/profil/"]');
        $this->client->request('GET', $link.'/profil/'.$robin->getId());
        self::assertResponseRedirects('/connexion');
    }

    public function testSomeoneNewWhenProfilesWaitToBeClaimed(): void
    {
        $leo = $this->foundHousehold();
        static::getContainer()->get(MemberRegistrar::class)->addUnclaimed($leo->getHousehold(), 'Robin');

        $this->client->request('GET', '/rejoindre/'.$leo->getHousehold()->getInviteToken());
        $this->client->clickLink('Je suis quelqu’un d’autre');
        $this->client->submitForm('Je rejoins la coloc', [
            'registration[name]' => 'Inès',
            'registration[username]' => 'ines',
            'registration[plainPassword]' => 'companero',
        ]);

        self::assertResponseRedirects('/decouverte');
        self::assertCount(3, $this->member('ines')->getHousehold()->getMembers());
    }

    public function testAProfileOfAnotherHouseholdCannotBeClaimed(): void
    {
        $leo = $this->foundHousehold();
        $zoe = $this->foundHousehold('Zoé', household: 'Une autre coloc');
        $other = static::getContainer()->get(MemberRegistrar::class)->addUnclaimed($zoe->getHousehold(), 'Max');

        $this->client->request('GET', '/rejoindre/'.$leo->getHousehold()->getInviteToken().'/profil/'.$other->getId());

        self::assertResponseStatusCodeSame(404);
    }

    public function testPreparingTheProfileOfACotenant(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);

        $this->client->request('GET', '/coloc');
        $this->client->submitForm('Préparer son profil', ['prenom' => 'Gab']);

        self::assertResponseRedirects('/coloc');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'à réclamer');
        self::assertSame(['Gab'], array_map(static fn (Member $member): string => $member->getName(), $this->reload($leo)->getHousehold()->getUnclaimedMembers()));
    }

    public function testReleasingAccountsKeepsWhatIsAttachedToThem(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $task = $this->taskFor($leo, $robin);

        $tester = new CommandTester((new Application(static::bootKernel()))->find('app:membre:a-reclamer'));
        $tester->execute(['who' => ['Robin']]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('/rejoindre/', $tester->getDisplay());
        $released = static::getContainer()->get(MemberRepository::class)->find($robin->getId());
        self::assertInstanceOf(Member::class, $released);
        self::assertFalse($released->isClaimed());
        self::assertNull(static::getContainer()->get(MemberRepository::class)->loadUserByIdentifier('robin'));
        self::assertSame($robin->getId(), static::getContainer()->get(TaskRepository::class)->find($task->getId())?->getAssignee()?->getId());
    }

    public function testTheLastAccountIsNeverReleased(): void
    {
        $this->foundHousehold();

        $tester = new CommandTester((new Application(static::bootKernel()))->find('app:membre:a-reclamer'));
        $tester->execute(['who' => ['Léo']]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertTrue($this->member('léo')->isClaimed());
    }

    public function testUnknownInvitationLink(): void
    {
        $this->client->request('GET', '/rejoindre/nope');

        self::assertResponseStatusCodeSame(404);
    }

    private function member(string $username): Member
    {
        $member = static::getContainer()->get(MemberRepository::class)->loadUserByIdentifier($username);
        self::assertInstanceOf(Member::class, $member);

        return $member;
    }

    /** A task in someone's charge: what must stay theirs whoever logs in with the profile. */
    private function taskFor(Member $author, Member $assignee): Task
    {
        $task = new Task($author->getHousehold(), $author, new \DateTimeImmutable('-1 day'));
        $task->setTitle('Sortir les poubelles');
        $task->setKind(TaskKind::Quick);
        $task->setPoints(10);
        $task->setAssignee($assignee);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($task);
        $entityManager->flush();

        return $task;
    }
}
