<?php

namespace App\Reminder;

use App\Entity\Member;

/** Who should be reminded of a task right now. */
final readonly class Reminder
{
    /**
     * @param list<Member> $recipients
     * @param list<Member> $owners     a pet's humans, when the task is theirs (nobody else is assigned to it)
     */
    public function __construct(
        public array $recipients,
        /** The assignee, when they are away and someone else is reminded instead. */
        public ?Member $standingInFor = null,
        public array $owners = [],
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

    /** A pet's task while none of its humans is home: the others at home are reminded instead. */
    public function ownersAway(): bool
    {
        return [] !== $this->owners && [] === array_uintersect($this->owners, $this->recipients, static fn (Member $a, Member $b): int => spl_object_id($a) <=> spl_object_id($b));
    }
}
