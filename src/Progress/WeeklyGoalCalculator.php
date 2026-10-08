<?php

namespace App\Progress;

use App\Entity\Member;
use App\Entity\Presence;

/** A member's weekly goal follows the days they are around: 4 days out of 7, 4/7 of the goal. */
final class WeeklyGoalCalculator
{
    public function goalFor(Member $member, int $presentDays): int
    {
        return (int) round($member->getWeeklyGoal() * max(0, min(Presence::FULL_WEEK, $presentDays)) / Presence::FULL_WEEK);
    }
}
