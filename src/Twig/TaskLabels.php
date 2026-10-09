<?php

namespace App\Twig;

use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\TaskCategory;
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
            Urgency::Due => TaskKind::Occasional === $task->getKind()
                ? (null === $dueAt || $now->getTimestamp() - $dueAt->getTimestamp() < 7_200 ? 'Ça vient d’arriver' : 'Signalée '.$this->moment($dueAt))
                : (null !== $dueAt && $dueAt > $now && TaskKind::Rolling !== $task->getKind()
                ? 'Avant '.$this->moment($dueAt)
                : 'À faire aujourd’hui'),
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
                TaskKind::Occasional => 'Quand ça arrive',
            },
        };
    }

    /**
     * "Salon, Cuisine et WC", the pet's name, "Les animaux" or "Toute la coloc".
     *
     * @param bool $short on a card, where space is short: "Salon +2"
     */
    #[AsTwigFunction('task_place')]
    public function place(Task $task, bool $short = false): string
    {
        $rooms = array_values($task->getZones()->map(static fn (Zone $zone): string => $zone->getName())->toArray());

        return match (true) {
            null !== $task->getPet() => $task->getPet()->getName(),
            $short && \count($rooms) > 1 => \sprintf('%s +%d', $rooms[0], \count($rooms) - 1),
            [] !== $rooms => self::list($rooms),
            TaskCategory::Pets === $task->getCategory() => 'Les animaux',
            default => 'Toute la coloc',
        };
    }

    /** @param list<string> $words "a", "a et b", "a, b et c" */
    public static function list(array $words): string
    {
        $last = array_pop($words);

        return [] === $words ? (string) $last : implode(', ', $words).' et '.$last;
    }

    /** @param bool $withCommitment false on a card, which shows the week's progress instead */
    #[AsTwigFunction('task_rule_label')]
    public function ruleLabel(Task $task, bool $withCommitment = true): string
    {
        return match ($task->getKind()) {
            TaskKind::Rolling => \sprintf('tous les %d j', $task->getRhythmDays())
                .($withCommitment && null !== $task->getWeeklyCommitment() ? \sprintf(' · au moins %d×/sem', $task->getWeeklyCommitment()) : ''),
            TaskKind::Scheduled => $task->isDaily()
                ? \sprintf('tous les jours à %s', $this->time($task->getScheduledTime()))
                : \sprintf('chaque %s à %s', self::weekday((int) $task->getScheduledWeekday()), $this->time($task->getScheduledTime())),
            TaskKind::OneOff => 'ponctuelle',
            TaskKind::Quick => 'express',
            TaskKind::Occasional => 'quand ça arrive',
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

    /** "12 h", "2 j", "1 j 6 h": a number of hours as people say it. */
    #[AsTwigFunction('duration_label')]
    public function duration(int $hours): string
    {
        $days = intdiv($hours, 24);
        $rest = $hours % 24;

        return match (true) {
            0 === $days => \sprintf('%d h', $hours),
            0 === $rest => \sprintf('%d j', $days),
            default => \sprintf('%d j %d h', $days, $rest),
        };
    }

    /** The icon of a kind of task: regular, on a fixed day, express. */
    #[AsTwigFunction('task_kind_icon')]
    public function kindIcon(Task $task): string
    {
        return match ($task->getKind()) {
            TaskKind::Rolling => 'repeat',
            TaskKind::Scheduled => 'calendar',
            TaskKind::Quick => 'bolt',
            TaskKind::OneOff => 'check',
            TaskKind::Occasional => 'siren',
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
