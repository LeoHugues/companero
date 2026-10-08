<?php

namespace App\Task;

use App\Calendar\Week;
use App\Entity\Bounty;
use App\Entity\Household;
use App\Entity\Task;
use App\Reminder\ReminderRecipients;
use App\Repository\BountyRepository;
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
        private BountyRepository $bounties,
        private ClockInterface $clock,
    ) {
    }

    public function build(Household $household): TaskBoard
    {
        $now = $this->clock->now();
        $week = Week::containing($now);
        $doneThisWeek = $this->completions->countByTask($household, $week->start, $week->end());
        $bounties = $this->bounties->findForWeek($household, $week->start);

        return new TaskBoard(array_map(
            fn (Task $task): TaskView => $this->viewOf($task, $now, $doneThisWeek[$task->getId()] ?? 0, $bounties[$task->getId()] ?? null),
            $this->tasks->findActive($household),
        ));
    }

    /** One task on its own, for its card's page. */
    public function view(Task $task): TaskView
    {
        $now = $this->clock->now();
        $week = Week::containing($now);

        return $this->viewOf($task, $now, $this->completions->countForTask($task, $week->start, $week->end()), $this->bounties->findOneForWeek($task, $week->start));
    }

    private function viewOf(Task $task, \DateTimeImmutable $now, int $doneThisWeek, ?Bounty $bounty): TaskView
    {
        $household = $task->getHousehold();

        return new TaskView(
            $task,
            $this->resolver->resolve($task, $now, $household->getCleaningDay(), $doneThisWeek),
            $task->reservedByAt($now),
            $this->reminders->for($task, $household->getMembers()),
            $task->availableAt($now),
            $bounty?->isClaimed() ? null : $bounty,
        );
    }
}
