<?php

namespace App\Progress;

use App\Entity\Member;

/** The household's own weekly goal, everyone's points together — reaching it earns a bonus for all. */
final readonly class TeamProgress
{
    /** @param list<MemberProgress> $members */
    public function __construct(
        public array $members,
        private int $goal,
    ) {
    }

    public function points(): int
    {
        return array_sum(array_map(static fn (MemberProgress $p): int => max(0, $p->points), $this->members));
    }

    public function goal(): int
    {
        return $this->goal;
    }

    public function reached(): bool
    {
        return $this->goal() > 0 && $this->points() >= $this->goal();
    }

    /** Width of a member's segment in the shared progress bar, in percent of the goal. */
    public function shareOf(MemberProgress $progress): float
    {
        $scale = max($this->goal(), $this->points());

        return $scale > 0 ? round(100 * max(0, $progress->points) / $scale, 1) : 0.0;
    }

    public function of(Member $member): ?MemberProgress
    {
        foreach ($this->members as $progress) {
            if ($progress->member === $member) {
                return $progress;
            }
        }

        return null;
    }
}
