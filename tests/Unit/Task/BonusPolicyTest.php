<?php

namespace App\Tests\Unit\Task;

use App\Enum\PointReason;
use App\Enum\Urgency;
use App\Task\BonusPolicy;
use App\Task\TaskStatus;
use App\Tests\Unit\TaskFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BonusPolicyTest extends TestCase
{
    use TaskFactory;

    public function testNoBonusWhenTheTaskWasNotPressing(): void
    {
        $policy = new BonusPolicy();

        self::assertNull($policy->bonusFor($this->task(), new TaskStatus(Urgency::Fresh, 80)));
        self::assertNull($policy->bonusFor($this->task(), new TaskStatus(Urgency::Soon, 40)));
    }

    public function testPunctualityPaysOneForSmallTasksAndTwoForBigOnes(): void
    {
        $policy = new BonusPolicy();
        $due = new TaskStatus(Urgency::Due, 0);

        self::assertEquals(1, $policy->bonusFor($this->task(points: 3), $due)?->points);
        self::assertEquals(2, $policy->bonusFor($this->task(points: 4), $due)?->points);
        self::assertSame(PointReason::Punctuality, $policy->bonusFor($this->task(), $due)?->reason);
    }

    /** @return iterable<array{int, int}> */
    public static function rescueCases(): iterable
    {
        yield [0, 0];
        yield [1, 0];
        yield [2, 1];
        yield [3, 1];
        yield [5, 2];
        yield [30, 3];
    }

    #[DataProvider('rescueCases')]
    public function testRescueBonusGrowsSlowlyAndIsCapped(int $overdueDays, int $expected): void
    {
        $bonus = (new BonusPolicy())->bonusFor($this->task(), new TaskStatus(Urgency::Late, 0, overdueDays: $overdueDays));

        self::assertSame($expected, $bonus->points ?? 0);
    }

    public function testBeingOnTimeNeverPaysLessThanTwoDaysOfDelay(): void
    {
        $policy = new BonusPolicy();
        $task = $this->task(points: 2);

        $onTime = $policy->bonusFor($task, new TaskStatus(Urgency::Due, 0))->points ?? 0;
        $twoDaysLate = $policy->bonusFor($task, new TaskStatus(Urgency::Late, 0, overdueDays: 2))->points ?? 0;

        self::assertGreaterThanOrEqual($twoDaysLate, $onTime);
    }
}
