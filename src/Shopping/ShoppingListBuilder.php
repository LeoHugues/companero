<?php

namespace App\Shopping;

use App\Entity\Household;
use App\Entity\Pet;
use App\Entity\ShoppingItem;
use App\Enum\PetSpecies;
use App\Repository\ShoppingItemRepository;
use Psr\Clock\ClockInterface;

final readonly class ShoppingListBuilder
{
    /** Bought in the last days: shown under the list, to put back on it if it was a slip. */
    public const RECENT_DAYS = 7;
    private const RECENT_SHOWN = 6;
    private const SUGGESTIONS = 8;

    /** What every house runs out of, suggested until the household has habits of its own. */
    public const STAPLES = ['Papier toilette', 'Éponges', 'Liquide vaisselle', 'Sacs poubelle', 'Lessive', 'Vinaigre blanc', 'Essuie-tout', 'Savon'];

    public function __construct(
        private ShoppingItemRepository $items,
        private ClockInterface $clock,
    ) {
    }

    public function build(Household $household): ShoppingList
    {
        $missing = $this->items->findMissing($household);
        $since = $this->clock->now()->modify(\sprintf('-%d days', self::RECENT_DAYS));

        $bought = array_filter($this->items->findForHousehold($household), static fn (ShoppingItem $item): bool => !$item->isMissing() && $item->wasEverBought());
        usort($bought, static fn (ShoppingItem $a, ShoppingItem $b): int => $b->getBoughtAt() <=> $a->getBoughtAt());
        $recent = array_values(array_filter($bought, static fn (ShoppingItem $item): bool => $item->getBoughtAt() >= $since));
        $before = array_values(array_filter($bought, static fn (ShoppingItem $item): bool => $item->getBoughtAt() < $since));

        // What was bought before, the latest first, then the staples (the pets' too) that are nowhere yet on the page.
        $shown = array_map(static fn (ShoppingItem $item): string => ShoppingItem::keyOf($item->getName()), [...$missing, ...$recent]);
        $suggestions = [];
        foreach ([...array_map(static fn (ShoppingItem $item): string => $item->getName(), $before), ...self::STAPLES, ...$this->petStaples($household)] as $name) {
            $key = ShoppingItem::keyOf($name);
            if (!\in_array($key, $shown, true)) {
                $suggestions[] = $name;
                $shown[] = $key;
            }
        }

        return new ShoppingList($missing, \array_slice($recent, 0, self::RECENT_SHOWN), \array_slice($suggestions, 0, self::SUGGESTIONS));
    }

    /** @return list<string> */
    private function petStaples(Household $household): array
    {
        $species = array_map(static fn (Pet $pet): PetSpecies => $pet->getSpecies(), $household->getPets()->toArray());

        return [
            ...(\in_array(PetSpecies::Cat, $species, true) || \in_array(PetSpecies::Dog, $species, true) ? ['Croquettes'] : []),
            ...(\in_array(PetSpecies::Cat, $species, true) ? ['Litière'] : []),
        ];
    }
}
