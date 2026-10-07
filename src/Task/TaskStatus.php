<?php

namespace App\Task;

use App\Enum\Urgency;

/** Where a task stands right now: how pressing it is and how "fresh" it still feels. */
final readonly class TaskStatus
{
    public int $freshness;

    public function __construct(
        public Urgency $urgency,
        int $freshness,
        public ?\DateTimeImmutable $dueAt = null,
        public int $overdueDays = 0,
        public ?int $doneThisWeek = null,
        public ?int $weeklyCommitment = null,
    ) {
        $this->freshness = max(0, min($freshness, $urgency->maxFreshness()));
    }

    public function hasPendingCommitment(): bool
    {
        return null !== $this->weeklyCommitment && $this->doneThisWeek < $this->weeklyCommitment;
    }
}
