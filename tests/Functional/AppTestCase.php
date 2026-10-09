<?php

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Household\Founding;
use App\Household\HouseholdFounder;
use App\Household\MemberRegistrar;
use App\Household\Registration;
use App\Repository\MemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AppTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Stateless CSRF protection trusts same-origin requests.
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
    }

    /** @param bool $onboarded false: the tour is still ahead of them */
    protected function foundHousehold(string $name = 'Léo', ?string $username = null, string $household = 'La coloc', bool $cleaningDayBoost = false, bool $onboarded = true): Member
    {
        $founding = new Founding();
        $founding->householdName = $household;
        $founding->name = $name;
        $founding->username = $username ?? $name;
        $founding->plainPassword = 'companero';

        $member = static::getContainer()->get(HouseholdFounder::class)->found($founding);
        // Tests must not earn more on the day of the week that happens to be the cleaning day.
        $member->getHousehold()->setCleaningDayBoost($cleaningDayBoost);

        return $this->onboard($member, $onboarded);
    }

    /** @param bool $onboarded false: the tour is still ahead of them */
    protected function register(Member $founder, string $name, bool $onboarded = true): Member
    {
        $registration = new Registration();
        $registration->name = $name;
        $registration->username = $name;
        $registration->plainPassword = 'companero';

        return $this->onboard(static::getContainer()->get(MemberRegistrar::class)->register($founder->getHousehold(), $registration), $onboarded);
    }

    /** The tour is behind them and the charter agreed to: straight to the pages under test. */
    private function onboard(Member $member, bool $onboarded): Member
    {
        if ($onboarded) {
            // An hour ago: a change to the charter during the test comes after it (times are kept to the second).
            $before = new \DateTimeImmutable('-1 hour');
            $member->finishOnboarding($before);
            $member->acceptCharter($before);
        }
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $member;
    }

    /** The same member, managed by the entity manager of the current kernel (it is rebooted between requests). */
    protected function reload(Member $member): Member
    {
        return static::getContainer()->get(MemberRepository::class)->find($member->getId()) ?? throw new \LogicException('Member not found.');
    }

    /** Submits one of the small action forms (C’est fait, Je m’en occupe…) found on the current page. */
    protected function submitAction(string $action): void
    {
        $form = $this->client->getCrawler()->filter(\sprintf('form[action="%s"]', $action))->form();
        $this->client->submit($form);
    }
}
