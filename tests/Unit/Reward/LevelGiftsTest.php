<?php

namespace App\Tests\Unit\Reward;

use App\Enum\BoostKind;
use App\Enum\GiftKind;
use App\Reward\ActiveBoost;
use App\Reward\LevelGifts;
use PHPUnit\Framework\TestCase;

final class LevelGiftsTest extends TestCase
{
    public function testEachLevelBringsAGiftInTurn(): void
    {
        $gifts = new LevelGifts();

        self::assertSame([], $gifts->forLevel(1, false));
        self::assertSame([GiftKind::TeamBoost], $gifts->forLevel(2, false));
        self::assertSame([GiftKind::StreakFreeze], $gifts->forLevel(3, false));
        self::assertSame([GiftKind::FriendBoost], $gifts->forLevel(4, false));
        self::assertSame([GiftKind::XpBoost], $gifts->forLevel(5, false));
        self::assertSame([GiftKind::TeamBoost], $gifts->forLevel(6, false));
    }

    public function testThePetsGetATreatAtEveryLevel(): void
    {
        self::assertSame([GiftKind::TeamBoost, GiftKind::Treat], (new LevelGifts())->forLevel(2, true));
    }

    public function testABoostGivesOnePointEveryThreePoints(): void
    {
        $boost = new ActiveBoost(BoostKind::Points, 'Jour de ménage', new \DateTimeImmutable());

        self::assertSame(0, $boost->bonusFor(2));
        self::assertSame(3, $boost->bonusFor(10));
        self::assertSame(10, $boost->bonusFor(30));
    }
}
