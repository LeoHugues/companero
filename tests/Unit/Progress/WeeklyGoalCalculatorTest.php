<?php

namespace App\Tests\Unit\Progress;

use App\Calendar\Week;
use App\Entity\Absence;
use App\Progress\WeeklyGoalCalculator;
use App\Tests\Unit\TaskFactory;
use PHPUnit\Framework\TestCase;

final class WeeklyGoalCalculatorTest extends TestCase
{
    use TaskFactory;

    public function testGoalIsProratedByTheDaysAway(): void
    {
        $member = $this->member();
        $member->setWeeklyGoal(20);
        $week = Week::containing(new \DateTimeImmutable('2026-10-07'));

        // Away from Friday to the next Tuesday: Friday, Saturday and Sunday fall in this week.
        $absence = new Absence($member);
        $absence->setStartsOn(new \DateTimeImmutable('2026-10-09'));
        $absence->setEndsOn(new \DateTimeImmutable('2026-10-13'));

        $calculator = new WeeklyGoalCalculator();
        $days = $calculator->absentDays($week, [$absence]);

        self::assertSame(3, $days);
        self::assertSame(11, $calculator->goalFor($member, $days));
    }

    public function testNoAbsenceKeepsTheFullGoal(): void
    {
        $member = $this->member();
        $calculator = new WeeklyGoalCalculator();

        self::assertSame(20, $calculator->goalFor($member, $calculator->absentDays(Week::containing(new \DateTimeImmutable()), [])));
    }
}
