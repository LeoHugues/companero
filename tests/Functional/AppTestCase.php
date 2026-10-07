<?php

namespace App\Tests\Functional;

use App\Entity\Member;
use App\Household\Founding;
use App\Household\HouseholdFounder;
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

    protected function foundHousehold(string $name = 'Léo', string $email = 'leo@example.com', string $household = 'La coloc'): Member
    {
        $founding = new Founding();
        $founding->householdName = $household;
        $founding->name = $name;
        $founding->email = $email;
        $founding->plainPassword = 'companero';

        return static::getContainer()->get(HouseholdFounder::class)->found($founding);
    }

    /** Submits one of the small action forms (C’est fait, Je m’en occupe…) found on the current page. */
    protected function submitAction(string $action): void
    {
        $form = $this->client->getCrawler()->filter(\sprintf('form[action="%s"]', $action))->form();
        $this->client->submit($form);
    }
}
