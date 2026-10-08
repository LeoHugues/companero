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
    /** By default, share of the rhythm after which a rolling task starts to show up as "soon". */
    private const SOON_RATIO = 0.6;
    /** A dated task becomes due this long before its deadline… */
    private const LEAD_TIME = self::DAY;
    /** …or, for a task that comes back every day, a few hours before. */
    private const DAILY_LEAD_TIME = 3 * self::HOUR;
    /** Being done this long before a daily occurrence covers it (fed at 8 a.m., done for the evening). */
    private const DAILY_COVER = 12 * self::HOUR;

    public function resolve(Task $task, \DateTimeImmutable $now, int $cleaningDay, int $doneThisWeek = 0): TaskStatus
    {
        return $this->resolveAt($task, $now, $cleaningDay, $doneThisWeek, $task->getLastCompletedAt());
    }

    /** Where the task stood at a given moment, knowing when it had last been done before then (a task noted afterwards). */
    public function resolveAt(Task $task, \DateTimeImmutable $now, int $cleaningDay, int $doneThisWeek, ?\DateTimeImmutable $lastCompletedAt): TaskStatus
    {
        return match ($task->getKind()) {
            TaskKind::Rolling => $this->rolling($task, $now, $cleaningDay, $doneThisWeek, $lastCompletedAt),
            TaskKind::Scheduled => $this->scheduled($task, $now, $lastCompletedAt),
            TaskKind::OneOff => $this->oneOff($task, $now),
            TaskKind::Quick => new TaskStatus(Urgency::Fresh, 100),
            // Asleep until someone says it is needed; then the moment has come.
            TaskKind::Occasional => null === $task->getRaisedAt()
                ? new TaskStatus(Urgency::Fresh, 100)
                : $this->deadlineStatus($task->getRaisedAt(), $task->getMarginHours(), 0, $now, self::DAY),
        };
    }

    private function rolling(Task $task, \DateTimeImmutable $now, int $cleaningDay, int $doneThisWeek, ?\DateTimeImmutable $lastCompletedAt): TaskStatus
    {
        $rhythm = max(1, (int) $task->getRhythmDays()) * self::DAY;
        $margin = $task->getMarginHours() * self::HOUR;
        // Never done yet: it is due from the moment it is created.
        $reference = $lastCompletedAt ?? $task->getCreatedAt()->modify(\sprintf('-%d seconds', $rhythm));
        $elapsed = $now->getTimestamp() - $reference->getTimestamp();
        $dueAt = $reference->modify(\sprintf('+%d seconds', $rhythm));
        // Orange from this long after it was done: set on the task, or at 60 % of the rhythm.
        $soonAfter = null !== $task->getWarningHours()
            ? max(0, $rhythm - $task->getWarningHours() * self::HOUR)
            : (int) ($rhythm * self::SOON_RATIO);

        $urgency = match (true) {
            $elapsed < $soonAfter => Urgency::Fresh,
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
            warningAt: $reference->modify(\sprintf('+%d seconds', $soonAfter)),
            lateAt: $dueAt->modify(\sprintf('+%d seconds', $margin)),
        );
    }

    private function scheduled(Task $task, \DateTimeImmutable $now, ?\DateTimeImmutable $lastCompletedAt): TaskStatus
    {
        $daily = $task->isDaily();
        // A completion covers an occurrence when it happens shortly before it.
        $after = null !== $lastCompletedAt
            ? $lastCompletedAt->modify(\sprintf('+%d seconds', $daily ? self::DAILY_COVER : self::LEAD_TIME))
            : $task->getCreatedAt();
        $occurrence = $this->nextOccurrence($task, $after);

        return $daily
            ? $this->deadlineStatus($occurrence, min($task->getMarginHours(), 12), $task->getWarningHours(), $now, self::DAY, self::DAILY_LEAD_TIME)
            : $this->deadlineStatus($occurrence, $task->getMarginHours(), $task->getWarningHours(), $now, 7 * self::DAY);
    }

    private function oneOff(Task $task, \DateTimeImmutable $now): TaskStatus
    {
        $dueAt = $task->getDueAt();
        if (null === $dueAt) {
            return new TaskStatus(Urgency::Fresh, 100);
        }

        return $this->deadlineStatus($dueAt, $task->getMarginHours(), $task->getWarningHours(), $now, 7 * self::DAY);
    }

    /** @param ?int $warningHours how long before the deadline it turns orange; null: twice the lead time */
    private function deadlineStatus(\DateTimeImmutable $deadline, int $marginHours, ?int $warningHours, \DateTimeImmutable $now, int $horizon, int $leadTime = self::LEAD_TIME): TaskStatus
    {
        $left = $deadline->getTimestamp() - $now->getTimestamp();
        $warnBefore = null !== $warningHours ? $warningHours * self::HOUR : 2 * $leadTime;

        $urgency = match (true) {
            $left > $warnBefore => Urgency::Fresh,
            $left > $leadTime => Urgency::Soon,
            $left >= -$marginHours * self::HOUR => Urgency::Due,
            default => Urgency::Late,
        };

        return new TaskStatus(
            urgency: $urgency,
            freshness: (int) round(100 * $left / $horizon),
            dueAt: $deadline,
            overdueDays: Urgency::Late === $urgency ? intdiv(-$left, self::DAY) : 0,
            warningAt: $deadline->modify(\sprintf('-%d seconds', $warnBefore)),
            lateAt: $deadline->modify(\sprintf('+%d hours', $marginHours)),
        );
    }

    private function nextOccurrence(Task $task, \DateTimeImmutable $after): \DateTimeImmutable
    {
        $time = $task->getScheduledTime() ?? new \DateTimeImmutable('00:00');
        $weekday = (int) $task->getScheduledWeekday();

        $candidate = $after->setTime((int) $time->format('H'), (int) $time->format('i'));
        if (Task::EVERY_DAY === $weekday) {
            return $candidate > $after ? $candidate : $candidate->modify('+1 day');
        }
        $candidate = $candidate->modify(\sprintf('+%d days', ($weekday - (int) $candidate->format('N') + 7) % 7));

        return $candidate > $after ? $candidate : $candidate->modify('+7 days');
    }
}
