<?php

namespace App\Reminder;

use App\Entity\Member;
use App\Entity\Task;
use App\Enum\TaskCategory;

/**
 * The assignee if they are home; otherwise the chosen backup if they are home;
 * otherwise everyone at home. A task nobody is assigned to concerns everyone at home —
 * unless it is a pet's: then its humans at home, or everyone at home when none of them is.
 */
final class ReminderRecipients
{
    /** @param iterable<Member> $members the household's members */
    public function for(Task $task, iterable $members): Reminder
    {
        $atHome = [];
        foreach ($members as $member) {
            if ($member->isAtHome()) {
                $atHome[] = $member;
            }
        }

        $assignee = $task->getAssignee();
        if (null === $assignee) {
            $owners = $this->ownersOf($task);
            $ownersAtHome = array_values(array_filter($owners, static fn (Member $owner): bool => $owner->isAtHome()));

            return new Reminder([] !== $ownersAtHome ? $ownersAtHome : $atHome, owners: $owners);
        }
        if ($assignee->isAtHome()) {
            return new Reminder([$assignee]);
        }

        $backup = $task->getBackup();

        return new Reminder(null !== $backup && $backup->isAtHome() ? [$backup] : $atHome, $assignee);
    }

    /** @return list<Member> the humans of its pet, or of every pet for a task of the pets in general */
    private function ownersOf(Task $task): array
    {
        $pets = null !== $task->getPet() ? [$task->getPet()] : (TaskCategory::Pets === $task->getCategory() ? $task->getHousehold()->getPets()->toArray() : []);
        $owners = [];
        foreach ($pets as $pet) {
            foreach ($pet->getOwners() as $owner) {
                $owners[spl_object_id($owner)] = $owner;
            }
        }

        return array_values($owners);
    }
}
