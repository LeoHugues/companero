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
        $mine = $board->inHandOf($member);
        // What earned the household's points this week, for the "Objectif de la maison" to unfold.
        $week = Week::containing($now);
        $doneThisWeek = $completions->findForHousehold($household, $week->start, $week->end());
        // "Prendre soin de la Casa": everything there is to do, by urgency; the fresh ones fold away under "Plus tard".
        $care = $board->toCareFor();
        $groups = [];
        foreach ([Urgency::Late, Urgency::Due, Urgency::Soon, Urgency::Fresh] as $urgency) {
            $groups[$urgency->value] = array_values(array_filter($care, static fn (TaskView $view): bool => $urgency === $view->status->urgency));
        }
        // Its filters: "Pour moi" (what I took, what I am in charge of or counted on for), "Libres", "Tout".
        $forMe = array_map(
            static fn (TaskView $view): int => (int) $view->task->getId(),
            array_values(array_filter($care, static fn (TaskView $view): bool => \in_array($view, $mine, true) || $view->task->getAssignee() === $member)),
        );

        return $this->render('home/index.html.twig', [
            'mine' => $mine,
            'quests' => $board->quests(),
            'draw' => $board->draw(),
            'groups' => $groups,
            'for_me' => $forMe,
            'care_counts' => [
                'mine' => \count($forMe),
                'free' => \count(array_filter($care, static fn (TaskView $view): bool => $view->isFree())),
                'all' => \count($care),
            ],
            'quick' => $board->quickShown(),
            'quick_count' => \count($board->quick()),
            'dormant' => $board->dormant(),
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
