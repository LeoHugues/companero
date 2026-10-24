<?php

namespace App\Tests\Unit\Shopping;

use App\Entity\ShoppingItem;
use App\Shopping\ItemArt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ItemArtTest extends TestCase
{
    /** @return iterable<array{string, string}> */
    public static function names(): iterable
    {
        yield ['Papier toilette', 'roll'];
        yield ['PQ', 'roll'];
        yield ['Essuie-tout', 'roll'];
        yield ['Éponges', 'sponge'];
        yield ['Liquide vaisselle', 'bottle'];
        yield ['Vinaigre blanc', 'bottle'];
        yield ['Sel régénérant', 'bottle'];
        yield ['Sacs poubelle', 'bag'];
        yield ['Ampoule E27 (salle de bain)', 'bulb'];
        yield ['Piles AA', 'battery'];
        yield ['Vis pour l’étagère', 'wrench'];
        yield ['Café', 'cup'];
        yield ['Thé vert', 'cup'];
        yield ['Pâtes', 'basket'];
        yield ['Œufs', 'basket'];
        yield ['Sel', 'basket'];
        yield ['Croquettes', 'paw'];
        yield ['Litière', 'paw'];
        yield ['Un cadeau pour le proprio', 'cart'];
    }

    #[DataProvider('names')]
    public function testTheDrawingIsGuessedFromTheName(string $name, string $icon): void
    {
        self::assertSame($icon, ItemArt::of($name)['icon']);
    }

    public function testNamesAreToldApartWithoutCapitalsAccentsNorPlurals(): void
    {
        self::assertSame('Papier toilette', ShoppingItem::tidy("  papier \t toilette "));
        self::assertSame(ShoppingItem::keyOf('Éponges'), ShoppingItem::keyOf('eponge'));
        self::assertSame(ShoppingItem::keyOf('Œufs'), ShoppingItem::keyOf('oeuf'));
        self::assertSame(ShoppingItem::keyOf('Papiers toilettes'), ShoppingItem::keyOf('Papier toilette'));
        self::assertNotSame(ShoppingItem::keyOf('Vis'), ShoppingItem::keyOf('Vi'));
    }
}
