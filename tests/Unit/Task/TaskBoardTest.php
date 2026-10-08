<?php

namespace App\Tests\Unit\Task;

use App\Entity\Zone;
use App\Enum\Urgency;
use App\Task\TaskBoard;
use App\Task\TaskStatus;
use App\Task\TaskView;
use App\Tests\Unit\TaskFactory;
use PHPUnit\Framework\TestCase;

final class TaskBoardTest extends TestCase
{
    use TaskFactory;

    public function testMostPressingTasksComeFirst(): void
    {
        $fresh = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Fresh, 90));
        $late = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Late, 0));
        $due = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Due, 10));

        $board = new TaskBoard([$fresh, $late, $due]);

        self::assertSame([$late, $due, $fresh], $board->items);
        self::assertSame([$late, $due], $board->pressing());
    }

    public function testCleanlinessIgnoresPrivateZonesAndOneOffTasks(): void
    {
        $household = $this->household();
        $bedroom = new Zone($household, 'Chambre de Léo', private: true);

        $board = new TaskBoard([
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Fresh, 80)),
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Soon, 40)),
            new TaskView($this->rollingTask(7, null, zone: $bedroom), new TaskStatus(Urgency::Late, 0)),
            new TaskView($this->task(), new TaskStatus(Urgency::Late, 0)),
        ]);

        // Fresh 100, soon 92: the private bedroom and the one-off task do not count.
        self::assertSame(96, $board->cleanliness());
    }

    public function testAHouseKeptInTimeIsRadiantWhateverTheGauges(): void
    {
        $inTime = new TaskBoard([
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Fresh, 65)),
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Fresh, 70)),
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Soon, 30)),
        ]);
        self::assertGreaterThanOrEqual(92, $inTime->cleanliness());

        // Things only weigh on the house once their moment has come, more and more as they are late.
        $behind = new TaskBoard([
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Fresh, 90)),
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Due, 10)),
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Late, 0, overdueDays: 3)),
        ]);
        self::assertSame(57, $behind->cleanliness());
    }

    public function testEmptyHouseIsSpotless(): void
    {
        self::assertSame(100, (new TaskBoard([]))->cleanliness());
    }
}
