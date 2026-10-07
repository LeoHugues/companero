<?php

namespace App\Progress;

use App\Calendar\Week;
use App\Entity\Absence;
use App\Entity\Member;

/** A member's weekly goal shrinks with the days they are away. */
final class WeeklyGoalCalculator
{
    /** @param iterable<Absence> $absences absences of this member */
    public function absentDays(Week $week, iterable $absences): int
    {
        $days = 0;
        foreach ($absences as $absence) {
            $days += $absence->daysWithin($week->start, $week->end());
        }

        return min(7, $days);
    }

    public function goalFor(Member $member, int $absentDays): int
    {
        return (int) round($member->getWeeklyGoal() * (7 - $absentDays) / 7);
    }
}
