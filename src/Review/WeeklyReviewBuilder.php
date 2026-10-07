<?php

namespace App\Review;

use App\Calendar\Week;
use App\Entity\Completion;
use App\Entity\Household;
use App\Entity\Task;
use App\Progress\TeamProgressBuilder;
use App\Repository\CompletionRepository;
use App\Repository\EarnedTitleRepository;
use App\Repository\TaskRepository;
use Psr\Clock\ClockInterface;

final readonly class WeeklyReviewBuilder
{
    public function __construct(
        private TeamProgressBuilder $teamProgress,
        private CompletionRepository $completions,
        private TaskRepository $tasks,
        private EarnedTitleRepository $earnedTitles,
        private ClockInterface $clock,
    ) {
    }

    public function build(Household $household, Week $week): WeeklyReview
    {
        $completions = $this->completions->findForHousehold($household, $week->start, $week->end());
        $counts = array_count_values(array_map(static fn (Completion $c): int => (int) $c->getTask()->getId(), $completions));

        return new WeeklyReview(
            week: $week,
            current: $week->contains($this->clock->now()),
            team: $this->teamProgress->build($household, $week),
            commitments: array_map(
                static fn (Task $task): CommitmentResult => new CommitmentResult($task, $counts[$task->getId()] ?? 0, (int) $task->getWeeklyCommitment()),
                $this->tasks->findWithWeeklyCommitment($household),
            ),
            activity: array_map(
                static fn (\DateTimeImmutable $day): array => [
                    'day' => $day,
                    'count' => \count(array_filter($completions, static fn (Completion $c): bool => $c->getCompletedAt()->format('Y-m-d') === $day->format('Y-m-d'))),
                    'cleaningDay' => $household->isCleaningDay($day),
                ],
                $week->days(),
            ),
            titles: $this->earnedTitles->findForWeek($household, $week->start),
            completions: $completions,
        );
    }
}
