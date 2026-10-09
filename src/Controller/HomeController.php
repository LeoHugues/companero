<?php

namespace App\Controller;

use App\Calendar\Week;
use App\Entity\Member;
use App\Enum\CasaMood;
use App\Enum\Urgency;
use App\Progress\LevelProvider;
use App\Progress\TeamProgressBuilder;
use App\Repository\CompletionRepository;
use App\Repository\PointEntryRepository;
use App\Task\TaskBoardBuilder;
use App\Task\TaskView;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class HomeController extends AbstractController
{
    private const UPCOMING_LIMIT = 3;

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function __invoke(
        #[CurrentUser] Member $member,
        TaskBoardBuilder $boards,
        TeamProgressBuilder $teamProgress,
        LevelProvider $levels,
        CompletionRepository $completions,
        PointEntryRepository $points,
        ClockInterface $clock,
    ): Response {
        $household = $member->getHousehold();
        $now = $clock->now();
        $board = $boards->build($household);
        $upcoming = \array_slice(array_values(array_filter($board->items, static fn (TaskView $view): bool => Urgency::Soon === $view->status->urgency)), 0, self::UPCOMING_LIMIT);
        $mine = $board->pressingFor($member);
        // Everything there is to do lives here: one-off tasks too, even when they are in no hurry.
        // What earned the household's points this week, for the "Objectif de la maison" to unfold.
        $week = Week::containing($now);
        $doneThisWeek = $completions->findForHousehold($household, $week->start, $week->end());
        $later = array_values(array_filter($board->oneOff(), static fn (TaskView $view): bool => Urgency::Fresh === $view->status->urgency));

        return $this->render('home/index.html.twig', [
            'board' => $board,
            'mine' => $mine,
            'pressing' => array_values(array_filter($board->pressing(), static fn (TaskView $view): bool => !\in_array($view, $mine, true))),
            'quick' => $board->quickShown(),
            'quick_count' => \count($board->quick()),
            'dormant' => $board->dormant(),
            'upcoming' => $upcoming,
            'later' => $later,
            'cleanliness' => $board->cleanliness(),
            'mood' => CasaMood::fromCleanliness($board->cleanliness()),
            'team' => $teamProgress->build($household, $week),
            'done_this_week' => ['completions' => $doneThisWeek, 'points' => $points->sumByCompletion($doneThisWeek)],
            'level' => $levels->levelOf($member),
            'is_cleaning_day' => $household->isCleaningDay($now),
            'now' => $now,
        ]);
    }
}
