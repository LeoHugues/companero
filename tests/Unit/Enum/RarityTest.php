<?php

namespace App\Tests\Unit\Enum;

use App\Enum\Rarity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RarityTest extends TestCase
{
    /** @return iterable<array{int, Rarity}> */
    public static function points(): iterable
    {
        yield [5, Rarity::Common];
        yield [15, Rarity::Common];
        yield [20, Rarity::Rare];
        yield [25, Rarity::Rare];
        yield [30, Rarity::Epic];
        yield [45, Rarity::Epic];
        yield [50, Rarity::Legendary];
        yield [200, Rarity::Legendary];
    }

    #[DataProvider('points')]
    public function testTheMoreATaskIsWorthTheRarerItsCard(int $points, Rarity $expected): void
    {
        self::assertSame($expected, Rarity::fromPoints($points));
    }
}
