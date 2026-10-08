<?php

namespace App\Tests\Unit\Task;

use App\Entity\Task;
use App\Enum\TaskKind;
use App\Enum\Urgency;
use App\Task\TaskStatusResolver;
use App\Tests\Unit\TaskFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskStatusResolverTest extends TestCase
{
    use TaskFactory;

    private const SATURDAY = 6;

    private TaskStatusResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TaskStatusResolver();
    }

    /** @return iterable<string, array{string, Urgency, int}> */
    public static function rollingCases(): iterable
    {
        // Rhythm of 10 days, last done Thursday 1st at noon, 24h margin.
        yield 'just done' => ['2026-10-01 12:00', Urgency::Fresh, 100];
        yield 'before 60% of the rhythm' => ['2026-10-06 12:00', Urgency::Fresh, 50];
        yield 'after 60% of the rhythm' => ['2026-10-08 12:00', Urgency::Soon, 30];
        yield 'rhythm reached' => ['2026-10-11 12:00', Urgency::Due, 0];
        yield 'within the margin' => ['2026-10-12 11:00', Urgency::Due, 0];
        yield 'beyond the margin' => ['2026-10-12 13:00', Urgency::Late, 0];
    }

    #[DataProvider('rollingCases')]
    public function testRollingTaskFollowsItsRhythm(string $now, Urgency $expected, int $freshness): void
    {
        $task = $this->rollingTask(10, '2026-10-01 12:00');

        $status = $this->resolver->resolve($task, new \DateTimeImmutable($now), self::SATURDAY);

        self::assertSame($expected, $status->urgency);
        self::assertSame($freshness, $status->freshness);
    }

    public function testLateTaskCountsDaysSinceItWasDue(): void
    {
        $task = $this->rollingTask(7, '2026-09-24 10:00');

        $status = $this->resolver->resolve($task, new \DateTimeImmutable('2026-10-04 11:00'), self::SATURDAY);

        self::assertSame(Urgency::Late, $status->urgency);
        self::assertSame(3, $status->overdueDays);
    }

    public function testNeverDoneRollingTaskIsDueRightAway(): void
    {
        $task = $this->rollingTask(3, null);

        $status = $this->resolver->resolve($task, new \DateTimeImmutable('2026-01-01 08:00'), self::SATURDAY);

        self::assertSame(Urgency::Due, $status->urgency);
    }

    public function testUnmetWeeklyCommitmentIsSoonBeforeTheCleaningDay(): void
    {
        $task = $this->rollingTask(7, '2026-10-01 10:00', weeklyCommitment: 1);

        // Monday 5th: the rhythm alone says "fresh", the commitment for this week is not met yet.
        $status = $this->resolver->resolve($task, new \DateTimeImmutable('2026-10-05 09:00'), self::SATURDAY, doneThisWeek: 0);

        self::assertSame(Urgency::Soon, $status->urgency);
        self::assertTrue($status->hasPendingCommitment());
    }

    public function testUnmetWeeklyCommitmentIsDueOnTheCleaningDay(): void
    {
        $task = $this->rollingTask(7, '2026-10-06 10:00', weeklyCommitment: 1);

        $status = $this->resolver->resolve($task, new \DateTimeImmutable('2026-10-10 09:00'), self::SATURDAY, doneThisWeek: 0);

        self::assertSame(Urgency::Due, $status->urgency);
        self::assertLessThanOrEqual(Urgency::Due->maxFreshness(), $status->freshness);
    }

    public function testMetWeeklyCommitmentLetsTheRhythmDecide(): void
    {
        $task = $this->rollingTask(7, '2026-10-06 10:00', weeklyCommitment: 1);

        $status = $this->resolver->resolve($task, new \DateTimeImmutable('2026-10-10 09:00'), self::SATURDAY, doneThisWeek: 1);

        self::assertSame(Urgency::Fresh, $status->urgency);
        self::assertFalse($status->hasPendingCommitment());
    }

    /** @return iterable<string, array{string, Urgency}> */
    public static function scheduledCases(): iterable
    {
        // Bins every Tuesday at 20:00, 2h margin, last taken out Tuesday 29 September.
        yield 'days before' => ['2026-10-03 10:00', Urgency::Fresh];
        yield 'two days before' => ['2026-10-05 10:00', Urgency::Soon];
        yield 'the day before' => ['2026-10-05 21:00', Urgency::Due];
        yield 'within the margin' => ['2026-10-06 21:30', Urgency::Due];
        yield 'missed' => ['2026-10-06 22:30', Urgency::Late];
    }

    #[DataProvider('scheduledCases')]
    public function testScheduledTaskTargetsItsNextOccurrence(string $now, Urgency $expected): void
    {
        $task = $this->task(TaskKind::Scheduled);
        $task->setScheduledWeekday(2);
        $task->setScheduledTime(new \DateTimeImmutable('20:00'));
        $task->setMarginHours(2);
        $task->complete(new \DateTimeImmutable('2026-09-29 19:30'));

        $status = $this->resolver->resolve($task, new \DateTimeImmutable($now), self::SATURDAY);

        self::assertSame($expected, $status->urgency);
        self::assertEquals(new \DateTimeImmutable('2026-10-06 20:00'), $status->dueAt);
    }

    public function testScheduledTaskDoneEarlyCoversTheComingOccurrence(): void
    {
        $task = $this->task(TaskKind::Scheduled);
        $task->setScheduledWeekday(2);
        $task->setScheduledTime(new \DateTimeImmutable('20:00'));
        // Taken out Monday evening, the day before collection.
        $task->complete(new \DateTimeImmutable('2026-10-05 21:00'));

        $status = $this->resolver->resolve($task, new \DateTimeImmutable('2026-10-06 10:00'), self::SATURDAY);

        self::assertSame(Urgency::Fresh, $status->urgency);
        self::assertEquals(new \DateTimeImmutable('2026-10-13 20:00'), $status->dueAt);
    }

    /** @return iterable<string, array{string, string, Urgency}> */
    public static function dailyCases(): iterable
    {
        // The cats eat every day at 19:00; they were fed yesterday evening.
        yield 'the morning after' => ['2026-10-07 09:00', '2026-10-07 19:00', Urgency::Fresh];
        yield 'in the afternoon' => ['2026-10-07 14:00', '2026-10-07 19:00', Urgency::Soon];
        yield 'dinner time' => ['2026-10-07 18:30', '2026-10-07 19:00', Urgency::Due];
        yield 'forgotten' => ['2026-10-07 22:30', '2026-10-07 19:00', Urgency::Late];
    }

    #[DataProvider('dailyCases')]
    public function testDailyTaskComesBackEveryDay(string $now, string $dueAt, Urgency $expected): void
    {
        $task = $this->task(TaskKind::Scheduled);
        $task->setScheduledWeekday(Task::EVERY_DAY);
        $task->setScheduledTime(new \DateTimeImmutable('19:00'));
        $task->setMarginHours(2);
        $task->complete(new \DateTimeImmutable('2026-10-06 19:10'));

        $status = $this->resolver->resolve($task, new \DateTimeImmutable($now), self::SATURDAY);

        self::assertSame($expected, $status->urgency);
        self::assertEquals(new \DateTimeImmutable($dueAt), $status->dueAt);
    }

    public function testDailyTaskDoneInTheAfternoonCoversTheEvening(): void
    {
        $task = $this->task(TaskKind::Scheduled);
        $task->setScheduledWeekday(Task::EVERY_DAY);
        $task->setScheduledTime(new \DateTimeImmutable('19:00'));
        $task->complete(new \DateTimeImmutable('2026-10-07 16:00'));

        $status = $this->resolver->resolve($task, new \DateTimeImmutable('2026-10-07 19:30'), self::SATURDAY);

        self::assertSame(Urgency::Fresh, $status->urgency);
        self::assertEquals(new \DateTimeImmutable('2026-10-08 19:00'), $status->dueAt);
    }

    public function testOneOffWithoutDeadlineIsNeverPressing(): void
    {
        $status = $this->resolver->resolve($this->task(), new \DateTimeImmutable('2026-10-05'), self::SATURDAY);

        self::assertSame(Urgency::Fresh, $status->urgency);
    }

    public function testOneOffBecomesLateAfterItsDeadlineAndMargin(): void
    {
        $task = $this->task();
        $task->setDueAt(new \DateTimeImmutable('2026-10-09 18:00'));

        self::assertSame(Urgency::Due, $this->resolver->resolve($task, new \DateTimeImmutable('2026-10-09 09:00'), self::SATURDAY)->urgency);
        self::assertSame(Urgency::Late, $this->resolver->resolve($task, new \DateTimeImmutable('2026-10-12 09:00'), self::SATURDAY)->urgency);
    }
}
