<?php

namespace App\Review;

use App\Calendar\Week;
use App\Entity\Completion;
use App\Entity\EarnedTitle;
use App\Progress\TeamProgress;

final readonly class WeeklyReview
{
    /**
     * @param list<CommitmentResult>                                              $commitments
     * @param list<array{day: \DateTimeImmutable, count: int, cleaningDay: bool}> $activity
     * @param list<EarnedTitle>                                                   $titles
     * @param list<Completion>                                                    $completions
     */
    public function __construct(
        public Week $week,
        public bool $current,
        public TeamProgress $team,
        public array $commitments,
        public array $activity,
        public array $titles,
        public array $completions,
    ) {
    }

    public function keptCommitments(): int
    {
        return \count(array_filter($this->commitments, static fn (CommitmentResult $c): bool => $c->kept()));
    }

    public function busiestDayCount(): int
    {
        return max([1, ...array_column($this->activity, 'count')]);
    }
}
