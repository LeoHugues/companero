<?php

namespace App\Notification;

use App\Calendar\Week;
use App\Entity\Member;
use App\Enum\TaskCategory;
use App\Enum\TaskKind;
use App\Progress\TeamProgressBuilder;
use App\Repository\YellowCardRepository;
use App\Task\TaskBoardBuilder;
use App\Task\TaskView;
use App\Twig\TaskLabels;
use Psr\Clock\ClockInterface;

/**
 * What the phone should tell a member right now, according to their settings (Profil › Rappels):
 * the tasks someone counts on them for, the occasional ones just raised, the cleaning day in the
 * morning, the week's review on Sunday evening — and a yellow card received, always.
 * The app asks every quarter of an hour (android/…/Notifier.kt) and shows each one once.
 */
final readonly class NoticeBoard
{
    /** The cleaning day's list, from 9 a.m. */
    public const CLEANING_DAY_HOUR = 9;
    /** The week's review, on Sunday from 8 p.m. */
    public const REVIEW_HOUR = 20;

    public function __construct(
        private TaskBoardBuilder $boards,
        private TaskLabels $labels,
        private YellowCardRepository $cards,
        private TeamProgressBuilder $teamProgress,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<Notice> */
    public function for(Member $member): array
    {
        $now = $this->clock->now();
        $today = $now->format('Y-m-d');
        $household = $member->getHousehold();
        $board = $this->boards->build($household);
        $notices = [];

        foreach ($this->cards->findUnseenBy($member) as $card) {
            $notices[] = new Notice('carton-'.$card->getId(), 'Carton jaune de '.$card->getGivenBy()->getName(), '« '.$card->getReason().' »', '/');
        }

        if ($member->isNotifyOverdue()) {
            foreach ($board->pressing() as $view) {
                $notice = $this->taskNotice($view, $member, $today);
                if (null !== $notice) {
                    $notices[] = $notice;
                }
            }
        }

        if ($member->isNotifyCleaningDay() && $household->isCleaningDay($now) && (int) $now->format('G') >= self::CLEANING_DAY_HOUR) {
            $count = \count($board->pressing());
            $notices[] = new Notice('menage-'.$today, 'C’est le jour de ménage', 0 === $count
                ? 'Rien d’urgent : la Casa est contente. Une express en passant ?'
                : \sprintf('%d tâche%s attend%s la Casa. Les points comptent plus aujourd’hui !', $count, $count > 1 ? 's' : '', $count > 1 ? 'ent' : ''));
        }

        if ($member->isNotifyWeeklyReview() && 7 === (int) $now->format('N') && (int) $now->format('G') >= self::REVIEW_HOUR) {
            $week = Week::containing($now);
            $team = $this->teamProgress->build($household, $week);
            $notices[] = new Notice('bilan-'.$week->start->format('Y-m-d'), 'Le bilan de la semaine', $team->reached()
                ? \sprintf('Objectif atteint : %d pts sur %d. Bravo la coloc !', $team->points(), $team->goal())
                : \sprintf('%d pts sur %d cette semaine. Qui a gagné les titres ?', $team->points(), $team->goal()), '/bilan');
        }

        return $notices;
    }

    /** A test, on demand from the profile: it says what would come right now. */
    public function test(Member $member): Notice
    {
        $count = \count($this->for($member));

        return new Notice('test-'.$this->clock->now()->format('YmdHis'), 'Companero', 0 === $count
            ? 'Les notifications marchent ! Rien à te rappeler pour l’instant.'
            : \sprintf('Les notifications marchent ! %d rappel%s pour toi en ce moment.', $count, $count > 1 ? 's' : ''), '/profil');
    }

    /** A pressing task: someone counts on this member, or it was just raised and they are home. */
    private function taskNotice(TaskView $view, Member $member, string $today): ?Notice
    {
        $task = $view->task;
        $reminder = $view->reminder;
        if (null === $reminder || !$reminder->concerns($member) || null !== $view->reservedBy && $view->reservedBy !== $member) {
            return null;
        }
        $status = $this->labels->statusLabel($task, $view->status);
        $path = '/taches/'.$task->getId();

        if (null !== $task->getAssignee()) {
            return new Notice('tache-'.$task->getId().'-'.$today, $task->getTitle(), null !== $reminder->standingInFor
                ? \sprintf('%s · %s n’est pas là : on compte sur toi.', $status, $reminder->standingInFor->getName())
                : $status.' · C’est toi qui t’en charges.', $path);
        }
        if ([] !== $reminder->owners) {
            $names = implode(' et ', array_map(static fn (Member $owner): string => $owner->getName(), $reminder->owners));

            return new Notice('tache-'.$task->getId().'-'.$today, $task->getTitle(), $reminder->ownersAway()
                ? \sprintf('%s · %s %s pas là : on compte sur toi.', $status, $names, \count($reminder->owners) > 1 ? 'ne sont' : 'n’est')
                : $status.' · Ton animal compte sur toi.', $path);
        }
        if (TaskKind::Occasional === $task->getKind() && null !== $task->getRaisedAt()) {
            return new Notice('arrive-'.$task->getId().'-'.$task->getRaisedAt()->format('YmdHi'), 'Ça arrive : '.$task->getTitle(), 'Sa carte attend sur l’accueil : qui s’en occupe ?', $path);
        }
        if (TaskCategory::Pets === $task->getCategory()) {
            return new Notice('tache-'.$task->getId().'-'.$today, $task->getTitle(), $status.' · Les animaux comptent sur toi.', $path);
        }

        return null;
    }
}
