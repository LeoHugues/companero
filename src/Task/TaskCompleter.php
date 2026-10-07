<?php

namespace App\Task;

use App\Calendar\Week;
use App\Entity\Completion;
use App\Entity\Member;
use App\Entity\PointEntry;
use App\Entity\Task;
use App\Enum\PointReason;
use App\Repository\CompletionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Records that a member did a task, and credits the points it was worth at that moment. */
final readonly class TaskCompleter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompletionRepository $completions,
        private TaskStatusResolver $resolver,
        private BonusPolicy $bonusPolicy,
        private ClockInterface $clock,
    ) {
    }

    public function complete(Task $task, Member $member): CompletionResult
    {
        $now = $this->clock->now();
        $week = Week::containing($now);
        $status = $this->resolver->resolve(
            $task,
            $now,
            $task->getHousehold()->getCleaningDay(),
            $this->completions->countForTask($task, $week->start, $week->end()),
        );

        $completion = new Completion($task, $member, $now, $status->urgency);
        $this->entityManager->persist($completion);
        $this->entityManager->persist(new PointEntry($member, PointReason::Task, $task->getPoints(), $task->getTitle(), $now, $completion));

        $bonus = $this->bonusPolicy->bonusFor($task, $status);
        if (null !== $bonus) {
            $this->entityManager->persist(new PointEntry($member, $bonus->reason, $bonus->points, $task->getTitle(), $now, $completion));
        }

        $task->complete($now);
        $this->entityManager->flush();

        return new CompletionResult($completion, $task->getPoints(), $bonus);
    }
}
