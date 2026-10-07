<?php

namespace App\Task;

use App\Enum\PointReason;

final readonly class Bonus
{
    public function __construct(
        public PointReason $reason,
        public int $points,
    ) {
    }
}
