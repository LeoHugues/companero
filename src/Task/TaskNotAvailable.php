<?php

namespace App\Task;

/** The task was just done: it cannot be done again before its cooldown is over. */
final class TaskNotAvailable extends \RuntimeException
{
    public function __construct(
        public readonly \DateTimeImmutable $availableAt,
    ) {
        parent::__construct(\sprintf('Not available before %s.', $availableAt->format(\DATE_ATOM)));
    }
}
