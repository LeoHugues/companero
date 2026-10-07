<?php

namespace App\Task;

use App\Entity\Completion;

final readonly class CompletionResult
{
    public function __construct(
        public Completion $completion,
        public int $basePoints,
        public ?Bonus $bonus,
    ) {
    }

    public function totalPoints(): int
    {
        return $this->basePoints + ($this->bonus->points ?? 0);
    }
}
