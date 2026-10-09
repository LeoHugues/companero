<?php

namespace App\Review;

use App\Calendar\Week;
use App\Entity\Completion;
use App\Entity\EarnedTitle;
use App\Entity\Household;
use App\Entity\PointEntry;
use App\Enum\GiftKind;
use App\Enum\PointReason;
use App\Progress\MemberProgress;
use App\Progress\TeamProgressBuilder;
use App\Repository\CompletionRepository;
use App\Repository\EarnedTitleRepository;
use App\Repository\GiftRepository;
use App\Repository\PointEntryRepository;
use App\Reward\GiftGranter;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wraps up a finished week: hands out the titles and, if the household reached its
 * collective goal, the team bonus: XP, not points, which stay a measure of the work done.
 * Safe to run several times.
 */
final readonly class WeekCloser
{
    public const TEAM_XP = 50;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeamProgressBuilder $teamProgress,
        private CompletionRepository $completions,
        private EarnedTitleRepository $earnedTitles,
        private PointEntryRepository $points,
        private TitleAwarder $titleAwarder,
        private GiftRepository $gifts,
        private GiftGranter $giftGranter,
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
                // Titles are handed out once per week: so is the streak.
                $this->updateStreak($progress, $bonusAt);
            }

            if ($team->reached() && $progress->wasPresent() && !$this->points->hasEntry($member, PointReason::TeamXp, $week->start, $week->end())) {
                $this->entityManager->persist(new PointEntry($member, PointReason::TeamXp, self::TEAM_XP, \sprintf('Objectif collectif, semaine %d', $week->number()), $bonusAt));
            }
        }

        $household->countWeek($week->start, $team->reached());
        $this->entityManager->flush();

        foreach ($team->members as $progress) {
            $this->giftGranter->catchUp($progress->member, $bonusAt);
        }
    }

    /** Paused while away; a missed goal uses up a streak freeze if there is one, or ends the streak. */
    private function updateStreak(MemberProgress $progress, \DateTimeImmutable $at): void
    {
        $member = $progress->member;
        if (!$progress->wasPresent()) {
            return;
        }
        if ($progress->reached()) {
            $member->extendStreak();

            return;
        }

        $freeze = $this->gifts->findUnused($member, GiftKind::StreakFreeze)[0] ?? null;
        if (null !== $freeze && $member->getStreak() > 0) {
            $freeze->use($at);
        } else {
            $member->breakStreak();
        }
    }
}
