<?php

namespace App\Task;

use App\Entity\Bounty;
use App\Entity\Member;
use App\Entity\Task;
use App\Enum\Rarity;
use App\Reminder\Reminder;

final readonly class TaskView
{
    public function __construct(
        public Task $task,
        public TaskStatus $status,
        public ?Member $reservedBy = null,
        public ?Reminder $reminder = null,
        /** Still in its cooldown: when it can be done again. */
        public ?\DateTimeImmutable $availableAt = null,
        /** This week's surprise hidden in the card, if nobody found it yet. */
        public ?Bounty $bounty = null,
    ) {
    }

    /** The card's rarity: one class up while it hides a surprise. */
    public function rarity(): Rarity
    {
        return null !== $this->bounty ? $this->task->getRarity()->next() : $this->task->getRarity();
    }

    public function isReservedBy(Member $member): bool
    {
        return $this->reservedBy === $member;
    }
}
