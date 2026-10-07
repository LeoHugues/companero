<?php

namespace App\Progress;

use App\Entity\Member;
use App\Repository\PointEntryRepository;

final readonly class LevelProvider
{
    public function __construct(
        private PointEntryRepository $points,
        private LevelCalculator $calculator,
    ) {
    }

    public function levelOf(Member $member): Level
    {
        return $this->calculator->forXp($this->points->totalFor($member));
    }
}
