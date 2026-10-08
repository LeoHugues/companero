<?php

namespace App\Tests\Unit;

use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\TaskKind;

/** Builds in-memory entities for unit tests. */
trait TaskFactory
{
    private function household(): Household
    {
        return new Household('La coloc', new \DateTimeImmutable('2026-01-01'));
    }

    private function member(?Household $household = null, string $name = 'Léo'): Member
    {
        return new Member($household ?? $this->household(), $name, strtolower($name).'@example.com', '#E8692C', new \DateTimeImmutable('2026-01-01'));
    }

    private function rollingTask(int $rhythmDays, ?string $lastCompletedAt, ?int $weeklyCommitment = null, int $points = 30, ?Zone $zone = null): Task
    {
        $task = $this->task(TaskKind::Rolling, $points, $zone);
        $task->setRhythmDays($rhythmDays);
        $task->setWeeklyCommitment($weeklyCommitment);
        if (null !== $lastCompletedAt) {
            $task->complete(new \DateTimeImmutable($lastCompletedAt));
        }

        return $task;
    }

    private function task(TaskKind $kind = TaskKind::OneOff, int $points = 30, ?Zone $zone = null, string $createdAt = '2026-01-01'): Task
    {
        $member = $this->member();
        $task = new Task($member->getHousehold(), $member, new \DateTimeImmutable($createdAt));
        $task->setTitle('Serpillière');
        $task->setKind($kind);
        $task->setPoints($points);
        $task->setZone($zone);

        return $task;
    }
}
