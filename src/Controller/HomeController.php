<?php

namespace App\Controller;

use App\Calendar\Week;
use App\Entity\Member;
use App\Enum\CasaMood;
use App\Ledger\LedgerBook;
use App\Notification\NoticeBoard;
use App\Progress\LevelProvider;
use App\Progress\TeamProgressBuilder;
use App\Repository\CompletionRepository;
use App\Repository\PointEntryRepository;
use App\Repository\ShoppingItemRepository;
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
        ShoppingItemRepository $shopping,
        LedgerBook $ledgers,
        ClockInterface $clock,
    ): Response {
        $household = $member->getHousehold();
        $now = $clock->now();
        $board = $boards->build($household);
        $mine = $board->inHandOf($member);
        // What earned the household's points this week, for the "Objectif de la maison" to unfold.
        $week = Week::containing($now);
        $doneThisWeek = $completions->findForHousehold($household, $week->start, $week->end());
        // Where I stand in the accounts I am in.
        $myLedgers = $ledgers->forMember($member);
        // The week's review is ready on Sunday evening, and still on Monday for the week just closed — if the household
        // already lived in it: a coloc created on a Monday has no review of the week before.
        $reviewWeek = '1' === $now->format('N') ? $week->previous() : $week;
        $reviewReady = ('7' === $now->format('N') && (int) $now->format('G') >= NoticeBoard::REVIEW_HOUR || '1' === $now->format('N'))
            && $household->getCreatedAt() < $reviewWeek->end();

        return $this->render('home/index.html.twig', [
            'mine' => $mine,
            // The cards of the hand counted on this member whoever takes them: let go, they stay in it.
            'counted_on' => array_map(static fn (TaskView $view): int => (int) $view->task->getId(), $board->pressingFor($member)),
            'quests' => $board->quests(),
            'draw' => $board->draw(),
            // Everything there is to do is in the "Tâches" tab: here, how much.
            'todo_count' => \count($board->toCareFor()),
            'quick' => $board->quickShown(),
            'quick_count' => \count($board->quick()),
            'missing' => $shopping->findMissing($household),
            'ledgers' => $myLedgers,
            'standing' => $ledgers->standing($member, $myLedgers),
            'cleanliness' => $board->cleanliness(),
            'mood' => CasaMood::fromCleanliness($board->cleanliness()),
            'team' => $teamProgress->build($household, $week),
            'review' => ['week' => $reviewReady ? $reviewWeek : $week, 'ready' => $reviewReady],
            'done_this_week' => ['completions' => $doneThisWeek, 'points' => $points->sumByCompletion($doneThisWeek)],
            'level' => $levels->levelOf($member),
            'is_cleaning_day' => $household->isCleaningDay($now),
            'now' => $now,
        ]);
    }
}
