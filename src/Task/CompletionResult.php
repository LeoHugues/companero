<?php

namespace App\Task;

use App\Entity\Completion;
use App\Entity\Gift;

final readonly class CompletionResult
{
    /** @param list<Gift> $gifts earned by reaching a new level */
    public function __construct(
        public Completion $completion,
        public int $basePoints,
        public int $boostPoints = 0,
        public int $boostXp = 0,
        public array $gifts = [],
    ) {
    }

    /** What counts towards the weekly goal. */
    public function totalPoints(): int
    {
        return $this->basePoints + $this->boostPoints;
    }
}
