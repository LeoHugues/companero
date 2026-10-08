<?php

namespace App\Task;

use App\Calendar\Week;
use App\Entity\Completion;
use App\Entity\Member;
use App\Entity\PointEntry;
use App\Entity\Task;
use App\Enum\PointReason;
use App\Repository\CompletionRepository;
use App\Reward\BoostResolver;
use App\Reward\GiftGranter;
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
        private BoostResolver $boosts,
        private GiftGranter $gifts,
        private ClockInterface $clock,
    ) {
    }

    /** @throws TaskNotAvailable when it was done too recently */
    public function complete(Task $task, Member $member): CompletionResult
    {
        $now = $this->clock->now();
        if (null !== $availableAt = $task->availableAt($now)) {
            throw new TaskNotAvailable($availableAt);
        }
        $week = Week::containing($now);
        $status = $this->resolver->resolve(
            $task,
            $now,
            $task->getHousehold()->getCleaningDay(),
            $this->completions->countForTask($task, $week->start, $week->end()),
        );

        $completion = new Completion($task, $member, $now, $status->urgency);
        $this->entityManager->persist($completion);
        $this->credit($member, PointReason::Task, $task->getPoints(), $task->getTitle(), $now, $completion);

        $bonus = $this->bonusPolicy->bonusFor($task, $status);
        if (null !== $bonus) {
            $this->credit($member, $bonus->reason, $bonus->points, $task->getTitle(), $now, $completion);
        }
        // Boosts apply to the base points of the task.
        $boostPoints = $this->boosts->points($member, $now)?->bonusFor($task->getPoints()) ?? 0;
        $this->credit($member, PointReason::Boost, $boostPoints, $task->getTitle(), $now, $completion);
        $boostXp = $this->boosts->xp($member, $now)?->bonusFor($task->getPoints()) ?? 0;
        $this->credit($member, PointReason::XpBoost, $boostXp, $task->getTitle(), $now, $completion);

        $task->complete($now, $member);
        $this->entityManager->flush();

        return new CompletionResult($completion, $task->getPoints(), $bonus, $boostPoints, $boostXp, $this->gifts->catchUp($member, $now));
    }

    private function credit(Member $member, PointReason $reason, int $points, string $label, \DateTimeImmutable $at, Completion $completion): void
    {
        if (0 !== $points) {
            $this->entityManager->persist(new PointEntry($member, $reason, $points, $label, $at, $completion));
        }
    }
}
