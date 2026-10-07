<?php

namespace App\Tests\Functional;

use App\Native\NativeConfiguration;

final class NativeAppTest extends AppTestCase
{
    private const ANDROID_USER_AGENT = 'Companero; Hotwire Native Android; Turbo Native Android; bridge-components: [];';

    public function testTheAndroidAppCanLoadItsPathConfigurationBeforeLoggingIn(): void
    {
        $this->client->request('GET', NativeConfiguration::ANDROID_PATH);

        self::assertResponseIsSuccessful();
        $configuration = json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['.*'], $configuration['rules'][0]['patterns']);
        // Hotwire Native reads "settings" as an object: an empty list would break the whole file.
        self::assertArrayNotHasKey('settings', $configuration);
    }

    public function testTheTaskFormIsAModalWithoutBottomNavigationInTheApp(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/taches/nouvelle', server: ['HTTP_USER_AGENT' => self::ANDROID_USER_AGENT]);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('nav[aria-label="Navigation principale"]');
        self::assertSelectorExists('a[aria-label="Fermer"][href="/recede_historical_location"]');
    }

    public function testTheTaskFormKeepsItsBottomNavigationInTheBrowser(): void
    {
        $this->client->loginUser($this->foundHousehold());

        $this->client->request('GET', '/taches/nouvelle');

        self::assertSelectorExists('nav[aria-label="Navigation principale"]');
        self::assertSelectorExists('a[aria-label="Fermer"][href="/"]');
    }
}
