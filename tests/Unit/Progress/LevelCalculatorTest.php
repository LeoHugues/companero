<?php

namespace App\Tests\Unit\Progress;

use App\Progress\LevelCalculator;
use PHPUnit\Framework\TestCase;

final class LevelCalculatorTest extends TestCase
{
    public function testEveryoneStartsAtLevelOne(): void
    {
        $level = (new LevelCalculator())->forXp(0);

        self::assertSame(1, $level->number);
        self::assertSame('Nouvelle recrue', $level->rank);
        self::assertSame(20, $level->xpToNext());
    }

    public function testLevelsAskABitMoreEachTime(): void
    {
        $calculator = new LevelCalculator();

        self::assertSame(2, $calculator->forXp(20)->number);
        self::assertSame(2, $calculator->forXp(59)->number);
        self::assertSame(3, $calculator->forXp(60)->number);
        self::assertSame(8, $calculator->forXp(560)->number);
        self::assertSame('Chevalier de la serpillière', $calculator->forXp(560)->rank);
    }

    public function testProgressWithinALevel(): void
    {
        $level = (new LevelCalculator())->forXp(500);

        self::assertSame(7, $level->number);
        self::assertSame(57, $level->progress());
        self::assertSame(60, $level->xpToNext());
        self::assertSame('Chevalier de la serpillière', $level->nextRank);
    }

    public function testRankStaysLegendaryBeyondTheLastOne(): void
    {
        self::assertSame('Légende de la coloc', (new LevelCalculator())->forXp(5000)->rank);
    }
}
