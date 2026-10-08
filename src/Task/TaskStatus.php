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
        /** When its card turns orange, and red. */
        public ?\DateTimeImmutable $warningAt = null,
        public ?\DateTimeImmutable $lateAt = null,
    ) {
        $this->freshness = max(0, min($freshness, $urgency->maxFreshness()));
    }

    /** What its card shows at a glance: all good, soon or now, too late. */
    public function alert(): string
    {
        return $this->urgency->alert();
    }

    public function hasPendingCommitment(): bool
    {
        return null !== $this->weeklyCommitment && $this->doneThisWeek < $this->weeklyCommitment;
    }
}
