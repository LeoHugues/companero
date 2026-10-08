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
    public const BIG_TASK_POINTS = 40;
    public const SMALL_PUNCTUALITY_BONUS = 10;
    public const BIG_PUNCTUALITY_BONUS = 20;
    /** Earned every two days of delay, up to MAX_RESCUE_BONUS. */
    public const RESCUE_STEP = 10;
    public const MAX_RESCUE_BONUS = 30;

    public function bonusFor(Task $task, TaskStatus $status): ?Bonus
    {
        return match ($status->urgency) {
            Urgency::Due => new Bonus(PointReason::Punctuality, $task->getPoints() >= self::BIG_TASK_POINTS ? self::BIG_PUNCTUALITY_BONUS : self::SMALL_PUNCTUALITY_BONUS),
            Urgency::Late => $this->rescue($status->overdueDays),
            default => null,
        };
    }

    private function rescue(int $overdueDays): ?Bonus
    {
        $points = min(self::MAX_RESCUE_BONUS, self::RESCUE_STEP * intdiv($overdueDays, 2));

        return $points > 0 ? new Bonus(PointReason::Rescue, $points) : null;
    }
}
