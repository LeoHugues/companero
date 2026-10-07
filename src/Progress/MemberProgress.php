<?php

namespace App\Progress;

use App\Entity\Member;

final readonly class MemberProgress
{
    public function __construct(
        public Member $member,
        public int $points,
        public int $goal,
        public int $absentDays,
    ) {
    }

    public function reached(): bool
    {
        return $this->points >= $this->goal;
    }

    public function percent(): int
    {
        return $this->goal > 0 ? min(100, (int) round(100 * $this->points / $this->goal)) : 100;
    }

    public function wasPresent(): bool
    {
        return $this->absentDays < 7;
    }
}
