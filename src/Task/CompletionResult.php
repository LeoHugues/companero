<?php

namespace App\Task;

use App\Entity\Bounty;
use App\Entity\Completion;
use App\Entity\Gift;
use App\Enum\BountyKind;

final readonly class CompletionResult
{
    /** @param list<Gift> $gifts earned by reaching a new level */
    public function __construct(
        public Completion $completion,
        public int $basePoints,
        public int $boostPoints = 0,
        public int $boostXp = 0,
        public array $gifts = [],
        /** The surprise found in the card, if any. */
        public ?Bounty $bounty = null,
    ) {
    }

    /** What counts towards the weekly goal. */
    public function totalPoints(): int
    {
        return $this->basePoints + $this->boostPoints + $this->bountyPoints();
    }

    public function bountyPoints(): int
    {
        return BountyKind::Points === $this->bounty?->getKind() ? $this->bounty->getAmount() : 0;
    }

    /** XP that does not count for the weekly goal: an XP boost, a surprise of XP. */
    public function extraXp(): int
    {
        return $this->boostXp + (BountyKind::Xp === $this->bounty?->getKind() ? $this->bounty->getAmount() : 0);
    }
}
