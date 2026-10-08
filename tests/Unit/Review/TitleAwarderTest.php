<?php

namespace App\Tests\Unit\Review;

use App\Entity\Completion;
use App\Enum\Urgency;
use App\Progress\MemberProgress;
use App\Review\TitleAwarder;
use App\Tests\Unit\TaskFactory;
use PHPUnit\Framework\TestCase;

final class TitleAwarderTest extends TestCase
{
    use TaskFactory;

    private const SATURDAY = 6;

    public function testAbsentAllWeek(): void
    {
        $title = (new TitleAwarder())->award(new MemberProgress($this->member(), 0, 0, 7), [], self::SATURDAY);

        self::assertSame('En vadrouille', $title->name);
    }

    public function testRescuingLateTasksIsCelebrated(): void
    {
        $member = $this->member();
        $completions = [
            new Completion($this->task(), $member, new \DateTimeImmutable('2026-10-06'), Urgency::Late),
            new Completion($this->task(), $member, new \DateTimeImmutable('2026-10-07'), Urgency::Late),
        ];

        $title = (new TitleAwarder())->award(new MemberProgress($member, 80, 200, 0), $completions, self::SATURDAY);

        self::assertSame('As du rattrapage', $title->name);
    }

    public function testDoingTheSameTaskOverAndOver(): void
    {
        $member = $this->member();
        $task = $this->task();
        $completions = array_map(
            static fn (string $day): Completion => new Completion($task, $member, new \DateTimeImmutable($day), Urgency::Soon),
            ['2026-10-05', '2026-10-07', '2026-10-09'],
        );

        $title = (new TitleAwarder())->award(new MemberProgress($member, 90, 200, 0), $completions, self::SATURDAY);

        self::assertSame('Spécialiste Serpillière', $title->name);
    }

    public function testThereIsAlwaysAKindTitle(): void
    {
        $title = (new TitleAwarder())->award(new MemberProgress($this->member(), 0, 200, 0), [], self::SATURDAY);

        self::assertSame('En mode économie d’énergie', $title->name);
    }
}
