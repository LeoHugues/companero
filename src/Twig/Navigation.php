<?php

namespace App\Twig;

use App\Entity\Member;
use App\Ledger\LedgerBook;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/** Where the tabs of the bottom bar lead. */
final readonly class Navigation
{
    public function __construct(
        private Security $security,
        private LedgerBook $ledgers,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /** "Comptes": straight to the account when I am in only one, the accounts otherwise. */
    #[AsTwigFunction('ledger_tab_path')]
    public function ledgerTab(): string
    {
        $member = $this->security->getUser();
        $mine = $member instanceof Member ? $this->ledgers->forMember($member) : [];

        return 1 === \count($mine)
            ? $this->urls->generate('ledger_show', ['id' => $mine[0]->getId()])
            : $this->urls->generate('ledger_index');
    }
}
