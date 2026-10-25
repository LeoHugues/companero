<?php

namespace App\Ledger;

use App\Entity\Participant;

/** "Robin doit 23,40 € à Léo": one reimbursement for everyone to be even. */
final readonly class Settlement
{
    public function __construct(
        public Participant $from,
        public Participant $to,
        /** In cents, in the account's currency. */
        public int $amount,
    ) {
    }
}
