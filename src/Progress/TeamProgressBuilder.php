<?php

namespace App\Progress;

use App\Calendar\Week;
use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Presence;
use App\Repository\PointEntryRepository;
use App\Repository\PresenceRepository;

final readonly class TeamProgressBuilder
{
    public function __construct(
        private PointEntryRepository $points,
        private PresenceRepository $presences,
        private WeeklyGoalCalculator $goals,
    ) {
    }

    public function build(Household $household, Week $week): TeamProgress
    {
        $points = $this->points->sumByMember($household, $week->start, $week->end());
        $presence = $this->presences->daysByMember($household, $week);

        return new TeamProgress(array_values(array_map(
            function (Member $member) use ($points, $presence): MemberProgress {
                $days = $presence[$member->getId()] ?? Presence::FULL_WEEK;

                return new MemberProgress($member, $points[$member->getId()] ?? 0, $this->goals->goalFor($member, $days), $days);
            },
            $household->getMembers()->toArray(),
        )));
    }
}
