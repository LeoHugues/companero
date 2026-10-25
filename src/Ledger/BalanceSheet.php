<?php

namespace App\Ledger;

use App\Entity\Ledger;
use App\Entity\Participant;

/**
 * Where everyone stands in an account: what they are owed (positive) or owe (negative), and the fewest
 * reimbursements that make everyone even.
 */
final readonly class BalanceSheet
{
    /** @param array<int, int> $balances participant id => cents, owed to them when positive */
    public function __construct(
        public Ledger $ledger,
        private array $balances,
    ) {
    }

    public function of(Participant $participant): int
    {
        return $this->balances[$participant->getId()] ?? 0;
    }

    public function isEven(): bool
    {
        return [] === array_filter($this->balances);
    }

    /** The largest balance, owed or owing: the full length of the bars. */
    public function largest(): int
    {
        return max([0, ...array_map(abs(...), $this->balances)]);
    }

    /**
     * Who reimburses whom: the one who owes the most pays the one who is owed the most, and so on. At most one
     * reimbursement fewer than there are people, each between two of them only.
     *
     * @return list<Settlement> the largest first
     */
    public function settlements(): array
    {
        $participants = [];
        $creditors = [];
        $debtors = [];
        foreach ($this->ledger->getParticipants() as $participant) {
            $id = (int) $participant->getId();
            $participants[$id] = $participant;
            $balance = $this->balances[$id] ?? 0;
            if ($balance > 0) {
                $creditors[$id] = $balance;
            } elseif ($balance < 0) {
                $debtors[$id] = -$balance;
            }
        }

        $settlements = [];
        while ([] !== $creditors && [] !== $debtors) {
            // Stable sorts: between equal amounts, the participants' order.
            uasort($creditors, static fn (int $a, int $b): int => $b <=> $a);
            uasort($debtors, static fn (int $a, int $b): int => $b <=> $a);
            $to = array_key_first($creditors);
            $from = array_key_first($debtors);
            $amount = min($creditors[$to], $debtors[$from]);
            $settlements[] = new Settlement($participants[$from], $participants[$to], $amount);
            $creditors[$to] -= $amount;
            $debtors[$from] -= $amount;
            if (0 === $creditors[$to]) {
                unset($creditors[$to]);
            }
            if (0 === $debtors[$from]) {
                unset($debtors[$from]);
            }
        }

        return $settlements;
    }

    /** The reimbursement suggested from one to the other, if there is one. */
    public function settlementBetween(Participant $from, Participant $to): ?Settlement
    {
        foreach ($this->settlements() as $settlement) {
            if ($settlement->from === $from && $settlement->to === $to) {
                return $settlement;
            }
        }

        return null;
    }
}
