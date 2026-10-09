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
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;

abstract class AppTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Stateless CSRF protection trusts same-origin requests.
        $this->client->setServerParameter('HTTP_ORIGIN', 'http://localhost');
    }

    protected function foundHousehold(string $name = 'Léo', string $email = 'leo@example.com', string $household = 'La coloc', bool $cleaningDayBoost = false): Member
    {
        $founding = new Founding();
        $founding->householdName = $household;
        $founding->name = $name;
        $founding->email = $email;
        $founding->plainPassword = 'companero';

        $member = static::getContainer()->get(HouseholdFounder::class)->found($founding);
        // Tests must not earn more on the day of the week that happens to be the cleaning day.
        $member->getHousehold()->setCleaningDayBoost($cleaningDayBoost);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $member;
    }

    protected function register(Member $founder, string $name): Member
    {
        $registration = new Registration();
        $registration->name = $name;
        $registration->email = strtolower($name).'@example.com';
        $registration->plainPassword = 'companero';

        return static::getContainer()->get(MemberRegistrar::class)->register($founder->getHousehold(), $registration);
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

    /** Ticks the rooms of a task form, by name. */
    protected function tickRooms(Form $form, string ...$rooms): void
    {
        foreach ($this->client->getCrawler()->filter('#task_zones label') as $label) {
            if (\in_array($label->textContent, $rooms, true)) {
                $box = $this->client->getCrawler()->filter('#'.$label->getAttribute('for'));
                foreach ($form->get('task[zones]') as $field) {
                    if ($field instanceof ChoiceFormField && \in_array($box->attr('value'), $field->availableOptionValues(), true)) {
                        $field->select($box->attr('value'));
                    }
                }
            }
        }
    }
}
