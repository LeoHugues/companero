<?php

namespace App\Task;

use App\Entity\Task;
use App\Enum\PointReason;
use App\Enum\Urgency;

/**
 * Doing a task right on time is rewarded; rescuing a forgotten one too, but never more
 * than being on time after a day or two of delay — waiting must not pay off.
 */
final class BonusPolicy
{
    public const BIG_TASK_POINTS = 4;
    public const MAX_RESCUE_BONUS = 3;

    public function bonusFor(Task $task, TaskStatus $status): ?Bonus
    {
        return match ($status->urgency) {
            Urgency::Due => new Bonus(PointReason::Punctuality, $task->getPoints() >= self::BIG_TASK_POINTS ? 2 : 1),
            Urgency::Late => $this->rescue($status->overdueDays),
            default => null,
        };
    }

    private function rescue(int $overdueDays): ?Bonus
    {
        $points = min(self::MAX_RESCUE_BONUS, intdiv($overdueDays, 2));

        return $points > 0 ? new Bonus(PointReason::Rescue, $points) : null;
    }
}
