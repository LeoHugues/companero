<?php

namespace App\Ledger;

/** One line of "Pour qui" in the expense form: in it or not, their parts, their amount. */
final class ShareInput
{
    public function __construct(
        public bool $included = true,
        public ?int $weight = 1,
        /** In cents of the expense's currency, split by amounts. */
        public ?int $amount = null,
    ) {
    }
}
