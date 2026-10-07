<?php

namespace App\Tests\Unit\Calendar;

use App\Calendar\Week;
use PHPUnit\Framework\TestCase;

final class WeekTest extends TestCase
{
    public function testWeekRunsFromMondayToSunday(): void
    {
        $week = Week::containing(new \DateTimeImmutable('2026-10-10 15:00'));

        self::assertEquals(new \DateTimeImmutable('2026-10-05 00:00'), $week->start);
        self::assertEquals(new \DateTimeImmutable('2026-10-12 00:00'), $week->end());
        self::assertSame(41, $week->number());
    }

    public function testSundayEveningBelongsToTheSameWeek(): void
    {
        $week = Week::containing(new \DateTimeImmutable('2026-10-05'));

        self::assertTrue($week->contains(new \DateTimeImmutable('2026-10-11 23:59')));
        self::assertFalse($week->contains(new \DateTimeImmutable('2026-10-12 00:00')));
    }

    public function testNavigatesBetweenWeeks(): void
    {
        $week = Week::containing(new \DateTimeImmutable('2026-10-07'));

        self::assertEquals(new \DateTimeImmutable('2026-09-28'), $week->previous()->start);
        self::assertEquals(new \DateTimeImmutable('2026-10-12'), $week->next()->start);
        self::assertEquals(new \DateTimeImmutable('2026-10-10'), $week->day(6));
        self::assertCount(7, $week->days());
    }
}
