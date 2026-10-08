<?php

namespace App\Task;

use App\Calendar\Week;
use App\Entity\Completion;
use App\Entity\Member;
use App\Entity\PointEntry;
use App\Entity\Task;
use App\Enum\PointReason;
use App\Repository\CompletionRepository;
use App\Repository\PointEntryRepository;
use App\Reward\BoostResolver;
use App\Reward\GiftGranter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Records that a member did a task, and credits the points it was worth at that moment —
 * right now, or earlier when it is noted afterwards. A completion can be put right later on.
 */
final readonly class TaskCompleter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompletionRepository $completions,
        private PointEntryRepository $points,
        private TaskStatusResolver $resolver,
        private BonusPolicy $bonusPolicy,
        private BoostResolver $boosts,
        private GiftGranter $gifts,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param ?\DateTimeImmutable $at when it was done, if not right now (never in the future)
     *
     * @throws TaskNotAvailable when it was done too recently
     */
    public function complete(Task $task, Member $member, ?\DateTimeImmutable $at = null): CompletionResult
    {
        $now = $this->clock->now();
        $backdated = null !== $at && $at < $now;
        $at = $backdated ? $at : $now;
        if (!$backdated && null !== $availableAt = $task->availableAt($now)) {
            throw new TaskNotAvailable($availableAt);
        }
        $status = $this->statusAt($task, $at, $backdated ? $this->completions->findLatestForTask($task, $at)?->getCompletedAt() : $task->getLastCompletedAt());

        $completion = new Completion($task, $member, $at, $status->urgency);
        $this->entityManager->persist($completion);
        $result = $this->score($completion, $task->getPoints(), $status);

        // Noted afterwards, it may come before a later completion: that one stays the reference.
        if (null === $task->getLastCompletedAt() || $at >= $task->getLastCompletedAt()) {
            $task->complete($at, $member);
        }
        $this->entityManager->flush();

        return new CompletionResult($completion, $result['base'], $result['bonus'], $result['boost'], $result['xp'], $this->gifts->catchUp($member, $now));
    }

    /**
     * Moves a completion to another moment or member, or changes its base points: bonuses and boosts
     * are worked out again for that moment, the weekly scores and the task's rhythm follow.
     */
    public function amend(Completion $completion, Member $member, \DateTimeImmutable $at, int $basePoints): void
    {
        $now = $this->clock->now();
        $at = min($at, $now);
        $task = $completion->getTask();
        $status = $this->statusAt($task, $at, $this->completions->findLatestForTask($task, $at, $completion)?->getCompletedAt(), $completion);
        $previousMember = $completion->getMember();

        foreach ($this->points->findBy(['completion' => $completion]) as $entry) {
            if (PointReason::Adjustment === $entry->getReason()) {
                // "This time it was more work": still true, wherever the completion goes.
                $entry->moveTo($member, $at);
            } else {
                $this->entityManager->remove($entry);
            }
        }
        $completion->amend($member, $at, $status->urgency);
        $this->score($completion, $basePoints, $status);
        $this->entityManager->flush();

        $latest = $this->completions->findLatestForTask($task);
        $task->restoreLastCompletion($latest?->getCompletedAt(), $latest?->getMember());
        $this->entityManager->flush();

        $this->gifts->catchUp($member, $now);
        if ($previousMember !== $member) {
            $this->gifts->catchUp($previousMember, $now);
        }
    }

    private function statusAt(Task $task, \DateTimeImmutable $at, ?\DateTimeImmutable $lastCompletedAt, ?Completion $except = null): TaskStatus
    {
        $week = Week::containing($at);

        return $this->resolver->resolveAt(
            $task,
            $at,
            $task->getHousehold()->getCleaningDay(),
            $this->completions->countForTask($task, $week->start, min($at, $week->end()), $except),
            $lastCompletedAt,
        );
    }

    /** @return array{base: int, bonus: ?Bonus, boost: int, xp: int} */
    private function score(Completion $completion, int $basePoints, TaskStatus $status): array
    {
        $task = $completion->getTask();
        $member = $completion->getMember();
        $at = $completion->getCompletedAt();
        $this->credit($completion, PointReason::Task, $basePoints);

        $bonus = $this->bonusPolicy->bonusFor($task, $status);
        if (null !== $bonus) {
            $this->credit($completion, $bonus->reason, $bonus->points);
        }
        // Boosts apply to the base points of the task.
        $boostPoints = $this->boosts->points($member, $at)?->bonusFor($basePoints) ?? 0;
        $this->credit($completion, PointReason::Boost, $boostPoints);
        $boostXp = $this->boosts->xp($member, $at)?->bonusFor($basePoints) ?? 0;
        $this->credit($completion, PointReason::XpBoost, $boostXp);

        return ['base' => $basePoints, 'bonus' => $bonus, 'boost' => $boostPoints, 'xp' => $boostXp];
    }

    private function credit(Completion $completion, PointReason $reason, int $points): void
    {
        if (0 !== $points) {
            $this->entityManager->persist(new PointEntry($completion->getMember(), $reason, $points, $completion->getTask()->getTitle(), $completion->getCompletedAt(), $completion));
        }
    }
}
