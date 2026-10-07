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

    public function testRescuingALateTaskIsCelebrated(): void
    {
        $member = $this->member();
        $completion = new Completion($this->task(), $member, new \DateTimeImmutable('2026-10-07'), Urgency::Late);

        $title = (new TitleAwarder())->award(new MemberProgress($member, 4, 20, 0), [$completion], self::SATURDAY);

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

        $title = (new TitleAwarder())->award(new MemberProgress($member, 9, 20, 0), $completions, self::SATURDAY);

        self::assertSame('Spécialiste Serpillière', $title->name);
    }

    public function testThereIsAlwaysAKindTitle(): void
    {
        $title = (new TitleAwarder())->award(new MemberProgress($this->member(), 0, 20, 0), [], self::SATURDAY);

        self::assertSame('En mode économie d’énergie', $title->name);
    }
}
