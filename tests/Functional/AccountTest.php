<?php

namespace App\Tests\Functional;

final class AccountTest extends AppTestCase
{
    public function testChangingMyUsernameAndPassword(): void
    {
        $leo = $this->foundHousehold();
        $this->client->loginUser($leo);

        $this->client->request('GET', '/profil/compte');
        $this->client->submitForm('Enregistrer', [
            'account[username]' => 'Leon',
            'account[newPassword]' => 'nouveau-secret',
            'account[currentPassword]' => 'companero',
        ]);
        self::assertResponseRedirects('/profil');
        self::assertSame('leon', $this->reload($leo)->getUsername());

        // Still logged in, and the new way in works.
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/deconnexion');
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', ['pseudo' => 'leon', 'password' => 'nouveau-secret']);
        self::assertResponseRedirects('http://localhost/');
    }

    public function testTheCurrentPasswordIsAskedAndUsernamesStayUnique(): void
    {
        $leo = $this->foundHousehold();
        $this->register($leo, 'Robin');
        $this->client->loginUser($leo);

        $this->client->request('GET', '/profil/compte');
        $this->client->submitForm('Enregistrer', ['account[username]' => 'léo', 'account[currentPassword]' => 'pas le bon']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Ce n’est pas ton mot de passe actuel.');

        $this->client->submitForm('Enregistrer', ['account[username]' => 'ROBIN', 'account[currentPassword]' => 'companero']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Ce pseudo est déjà pris.');
        self::assertSame('léo', $this->reload($leo)->getUsername());
    }
}
