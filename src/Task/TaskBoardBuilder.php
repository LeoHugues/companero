<?php

namespace App\Task;

use App\Calendar\Week;
use App\Entity\Household;
use App\Entity\Task;
use App\Reminder\ReminderRecipients;
use App\Repository\CompletionRepository;
use App\Repository\TaskRepository;
use Psr\Clock\ClockInterface;

final readonly class TaskBoardBuilder
{
    public function __construct(
        private TaskRepository $tasks,
        private CompletionRepository $completions,
        private TaskStatusResolver $resolver,
        private ReminderRecipients $reminders,
        private ClockInterface $clock,
    ) {
    }

    public function build(Household $household): TaskBoard
    {
        $now = $this->clock->now();
        $week = Week::containing($now);
        $doneThisWeek = $this->completions->countByTask($household, $week->start, $week->end());

        return new TaskBoard(array_map(
            fn (Task $task): TaskView => new TaskView(
                $task,
                $this->resolver->resolve($task, $now, $household->getCleaningDay(), $doneThisWeek[$task->getId()] ?? 0),
                $task->reservedByAt($now),
                $this->reminders->for($task, $household->getMembers()),
            ),
            $this->tasks->findActive($household),
        ));
    }
}
