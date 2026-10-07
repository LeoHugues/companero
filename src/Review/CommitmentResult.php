<?php

namespace App\Review;

use App\Entity\Task;

final readonly class CommitmentResult
{
    public function __construct(
        public Task $task,
        public int $done,
        public int $expected,
    ) {
    }

    public function kept(): bool
    {
        return $this->done >= $this->expected;
    }
}
