<?php

namespace App\Tests\Functional;

use App\Entity\Expense;
use App\Entity\Ledger;
use App\Entity\Member;
use App\Entity\Participant;
use App\Enum\ExpenseKind;
use App\Repository\ExpenseRepository;
use App\Repository\LedgerRepository;
use Doctrine\ORM\EntityManagerInterface;

/** The accounts kept together, like tricounts: expenses, balances, and the reimbursements settled in one tap. */
final class LedgerTest extends AppTestCase
{
    public function testTheColocOpensItsAccountsInOneTapAndSharesAnExpenseEqually(): void
    {
        $leo = $this->foundHousehold();
        $this->register($leo, 'Léa');
        $robin = $this->register($leo, 'Robin');
        $this->client->loginUser($leo);

        $this->client->request('GET', '/comptes');
        self::assertSelectorTextContains('h1', 'Les comptes');
        $this->client->submitForm('Ouvrir les comptes de la coloc');
        $ledger = $this->ledgers($leo)[0];
        self::assertResponseRedirects('/comptes/'.$ledger->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Dépenses communes');
        self::assertSame(['Léa', 'Léo', 'Robin'], $this->names($ledger));

        // Paid by the one who notes it, for everyone, today: a third each.
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', ['expense[title]' => 'Courses Lidl', 'expense[amount]' => '30,00']);
        self::assertResponseRedirects('/comptes/'.$ledger->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'C’est noté : Courses Lidl, '.self::euros('30,00').'.');
        self::assertSelectorTextContains('#ledger-expenses', 'Payé par Léo');
        self::assertSelectorTextContains('#ledger-expenses', 'ta part '.self::euros('10,00'));
        self::assertSelectorTextContains('.ledger-summary-line', 'On te doit');
        self::assertSelectorTextContains('.ledger-summary-balance', '+'.self::euros('20,00'));
        self::assertSelectorTextContains('.ledger-totals', self::euros('30,00'));
        $expense = $this->expenses($ledger)[0];
        self::assertSame('groceries', $expense->getCategory()?->value);

        // The balances: what each one is owed or owes, and who reimburses whom.
        $this->client->request('GET', '/comptes/'.$ledger->getId().'?vue=equilibres');
        self::assertSelectorNotExists('#ledger-balances[hidden]');
        self::assertSelectorTextContains('#balance-'.$this->participant($ledger, 'Léo')->getId(), '+'.self::euros('20,00'));
        self::assertSelectorTextContains('#balance-'.$this->participant($ledger, 'Robin')->getId(), '−'.self::euros('10,00'));
        self::assertSame(['Léa te doit 10,00 €', 'Robin te doit 10,00 €'], $this->settlements());

        // Robin settles from there, in one tap; a second tap notes nothing more.
        $this->client->loginUser($this->reload($robin));
        $this->client->request('GET', '/comptes/'.$ledger->getId().'?vue=equilibres');
        self::assertSame(['Léa doit 10,00 € à Léo', 'Tu dois 10,00 € à Léo'], $this->settlements());
        $settle = $this->client->getCrawler()->filter('button[aria-label="Régler : Robin rembourse '.self::euros('10,00').' à Léo"]')->form();
        $this->client->submit($settle);
        self::assertResponseRedirects('/comptes/'.$ledger->getId().'?vue=equilibres');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'C’est réglé : tu as remboursé '.self::euros('10,00').' à Léo.');
        self::assertSelectorTextContains('#balance-'.$this->participant($ledger, 'Robin')->getId(), 'Quitte');
        self::assertSame(['Léa doit 10,00 € à Léo'], $this->settlements());
        $this->client->submit($settle);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'C’était déjà réglé.');

        $transfers = array_values(array_filter($this->expenses($ledger), static fn (Expense $expense): bool => $expense->isTransfer()));
        self::assertCount(1, $transfers);
        self::assertSame('Remboursement', $transfers[0]->getTitle());
        self::assertSame('Léo', $transfers[0]->getRecipient()?->getName());
        // It is among the expenses, as "Robin → Léo".
        $this->client->request('GET', '/comptes/'.$ledger->getId());
        self::assertSelectorTextContains('#expense-'.$transfers[0]->getId(), 'Robin → Léo');
    }

    public function testAnExpenseIsSplitByPartsOrByAmounts(): void
    {
        $leo = $this->foundHousehold();
        $this->register($leo, 'Léa');
        $this->register($leo, 'Robin');
        $ledger = $this->openCommon($leo);
        [$lea, $me, $robin] = [$this->participant($ledger, 'Léa'), $this->participant($ledger, 'Léo'), $this->participant($ledger, 'Robin')];

        // By parts: two for me, one for Robin, none for Léa (who is not in it, then).
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', [
            'expense[title]' => 'Apéro',
            'expense[amount]' => '30',
            'expense[splitMode]' => 'shares',
            $this->field($me, 'weight') => '2',
            $this->field($robin, 'weight') => '1',
            $this->field($lea, 'weight') => '0',
        ]);
        self::assertResponseRedirects();
        self::assertSame(['Léo' => 2000, 'Robin' => 1000], $this->shares($this->expenses($ledger)[0]));

        // By amounts: they must add up.
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', [
            'expense[title]' => 'Pharmacie',
            'expense[amount]' => '25,50',
            'expense[splitMode]' => 'amounts',
            $this->field($me, 'amount') => '10',
            $this->field($robin, 'amount') => '10,5',
            $this->field($lea, 'included') => false,
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Il reste '.self::euros('5,00').' à répartir.');
        // Unreadable, it is said there too.
        $this->client->submitForm('Noter', [$this->field($me, 'amount') => 'quinze']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.split-remainder', 'Un montant comme 12,50.');
        $this->client->submitForm('Noter', [$this->field($me, 'amount') => '15']);
        self::assertResponseRedirects();
        $pharmacy = $this->expenses($ledger)[0];
        self::assertSame('Pharmacie', $pharmacy->getTitle());
        self::assertSame('health', $pharmacy->getCategory()?->value);
        self::assertSame(['Léo' => 1500, 'Robin' => 1050], $this->shares($pharmacy));

        // Equally between three, no cent is lost: the first one pays it.
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', ['expense[title]' => 'Café', 'expense[amount]' => '10']);
        self::assertSame(['Léa' => 334, 'Léo' => 333, 'Robin' => 333], $this->shares($this->expenses($ledger)[0]));

        // Nobody ticked: it is for no one.
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', [
            'expense[title]' => 'Rien',
            'expense[amount]' => '10',
            $this->field($me, 'included') => false,
            $this->field($robin, 'included') => false,
            $this->field($lea, 'included') => false,
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Pour qui ? Coche au moins une personne.');
    }

    public function testAnIncomeIsOwedToEveryoneAndATransferEvensUp(): void
    {
        $leo = $this->foundHousehold();
        $this->register($leo, 'Robin');
        $ledger = $this->openCommon($leo);
        $robin = $this->participant($ledger, 'Robin');
        $me = $this->participant($ledger, 'Léo');

        // The deposit given back to Léo is half Robin's.
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', ['expense[kind]' => 'income', 'expense[title]' => 'Caution rendue', 'expense[amount]' => '800']);
        self::assertResponseRedirects();
        $this->client->request('GET', '/comptes/'.$ledger->getId().'?vue=equilibres');
        self::assertSame(['Tu dois 400,00 € à Robin'], $this->settlements());

        // Its row opens the reimbursement, its amount to be changed: half of it now.
        $link = $this->client->getCrawler()->filter('#settle-'.$me->getId().'-'.$robin->getId().' a')->link();
        $this->client->click($link);
        self::assertSelectorTextContains('h1', 'Un remboursement');
        $this->client->submitForm('Noter', ['expense[amount]' => '150']);
        self::assertResponseRedirects('/comptes/'.$ledger->getId().'?vue=equilibres');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Remboursement noté : tu as remboursé '.self::euros('150,00').' à Robin.');
        self::assertSame(['Tu dois 250,00 € à Robin'], $this->settlements());

        // To oneself, it is not a transfer.
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle?type=transfert');
        $this->client->submitForm('Noter', ['expense[amount]' => '10', 'expense[recipient]' => (string) $me->getId()]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'À quelqu’un d’autre que soi-même !');
    }

    public function testAnExpensePaidInAnotherCurrencyCountsAtItsRate(): void
    {
        $leo = $this->foundHousehold();
        $this->register($leo, 'Robin');
        $ledger = $this->openCommon($leo);

        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', ['expense[title]' => 'Fondue à Genève', 'expense[amount]' => '96', 'expense[currency]' => 'CHF']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Combien vaut 1 CHF en € ?');
        $this->client->submitForm('Noter', ['expense[exchangeRate]' => '1,06']);
        self::assertResponseRedirects();

        $fondue = $this->expenses($ledger)[0];
        self::assertSame(9600, $fondue->getAmount());
        self::assertSame(10176, $fondue->getConvertedAmount());
        self::assertSame(['Léo' => 5088, 'Robin' => 5088], $this->shares($fondue));
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/'.$fondue->getId());
        self::assertSelectorTextContains('main', self::amount('96,00', 'CHF').', à 1 CHF = 1,06 €');
        self::assertSelectorTextContains('.expense-hero-amount', self::euros('101,76'));
    }

    public function testAnExpenseIsCorrectedThenTakenOff(): void
    {
        $leo = $this->foundHousehold();
        $this->register($leo, 'Robin');
        $ledger = $this->openCommon($leo);
        $robin = $this->participant($ledger, 'Robin');

        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', ['expense[title]' => 'Pizzas', 'expense[amount]' => '24']);
        $pizzas = $this->expenses($ledger)[0];

        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/'.$pizzas->getId().'/modifier');
        self::assertSelectorTextContains('h1', 'Corriger');
        $this->client->submitForm('Enregistrer', ['expense[amount]' => '36', 'expense[paidBy]' => (string) $robin->getId(), 'expense[category]' => 'leisure']);
        self::assertResponseRedirects('/comptes/'.$ledger->getId().'/depenses/'.$pizzas->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '« Pizzas » : c’est corrigé.');
        self::assertSelectorTextContains('.facts', 'Robin');
        self::assertSelectorTextContains('main', 'corrigé par Léo');
        $pizzas = $this->expenses($ledger)[0];
        self::assertSame(3600, $pizzas->getConvertedAmount());
        self::assertSame('leisure', $pizzas->getCategory()?->value);
        self::assertSame(['Léo' => 1800, 'Robin' => 1800], $this->shares($pizzas));

        $this->client->submitForm('Retirer du compte');
        self::assertResponseRedirects('/comptes/'.$ledger->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '« Pizzas » est retirée du compte.');
        self::assertSame([], $this->expenses($ledger));
        self::assertSelectorTextContains('.ledger-summary', 'Tu es quitte');
    }

    public function testAnotherAccountHasGuestsAndOnlySomeColocs(): void
    {
        $leo = $this->foundHousehold();
        $lea = $this->register($leo, 'Léa');
        $this->register($leo, 'Robin');
        $this->client->loginUser($leo);

        $this->client->request('GET', '/comptes/nouveau');
        $form = $this->client->getCrawler()->selectButton('Ouvrir le compte')->form();
        $form['ledger[name]'] = 'Week-end à Annecy';
        foreach ($form['ledger[members]'] as $box) {
            if ((string) $lea->getId() === $box->availableOptionValues()[0]) {
                $box->untick();
            }
        }
        $form['ledger[guests]'] = "Camille\n  Max ";
        $this->client->submit($form);
        $ledger = array_values(array_filter($this->ledgers($leo), static fn (Ledger $ledger): bool => 'Week-end à Annecy' === $ledger->getName()))[0];
        self::assertResponseRedirects('/comptes/'.$ledger->getId());
        self::assertSame(['Léo', 'Robin', 'Camille', 'Max'], $this->names($ledger));
        self::assertTrue($this->participant($ledger, 'Camille')->isGuest());

        // Camille paid the chalet: she stays. Max had nothing: he goes. Léa comes after all.
        $camille = $this->participant($ledger, 'Camille');
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', ['expense[title]' => 'Chalet', 'expense[amount]' => '360', 'expense[paidBy]' => (string) $camille->getId(), $this->field($this->participant($ledger, 'Max'), 'included') => false]);
        self::assertSame('rent', $this->expenses($ledger)[0]->getCategory()?->value);

        $this->client->request('GET', '/comptes/'.$ledger->getId().'/reglages');
        self::assertSelectorNotExists('form[action="/comptes/'.$ledger->getId().'/participants/'.$camille->getId().'/retirer"]');
        $this->submitAction('/comptes/'.$ledger->getId().'/participants/'.$this->participant($ledger, 'Max')->getId().'/retirer');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Max n’est plus dans le compte.');
        $this->client->submit($this->client->getCrawler()->filter('button[aria-label="Ajouter Léa au compte"]')->form());
        $this->client->followRedirect();
        $this->client->submit($this->client->getCrawler()->filter('form[action="/comptes/'.$ledger->getId().'/participants/'.$camille->getId().'"]')->form(['name' => 'Camille D.']));
        $this->client->followRedirect();
        self::assertSame(['Léo', 'Robin', 'Camille D.', 'Léa'], $this->names($ledger));

        // Its currency is fixed now that something is noted.
        self::assertSelectorExists('select#ledger_currency[disabled]');

        // Closed for good, with its lines.
        $this->client->submitForm('Fermer ce compte');
        self::assertResponseRedirects('/comptes');
        self::assertSame([], $this->ledgers($leo));
    }

    public function testTheWholeColocsAccountWelcomesNewcomers(): void
    {
        $leo = $this->foundHousehold();
        $ledger = $this->openCommon($leo);
        self::assertSame(['Léo'], $this->names($ledger));

        $this->register($this->reload($leo), 'Gab');
        $this->client->request('GET', '/comptes/'.$ledger->getId());
        self::assertSame(['Léo', 'Gab'], $this->names($ledger));
    }

    public function testTheHomePageSaysWhereIStand(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $this->client->loginUser($leo);

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#summary-ledger', 'Qui a payé quoi, et qui rembourse qui');
        // "+ Une dépense" without an account: the coloc's opens first, then the form.
        $this->client->request('GET', '/comptes/depense');
        self::assertResponseRedirects('/comptes?depense=1');
        $this->client->followRedirect();
        $this->client->submitForm('Ouvrir les comptes de la coloc');
        $ledger = $this->ledgers($leo)[0];
        self::assertResponseRedirects('/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->followRedirect();
        $this->client->submitForm('Noter', ['expense[title]' => 'Électricité', 'expense[amount]' => '84,20']);

        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#summary-ledger a[href="/comptes/'.$ledger->getId().'"]', 'On te doit '.self::euros('42,10'));
        // The tab "Comptes" leads straight to the only account.
        self::assertSelectorExists('.nav-bar a[href="/comptes/'.$ledger->getId().'"]');
        $this->client->loginUser($this->reload($robin));
        $this->client->request('GET', '/');
        self::assertSelectorTextContains('#summary-ledger a[href="/comptes/'.$ledger->getId().'"]', 'Tu dois '.self::euros('42,10'));
        $this->client->request('GET', '/comptes/depense');
        self::assertResponseRedirects('/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->request('GET', '/taches/nouvelle');
        self::assertSelectorExists('a[href="/comptes/depense"]');
    }

    public function testTheExpensesGoInASpreadsheet(): void
    {
        $leo = $this->foundHousehold();
        $this->register($leo, 'Robin');
        $ledger = $this->openCommon($leo);
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', ['expense[title]' => 'Courses', 'expense[amount]' => '12,50']);

        $this->client->request('GET', '/comptes/'.$ledger->getId().'/export');
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('depenses-communes.csv', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("\u{FEFF}", $content);
        $rows = array_map(static fn (string $line): array => str_getcsv($line, ';', '"', ''), explode("\n", trim(substr($content, 3))));
        self::assertSame(['Date', 'Titre', 'Type', 'Catégorie', 'Montant', 'Devise', 'Taux', 'Montant (EUR)', 'Payé par', 'Pour', 'Léo (EUR)', 'Robin (EUR)'], $rows[0]);
        self::assertSame([(new \DateTimeImmutable())->format('d/m/Y'), 'Courses', 'Dépense', 'Courses', '12,50', 'EUR', '', '12,50', 'Léo', 'Léo, Robin', '6,25', '-6,25'], $rows[1]);
    }

    public function testColocsAreToldOfTheExpensesNotedForThem(): void
    {
        $leo = $this->foundHousehold();
        $robin = $this->register($leo, 'Robin');
        $ledger = $this->openCommon($leo);

        $this->client->loginUser($this->reload($robin));
        $this->client->request('GET', '/comptes/'.$ledger->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', ['expense[title]' => 'Courses', 'expense[amount]' => '30']);
        $expense = $this->expenses($ledger)[0];

        $this->client->loginUser($this->reload($leo));
        $this->client->request('GET', '/api/notifications');
        $notices = array_column(json_decode((string) $this->client->getResponse()->getContent(), true), null, 'key');
        self::assertSame('Une dépense : Courses', $notices['depense-'.$expense->getId()]['title']);
        self::assertSame('Robin a payé '.self::euros('30,00').' · ta part '.self::euros('15,00').'.', $notices['depense-'.$expense->getId()]['body']);
        self::assertSame('/comptes/'.$ledger->getId().'/depenses/'.$expense->getId(), $notices['depense-'.$expense->getId()]['path']);

        // Not to the one who noted it, nor to whoever said no in their reminders.
        $this->client->loginUser($this->reload($robin));
        $this->client->request('GET', '/api/notifications');
        self::assertStringNotContainsString('depense-', (string) $this->client->getResponse()->getContent());
        $this->reload($leo)->setNotifyExpenses(false);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->client->loginUser($this->reload($leo));
        $this->client->request('GET', '/api/notifications');
        self::assertStringNotContainsString('depense-', (string) $this->client->getResponse()->getContent());
    }

    public function testTheAccountsOfAnotherHouseholdAreOutOfReach(): void
    {
        $zoe = $this->foundHousehold('Zoé', household: 'Une autre coloc');
        $theirs = $this->openCommon($zoe);
        $this->client->request('GET', '/comptes/'.$theirs->getId().'/depenses/nouvelle');
        $this->client->submitForm('Noter', ['expense[title]' => 'Loyer', 'expense[amount]' => '500']);
        $expense = $this->expenses($theirs)[0];

        $leo = $this->foundHousehold();
        $mine = $this->openCommon($leo);
        foreach (['/comptes/'.$theirs->getId(), '/comptes/'.$theirs->getId().'/reglages', '/comptes/'.$theirs->getId().'/export', '/comptes/'.$theirs->getId().'/depenses/'.$expense->getId()] as $page) {
            $this->client->request('GET', $page);
            self::assertResponseStatusCodeSame(403, $page);
        }
        $this->client->request('POST', '/comptes/'.$theirs->getId().'/regler', ['_csrf_token' => 'csrf-token']);
        self::assertResponseStatusCodeSame(403);
        // Their expense under my account's address is nowhere.
        $this->client->request('GET', '/comptes/'.$mine->getId().'/depenses/'.$expense->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/comptes');
        self::assertSelectorNotExists('#ledger-'.$theirs->getId());
    }

    /** The coloc's account, opened by this member, who is logged in. */
    private function openCommon(Member $member): Ledger
    {
        $this->client->loginUser($this->reload($member));
        $this->client->request('GET', '/comptes');
        $this->client->submitForm('Ouvrir les comptes de la coloc');

        return $this->ledgers($member)[0];
    }

    /** @return list<Ledger> */
    private function ledgers(Member $member): array
    {
        return static::getContainer()->get(LedgerRepository::class)->findForHousehold($this->reload($member)->getHousehold());
    }

    /** @return list<string> */
    private function names(Ledger $ledger): array
    {
        $ledger = static::getContainer()->get(LedgerRepository::class)->find($ledger->getId()) ?? throw new \LogicException('Ledger not found.');

        return array_values(array_map(static fn (Participant $participant): string => $participant->getName(), $ledger->getParticipants()->toArray()));
    }

    private function participant(Ledger $ledger, string $name): Participant
    {
        $ledger = static::getContainer()->get(LedgerRepository::class)->find($ledger->getId()) ?? throw new \LogicException('Ledger not found.');

        return $ledger->getParticipants()->findFirst(static fn (int $key, Participant $participant): bool => $participant->getName() === $name) ?? throw new \LogicException($name.' not found.');
    }

    /** @return list<Expense> the latest first */
    private function expenses(Ledger $ledger): array
    {
        $ledger = static::getContainer()->get(LedgerRepository::class)->find($ledger->getId()) ?? throw new \LogicException('Ledger not found.');

        return static::getContainer()->get(ExpenseRepository::class)->findForLedger($ledger);
    }

    /** @return array<string, int> name => cents, in the account's currency */
    private function shares(Expense $expense): array
    {
        self::assertNotSame(ExpenseKind::Transfer, $expense->getKind());
        $shares = [];
        foreach ($expense->getShares() as $share) {
            $shares[$share->getParticipant()->getName()] = $share->getConvertedAmount();
        }

        return $shares;
    }

    /** @param 'included'|'weight'|'amount' $what */
    private function field(Participant $participant, string $what): string
    {
        return \sprintf('expense[split][%d][%s]', $participant->getId(), $what);
    }

    /** @return list<string> the reimbursements suggested, as they read */
    private function settlements(): array
    {
        return $this->client->getCrawler()->filter('.settle-row .row-title')->each(static fn ($title): string => str_replace(["\u{A0}", "\u{202F}"], ' ', trim($title->text())));
    }

    /** "20,00 €", as it is written: a non-breaking space before the symbol. */
    private static function euros(string $amount): string
    {
        return self::amount($amount, '€');
    }

    private static function amount(string $amount, string $symbol): string
    {
        return $amount."\u{A0}".$symbol;
    }
}
