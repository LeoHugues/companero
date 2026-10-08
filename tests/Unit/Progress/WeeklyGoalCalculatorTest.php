<?php

namespace App\Tests\Unit\Progress;

use App\Progress\WeeklyGoalCalculator;
use App\Tests\Unit\TaskFactory;
use PHPUnit\Framework\TestCase;

final class WeeklyGoalCalculatorTest extends TestCase
{
    use TaskFactory;

    public function testGoalFollowsTheDaysOfPresence(): void
    {
        $member = $this->member();
        $member->setWeeklyGoal(200);
        $calculator = new WeeklyGoalCalculator();

        self::assertSame(200, $calculator->goalFor($member, 7));
        self::assertSame(114, $calculator->goalFor($member, 4));
        self::assertSame(0, $calculator->goalFor($member, 0));
    }

    public function testDaysAreKeptWithinTheWeek(): void
    {
        $member = $this->member();
        $calculator = new WeeklyGoalCalculator();

        self::assertSame(70, $calculator->goalFor($member, 12));
        self::assertSame(0, $calculator->goalFor($member, -1));
    }
}
