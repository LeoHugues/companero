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

    /**
     * How well the task is looked after, for the Casa's cleanliness: a task done in time counts
     * fully, whatever its gauge says — the house is not dirty because the vacuum will be due in
     * three days. It only weighs on the house once its moment has come, and more so as it is late.
     */
    public function care(): int
    {
        return match ($this->urgency) {
            Urgency::Fresh => 100,
            Urgency::Soon => 92,
            Urgency::Due => 70,
            Urgency::Late => max(0, 45 - 15 * $this->overdueDays),
        };
    }

    public function hasPendingCommitment(): bool
    {
        return null !== $this->weeklyCommitment && $this->doneThisWeek < $this->weeklyCommitment;
    }
}
