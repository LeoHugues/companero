<?php

namespace App\Reminder;

use App\Entity\Member;

/** Who should be reminded of a task right now. */
final readonly class Reminder
{
    /** @param list<Member> $recipients */
    public function __construct(
        public array $recipients,
        /** The assignee, when they are away and someone else is reminded instead. */
        public ?Member $standingInFor = null,
    ) {
    }

    public function concerns(Member $member): bool
    {
        return \in_array($member, $this->recipients, true);
    }

    /** Reminded on their own: the task is theirs, or they stand in for the assignee. */
    public function isPersonalFor(Member $member): bool
    {
        return [$member] === $this->recipients;
    }
}
