<?php

namespace App\Reminder;

use App\Entity\Member;
use App\Entity\Task;

/**
 * The assignee if they are home; otherwise the chosen backup if they are home;
 * otherwise everyone at home. A task nobody is assigned to concerns everyone at home.
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
            return new Reminder($atHome);
        }
        if ($assignee->isAtHome()) {
            return new Reminder([$assignee]);
        }

        $backup = $task->getBackup();

        return new Reminder(null !== $backup && $backup->isAtHome() ? [$backup] : $atHome, $assignee);
    }
}
