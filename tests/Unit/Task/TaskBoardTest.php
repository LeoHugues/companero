<?php

namespace App\Tests\Unit\Task;

use App\Entity\Bounty;
use App\Entity\Zone;
use App\Enum\BountyKind;
use App\Enum\TaskKind;
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

    public function testTheCasaAsksForFreeTasksWhoseMomentHasComeASurpriseFirst(): void
    {
        $robin = $this->member(name: 'Robin');
        $late = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Late, 0));
        $soon = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Soon, 40));
        $taken = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Late, 0), reservedBy: $robin);
        $assigned = $this->rollingTask(7, null);
        $assigned->setAssignee($robin);
        $surprise = $this->rollingTask(7, null);
        $hidden = new TaskView($surprise, new TaskStatus(Urgency::Fresh, 90), bounty: new Bounty($surprise, new \DateTimeImmutable('2026-10-05'), BountyKind::Points, 15));

        $board = new TaskBoard([
            $late,
            $soon,
            $taken,
            new TaskView($assigned, new TaskStatus(Urgency::Due, 10)),
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Fresh, 90)),
            new TaskView($this->task(TaskKind::Quick), new TaskStatus(Urgency::Due, 10)),
            new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Due, 10), availableAt: new \DateTimeImmutable('2026-10-11')),
            $hidden,
        ]);

        // Nobody took them, nobody is in charge: a surprise first, then the most pressing — never an express task, nor one resting.
        self::assertSame([$hidden, $late, $soon], $board->quests());
        self::assertSame([$hidden, $late], $board->quests(2));
    }

    public function testTheDeckDealsTheCasasFirstQuestOtherwiseTheMostPressingFreeTask(): void
    {
        $taken = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Late, 0), reservedBy: $this->member());
        $resting = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Fresh, 95), availableAt: new \DateTimeImmutable('2026-10-11'));
        $fresh = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Fresh, 90));
        $soon = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Soon, 40));

        self::assertSame($soon, (new TaskBoard([$taken, $soon, $fresh]))->draw());
        // No quest left: a free task in no hurry still makes a card to draw, but not one resting after it was done.
        self::assertSame($fresh, (new TaskBoard([$taken, $resting, $fresh]))->draw());
        self::assertNull((new TaskBoard([$taken, $resting]))->draw());
    }

    public function testTheHandOfAMemberHoldsWhatTheyTookAndWhatIsCountedOnThem(): void
    {
        $leo = $this->member();
        $mine = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Fresh, 90), reservedBy: $leo);
        $theirs = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Late, 0), reservedBy: $this->member(name: 'Léa'));

        self::assertSame([$mine], (new TaskBoard([$theirs, $mine]))->inHandOf($leo));
    }

    public function testTheListToCareForLeavesOutExpressTasksAndOccasionalOnesAsleep(): void
    {
        $asleep = $this->task(TaskKind::Occasional);
        $raised = $this->task(TaskKind::Occasional);
        $raised->raise(new \DateTimeImmutable('2026-10-10'));
        $rolling = new TaskView($this->rollingTask(7, null), new TaskStatus(Urgency::Soon, 40));
        $raisedView = new TaskView($raised, new TaskStatus(Urgency::Due, 10));

        $board = new TaskBoard([
            $rolling,
            $raisedView,
            new TaskView($asleep, new TaskStatus(Urgency::Fresh, 100)),
            new TaskView($this->task(TaskKind::Quick), new TaskStatus(Urgency::Fresh, 100)),
        ]);

        self::assertSame([$raisedView, $rolling], $board->toCareFor());
    }
}
