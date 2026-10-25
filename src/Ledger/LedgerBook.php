<?php

namespace App\Ledger;

use App\Entity\Household;
use App\Entity\Ledger;
use App\Entity\Member;
use App\Repository\ExpenseRepository;
use App\Repository\LedgerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** The household's accounts: opened, looked up, balanced, closed. */
final readonly class LedgerBook
{
    public const COMMON_NAME = 'Dépenses communes';

    public function __construct(
        private LedgerRepository $ledgers,
        private ExpenseRepository $expenses,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The household's accounts, the whole coloc's first. Those of the whole coloc welcome the colocs who joined
     * since they were opened.
     *
     * @return list<Ledger>
     */
    public function forHousehold(Household $household): array
    {
        $ledgers = $this->ledgers->findForHousehold($household);
        $welcomed = false;
        foreach ($ledgers as $ledger) {
            $welcomed = $this->welcome($ledger, false) || $welcomed;
        }
        if ($welcomed) {
            $this->entityManager->flush();
        }

        return $ledgers;
    }

    /** @return list<Ledger> the accounts a member is in */
    public function forMember(Member $member): array
    {
        return array_values(array_filter($this->forHousehold($member->getHousehold()), static fn (Ledger $ledger): bool => null !== $ledger->participantFor($member)));
    }

    /** The whole coloc's account, if needed with the colocs who joined since. */
    public function welcome(Ledger $ledger, bool $flush = true): bool
    {
        if (!$ledger->isEveryone() || !$ledger->welcome($ledger->getHousehold()->getMembers())) {
            return false;
        }
        if ($flush) {
            $this->entityManager->flush();
        }

        return true;
    }

    /** "Dépenses communes", with the whole coloc, in euros. */
    public function openCommon(Household $household): Ledger
    {
        $ledger = new Ledger($household, self::COMMON_NAME, $this->clock->now());
        $ledger->setEveryone(true);
        $ledger->welcome($household->getMembers());
        $this->entityManager->persist($ledger);
        $this->entityManager->flush();

        return $ledger;
    }

    public function sheet(Ledger $ledger): BalanceSheet
    {
        return new BalanceSheet($ledger, $this->expenses->balances([$ledger]));
    }

    /**
     * Where a member stands in each of their accounts.
     *
     * @param list<Ledger> $ledgers the accounts they are in (forMember())
     *
     * @return array<int, int> ledger id => cents, owed to them when positive
     */
    public function standing(Member $member, array $ledgers): array
    {
        $balances = $this->expenses->balances($ledgers);
        $standing = [];
        foreach ($ledgers as $ledger) {
            $standing[(int) $ledger->getId()] = $balances[(int) $ledger->participantFor($member)?->getId()] ?? 0;
        }

        return $standing;
    }

    /** An account closed for good, with all its lines. */
    public function delete(Ledger $ledger): void
    {
        foreach ($this->expenses->findBy(['ledger' => $ledger]) as $expense) {
            $this->entityManager->remove($expense);
        }
        $this->entityManager->remove($ledger);
        $this->entityManager->flush();
    }
}
