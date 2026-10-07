<?php

namespace App\Progress;

use App\Calendar\Week;
use App\Entity\Absence;
use App\Entity\Household;
use App\Entity\Member;
use App\Repository\AbsenceRepository;
use App\Repository\PointEntryRepository;

final readonly class TeamProgressBuilder
{
    public function __construct(
        private PointEntryRepository $points,
        private AbsenceRepository $absences,
        private WeeklyGoalCalculator $goals,
    ) {
    }

    public function build(Household $household, Week $week): TeamProgress
    {
        $points = $this->points->sumByMember($household, $week->start, $week->end());
        $absences = $this->absences->findOverlapping($household, $week->start, $week->end());

        return new TeamProgress(array_values(array_map(
            function (Member $member) use ($week, $points, $absences): MemberProgress {
                $absentDays = $this->goals->absentDays($week, array_filter($absences, static fn (Absence $a): bool => $a->getMember() === $member));

                return new MemberProgress($member, $points[$member->getId()] ?? 0, $this->goals->goalFor($member, $absentDays), $absentDays);
            },
            $household->getMembers()->toArray(),
        )));
    }
}
