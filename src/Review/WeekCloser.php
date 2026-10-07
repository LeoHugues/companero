<?php

namespace App\Review;

use App\Calendar\Week;
use App\Entity\Completion;
use App\Entity\EarnedTitle;
use App\Entity\Household;
use App\Entity\PointEntry;
use App\Enum\PointReason;
use App\Progress\TeamProgressBuilder;
use App\Repository\CompletionRepository;
use App\Repository\EarnedTitleRepository;
use App\Repository\PointEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wraps up a finished week: hands out the titles and, if the household reached its
 * collective goal, the team bonus. Safe to run several times.
 */
final readonly class WeekCloser
{
    public const TEAM_BONUS = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeamProgressBuilder $teamProgress,
        private CompletionRepository $completions,
        private EarnedTitleRepository $earnedTitles,
        private PointEntryRepository $points,
        private TitleAwarder $titleAwarder,
    ) {
    }

    public function close(Household $household, Week $week): void
    {
        $team = $this->teamProgress->build($household, $week);
        $completions = $this->completions->findForHousehold($household, $week->start, $week->end());
        $alreadyTitled = array_map(static fn (EarnedTitle $title) => $title->getMember(), $this->earnedTitles->findForWeek($household, $week->start));
        $bonusAt = $week->end()->modify('-1 second');

        foreach ($team->members as $progress) {
            $member = $progress->member;

            if (!\in_array($member, $alreadyTitled, true)) {
                $title = $this->titleAwarder->award(
                    $progress,
                    array_values(array_filter($completions, static fn (Completion $c): bool => $c->getMember() === $member)),
                    $household->getCleaningDay(),
                );
                $this->entityManager->persist(new EarnedTitle($member, $week->start, $title->name, $title->reason));
            }

            if ($team->reached() && $progress->wasPresent() && !$this->points->hasEntry($member, PointReason::TeamBonus, $week->start, $week->end())) {
                $this->entityManager->persist(new PointEntry($member, PointReason::TeamBonus, self::TEAM_BONUS, \sprintf('Objectif collectif, semaine %d', $week->number()), $bonusAt));
            }
        }

        $this->entityManager->flush();
    }
}
