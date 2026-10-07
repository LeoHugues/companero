<?php

namespace App\Controller;

use App\Calendar\Week;
use App\Entity\Member;
use App\Enum\CasaMood;
use App\Enum\Urgency;
use App\Progress\LevelProvider;
use App\Progress\TeamProgressBuilder;
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
        ClockInterface $clock,
    ): Response {
        $household = $member->getHousehold();
        $now = $clock->now();
        $board = $boards->build($household);
        $upcoming = array_filter($board->items, static fn (TaskView $view): bool => Urgency::Soon === $view->status->urgency);

        return $this->render('home/index.html.twig', [
            'board' => $board,
            'pressing' => $board->pressing(),
            'upcoming' => \array_slice(array_values($upcoming), 0, self::UPCOMING_LIMIT),
            'cleanliness' => $board->cleanliness(),
            'mood' => CasaMood::fromCleanliness($board->cleanliness()),
            'team' => $teamProgress->build($household, Week::containing($now)),
            'level' => $levels->levelOf($member),
            'is_cleaning_day' => $household->isCleaningDay($now),
            'now' => $now,
        ]);
    }
}
