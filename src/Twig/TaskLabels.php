<?php

namespace App\Twig;

use App\Entity\Task;
use App\Enum\TaskKind;
use App\Enum\Urgency;
use App\Task\TaskStatus;
use Psr\Clock\ClockInterface;
use Twig\Attribute\AsTwigFunction;

/** Human, French wording for task statuses and schedules. */
final readonly class TaskLabels
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    #[AsTwigFunction('task_status_label')]
    public function statusLabel(Task $task, TaskStatus $status): string
    {
        $now = $this->clock->now();
        $dueAt = $status->dueAt;

        return match ($status->urgency) {
            Urgency::Late => $status->overdueDays > 0 ? \sprintf('En retard de %d j', $status->overdueDays) : 'En retard',
            Urgency::Due => null !== $dueAt && $dueAt > $now && TaskKind::Rolling !== $task->getKind()
                ? 'Avant '.$this->moment($dueAt)
                : 'À faire aujourd’hui',
            Urgency::Soon => match (true) {
                $status->hasPendingCommitment() => 'Cette semaine',
                TaskKind::Rolling === $task->getKind() && null !== $dueAt => $this->inDays($dueAt),
                null !== $dueAt => ucfirst($this->moment($dueAt)),
                default => 'Bientôt',
            },
            Urgency::Fresh => match ($task->getKind()) {
                TaskKind::Rolling => $status->freshness >= 75 || null === $dueAt ? 'Tout propre' : $this->inDays($dueAt),
                TaskKind::Scheduled => null !== $dueAt ? ucfirst($this->moment($dueAt)) : 'Plus tard',
                TaskKind::OneOff => null !== $dueAt ? 'Pour '.$this->moment($dueAt) : 'Pas pressé',
                TaskKind::Quick => 'Quand il faut',
            },
        };
    }

    #[AsTwigFunction('task_rule_label')]
    public function ruleLabel(Task $task): string
    {
        return match ($task->getKind()) {
            TaskKind::Rolling => \sprintf('tous les %d j', $task->getRhythmDays())
                .(null !== $task->getWeeklyCommitment() ? \sprintf(' · au moins %d×/sem', $task->getWeeklyCommitment()) : ''),
            TaskKind::Scheduled => $task->isDaily()
                ? \sprintf('tous les jours à %s', $this->time($task->getScheduledTime()))
                : \sprintf('chaque %s à %s', self::weekday((int) $task->getScheduledWeekday()), $this->time($task->getScheduledTime())),
            TaskKind::OneOff => 'ponctuelle',
            TaskKind::Quick => 'express',
        };
    }

    /** "à 15 h 30", "demain à 9 h", "jeudi à 9 h", "le 14/10": when a task in cooldown can be done again. */
    #[AsTwigFunction('available_again')]
    public function availableAgain(\DateTimeImmutable $at): string
    {
        $days = (int) $this->clock->now()->setTime(0, 0)->diff($at->setTime(0, 0))->format('%r%a');
        $time = $this->time($at);

        return match (true) {
            $days <= 0 => 'à '.$time,
            1 === $days => 'demain à '.$time,
            $days < 7 => self::weekday((int) $at->format('N')).' à '.$time,
            default => 'le '.$at->format('d/m'),
        };
    }

    #[AsTwigFunction('weekday_name')]
    public static function weekday(int $isoDay): string
    {
        return ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'][($isoDay - 1) % 7];
    }

    /** "aujourd'hui 20 h", "demain 20 h", "mardi 20 h", "hier 20 h", "le 14/10". */
    #[AsTwigFunction('task_moment')]
    public function moment(\DateTimeImmutable $at): string
    {
        $today = $this->clock->now()->setTime(0, 0);
        $days = (int) $today->diff($at->setTime(0, 0))->format('%r%a');
        $time = '00:00' === $at->format('H:i') ? '' : ' '.$this->time($at);

        return match (true) {
            0 === $days => 'aujourd’hui'.$time,
            1 === $days => 'demain'.$time,
            -1 === $days => 'hier'.$time,
            $days > 1 && $days < 7 => self::weekday((int) $at->format('N')).$time,
            default => 'le '.$at->format('d/m'),
        };
    }

    private function inDays(\DateTimeImmutable $at): string
    {
        $days = (int) $this->clock->now()->setTime(0, 0)->diff($at->setTime(0, 0))->format('%r%a');

        return match (true) {
            $days <= 0 => 'Aujourd’hui',
            1 === $days => 'Demain',
            default => \sprintf('Dans %d j', $days),
        };
    }

    private function time(?\DateTimeImmutable $time): string
    {
        if (null === $time) {
            return '';
        }

        return '00' === $time->format('i') ? $time->format('G').' h' : $time->format('G \h i');
    }
}
