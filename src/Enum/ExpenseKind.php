<?php

namespace App\Enum;

/**
 * What a line of an account is: money spent for several people, money received for several people, or money
 * handed from one to another (a reimbursement).
 */
enum ExpenseKind: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Expense => 'Dépense',
            self::Income => 'Revenu',
            self::Transfer => 'Transfert',
        };
    }

    /**
     * Who paid (or handed the money) is owed what they gave, the others owe their share: +1. Who received money
     * for everyone owes it to them: -1.
     */
    public function sign(): int
    {
        return self::Income === $this ? -1 : 1;
    }

    /** Spent or received for several people: it has a split, and counts in the totals. A transfer only settles. */
    public function isShared(): bool
    {
        return self::Transfer !== $this;
    }
}
