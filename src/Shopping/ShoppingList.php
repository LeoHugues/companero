<?php

namespace App\Shopping;

use App\Entity\ShoppingItem;

/** What the house is missing, what was just bought, and what to add again in one tap. */
final readonly class ShoppingList
{
    /**
     * @param list<ShoppingItem> $missing     the urgent ones first, then the latest noticed
     * @param list<ShoppingItem> $bought      lately, the latest first
     * @param list<string>       $suggestions names: what was bought before, then what every house runs out of
     */
    public function __construct(
        public array $missing,
        public array $bought,
        public array $suggestions,
    ) {
    }

    /** @return list<ShoppingItem> */
    public function urgent(): array
    {
        return array_values(array_filter($this->missing, static fn (ShoppingItem $item): bool => $item->isUrgent()));
    }

    /** @return list<ShoppingItem> */
    public function notUrgent(): array
    {
        return array_values(array_filter($this->missing, static fn (ShoppingItem $item): bool => !$item->isUrgent()));
    }
}
