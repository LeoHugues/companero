<?php

namespace App\Task;

use App\Entity\Task;
use App\Enum\TaskKind;
use App\Enum\Urgency;

/**
 * Computes the status of a task from its rules and history — nothing is stored,
 * so a task never piles up duplicates: it simply becomes more and more urgent.
 */
final class TaskStatusResolver
{
    private const DAY = 86_400;
    private const HOUR = 3_600;
    /** Share of the rhythm after which a rolling task starts to show up as "soon". */
    private const SOON_RATIO = 0.6;
    /** A dated task becomes due this long before its deadline. */
    private const LEAD_TIME = self::DAY;

    public function resolve(Task $task, \DateTimeImmutable $now, int $cleaningDay, int $doneThisWeek = 0): TaskStatus
    {
        return match ($task->getKind()) {
            TaskKind::Rolling => $this->rolling($task, $now, $cleaningDay, $doneThisWeek),
            TaskKind::Scheduled => $this->scheduled($task, $now),
            TaskKind::OneOff => $this->oneOff($task, $now),
            TaskKind::Quick => new TaskStatus(Urgency::Fresh, 100),
        };
    }

    private function rolling(Task $task, \DateTimeImmutable $now, int $cleaningDay, int $doneThisWeek): TaskStatus
    {
        $rhythm = max(1, (int) $task->getRhythmDays()) * self::DAY;
        $margin = $task->getMarginHours() * self::HOUR;
        // Never done yet: it is due from the moment it is created.
        $reference = $task->getLastCompletedAt() ?? $task->getCreatedAt()->modify(\sprintf('-%d seconds', $rhythm));
        $elapsed = $now->getTimestamp() - $reference->getTimestamp();
        $dueAt = $reference->modify(\sprintf('+%d seconds', $rhythm));

        $urgency = match (true) {
            $elapsed < $rhythm * self::SOON_RATIO => Urgency::Fresh,
            $elapsed < $rhythm => Urgency::Soon,
            $elapsed <= $rhythm + $margin => Urgency::Due,
            default => Urgency::Late,
        };

        $commitment = $task->getWeeklyCommitment();
        if (null !== $commitment && $doneThisWeek < $commitment) {
            // The cleaning day acts as a soft deadline for the weekly commitment.
            $urgency = $urgency->max((int) $now->format('N') >= $cleaningDay ? Urgency::Due : Urgency::Soon);
        }

        return new TaskStatus(
            urgency: $urgency,
            freshness: (int) round(100 * (1 - $elapsed / $rhythm)),
            dueAt: $dueAt,
            overdueDays: Urgency::Late === $urgency ? intdiv($now->getTimestamp() - $dueAt->getTimestamp(), self::DAY) : 0,
            doneThisWeek: null !== $commitment ? $doneThisWeek : null,
            weeklyCommitment: $commitment,
        );
    }

    private function scheduled(Task $task, \DateTimeImmutable $now): TaskStatus
    {
        // A completion covers an occurrence when it happens at most LEAD_TIME before it.
        $after = null !== $task->getLastCompletedAt()
            ? $task->getLastCompletedAt()->modify(\sprintf('+%d seconds', self::LEAD_TIME))
            : $task->getCreatedAt();
        $occurrence = $this->nextOccurrence($task, $after);

        return $this->deadlineStatus($occurrence, $task->getMarginHours(), $now, 7 * self::DAY);
    }

    private function oneOff(Task $task, \DateTimeImmutable $now): TaskStatus
    {
        $dueAt = $task->getDueAt();
        if (null === $dueAt) {
            return new TaskStatus(Urgency::Fresh, 100);
        }

        return $this->deadlineStatus($dueAt, $task->getMarginHours(), $now, 7 * self::DAY);
    }

    private function deadlineStatus(\DateTimeImmutable $deadline, int $marginHours, \DateTimeImmutable $now, int $horizon): TaskStatus
    {
        $left = $deadline->getTimestamp() - $now->getTimestamp();

        $urgency = match (true) {
            $left > 2 * self::LEAD_TIME => Urgency::Fresh,
            $left > self::LEAD_TIME => Urgency::Soon,
            $left >= -$marginHours * self::HOUR => Urgency::Due,
            default => Urgency::Late,
        };

        return new TaskStatus(
            urgency: $urgency,
            freshness: (int) round(100 * $left / $horizon),
            dueAt: $deadline,
            overdueDays: Urgency::Late === $urgency ? intdiv(-$left, self::DAY) : 0,
        );
    }

    private function nextOccurrence(Task $task, \DateTimeImmutable $after): \DateTimeImmutable
    {
        $time = $task->getScheduledTime() ?? new \DateTimeImmutable('00:00');
        $weekday = (int) $task->getScheduledWeekday();

        $candidate = $after->setTime((int) $time->format('H'), (int) $time->format('i'));
        $candidate = $candidate->modify(\sprintf('+%d days', ($weekday - (int) $candidate->format('N') + 7) % 7));

        return $candidate > $after ? $candidate : $candidate->modify('+7 days');
    }
}
