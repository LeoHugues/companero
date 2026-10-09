<?php

namespace App\Tests\Unit\Reminder;

use App\Entity\Pet;
use App\Reminder\ReminderRecipients;
use App\Tests\Unit\TaskFactory;
use PHPUnit\Framework\TestCase;

final class ReminderRecipientsTest extends TestCase
{
    use TaskFactory;

    public function testATaskForNobodyInParticularRemindsEveryoneAtHome(): void
    {
        [$leo, $robin, $gab] = $this->coloc();
        $gab->setAtHome(false, new \DateTimeImmutable());

        $reminder = (new ReminderRecipients())->for($this->task(), [$leo, $robin, $gab]);

        self::assertSame([$leo, $robin], $reminder->recipients);
        self::assertNull($reminder->standingInFor);
    }

    public function testAPetTaskRemindsItsHumansThenTheOthersWhenNoneOfThemIsHome(): void
    {
        [$leo, $robin, $gab] = $this->coloc();
        $task = $this->task();
        $pet = new Pet($task->getHousehold(), 'Tishka');
        $pet->addOwner($leo);
        $pet->addOwner($gab);
        $task->setPet($pet);

        $reminder = (new ReminderRecipients())->for($task, [$leo, $robin, $gab]);
        self::assertSame([$leo, $gab], $reminder->recipients);
        self::assertFalse($reminder->ownersAway());

        $leo->setAtHome(false, new \DateTimeImmutable());
        self::assertTrue((new ReminderRecipients())->for($task, [$leo, $robin, $gab])->isPersonalFor($gab));

        // None of its humans is home: whoever is.
        $gab->setAtHome(false, new \DateTimeImmutable());
        $reminder = (new ReminderRecipients())->for($task, [$leo, $robin, $gab]);
        self::assertSame([$robin], $reminder->recipients);
        self::assertTrue($reminder->ownersAway());
        self::assertSame([$leo, $gab], $reminder->owners);
    }

    public function testTheAssigneeIsRemindedWhenHome(): void
    {
        [$leo, $robin, $gab] = $this->coloc();
        $task = $this->task();
        $task->setAssignee($leo);
        $task->setBackup($robin);

        $reminder = (new ReminderRecipients())->for($task, [$leo, $robin, $gab]);

        self::assertTrue($reminder->isPersonalFor($leo));
        self::assertFalse($reminder->concerns($robin));
    }

    public function testTheBackupStandsInForAnAbsentAssignee(): void
    {
        [$leo, $robin, $gab] = $this->coloc();
        $leo->setAtHome(false, new \DateTimeImmutable());
        $task = $this->task();
        $task->setAssignee($leo);
        $task->setBackup($robin);

        $reminder = (new ReminderRecipients())->for($task, [$leo, $robin, $gab]);

        self::assertTrue($reminder->isPersonalFor($robin));
        self::assertSame($leo, $reminder->standingInFor);
    }

    public function testEveryoneAtHomeIsRemindedWhenTheBackupIsAwayToo(): void
    {
        [$leo, $robin, $gab] = $this->coloc();
        $leo->setAtHome(false, new \DateTimeImmutable());
        $robin->setAtHome(false, new \DateTimeImmutable());
        $task = $this->task();
        $task->setAssignee($leo);
        $task->setBackup($robin);

        $reminder = (new ReminderRecipients())->for($task, [$leo, $robin, $gab]);

        self::assertSame([$gab], $reminder->recipients);
        self::assertSame($leo, $reminder->standingInFor);
    }

    /** @return array{0: \App\Entity\Member, 1: \App\Entity\Member, 2: \App\Entity\Member} */
    private function coloc(): array
    {
        $household = $this->household();

        return [$this->member($household, 'Léo'), $this->member($household, 'Robin'), $this->member($household, 'Gab')];
    }
}
