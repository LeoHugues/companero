<?php

namespace App\Ledger;

use App\Entity\Expense;
use App\Entity\Member;
use App\Enum\ExpenseKind;
use App\Enum\SplitMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Writes an expense down as its form says: converted into the account's currency when it was paid in another, split
 * to the cent, its category guessed from its title when none was chosen.
 */
final readonly class ExpenseRecorder
{
    public const TRANSFER_TITLE = 'Remboursement';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /** A new line of the account, or the same one corrected. The draft must be valid. */
    public function record(ExpenseDraft $draft, Member $by, ?Expense $expense = null): Expense
    {
        $paidBy = $draft->paidBy ?? throw new \LogicException('An expense is paid by someone.');
        $amount = $draft->amount ?? throw new \LogicException('An expense has an amount.');
        $now = $this->clock->now();
        if (null === $expense) {
            $expense = new Expense($draft->ledger, $draft->kind, $paidBy, $by, $now);
        } else {
            $expense->setKind($draft->kind);
            $expense->setPaidBy($paidBy);
            $expense->touch($by, $now);
        }

        $transfer = ExpenseKind::Transfer === $draft->kind;
        $title = trim((string) $draft->title);
        $expense->setTitle('' === $title && $transfer ? self::TRANSFER_TITLE : $title);
        $expense->setSpentOn($draft->spentOn ?? $now);
        $expense->setCategory($transfer ? null : $draft->category ?? ExpenseArt::guess($expense->getTitle()));

        $foreign = $draft->isForeign();
        $converted = $foreign ? (int) round($amount * (float) $draft->exchangeRate) : $amount;
        $expense->setAmount($amount, $draft->currency, $foreign ? $draft->exchangeRate : null, $converted);

        $parts = $transfer
            ? [(int) $draft->recipient?->getId() => ['weight' => 1, 'amount' => $amount]]
            : $draft->shares() ?? throw new \LogicException('The split of an expense adds up.');
        // Converted share by share, the cents left over going where they weigh the most: the shares add up again.
        $convertedParts = $foreign ? Allocation::split($converted, array_map(static fn (array $part): int => $part['amount'], $parts)) : null;
        $participants = $draft->ledger->participantsById();
        $shares = [];
        foreach ($parts as $id => $part) {
            $shares[] = ['participant' => $participants[$id], 'weight' => $part['weight'], 'amount' => $part['amount'], 'converted' => $convertedParts[$id] ?? $part['amount']];
        }
        $expense->split($transfer ? SplitMode::Equal : $draft->splitMode, $shares);

        $this->entityManager->persist($expense);
        $this->entityManager->flush();

        return $expense;
    }

    /** "Régler": the reimbursement the balances suggest, handed over today. */
    public function settle(Settlement $settlement, Member $by): Expense
    {
        $draft = ExpenseDraft::transfer($settlement->from->getLedger(), $settlement->from, $settlement->to, $settlement->amount, $this->clock->now());

        return $this->record($draft, $by);
    }

    public function delete(Expense $expense): void
    {
        $this->entityManager->remove($expense);
        $this->entityManager->flush();
    }
}
