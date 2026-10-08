<?php

namespace App\Task;

use App\Entity\Member;
use App\Entity\Task;
use App\Reminder\Reminder;

final readonly class TaskView
{
    public function __construct(
        public Task $task,
        public TaskStatus $status,
        public ?Member $reservedBy = null,
        public ?Reminder $reminder = null,
    ) {
    }

    public function isReservedBy(Member $member): bool
    {
        return $this->reservedBy === $member;
    }
}
