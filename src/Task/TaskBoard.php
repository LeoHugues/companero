<?php

namespace App\Task;

use App\Entity\Member;
use App\Entity\Zone;
use App\Enum\TaskKind;

/** Every active task of a household with its current status, most pressing first. */
final readonly class TaskBoard
{
    /** @var list<TaskView> */
    public array $items;

    /** @param list<TaskView> $items */
    public function __construct(array $items)
    {
        usort($items, static fn (TaskView $a, TaskView $b): int => [$b->status->urgency->weight(), $a->status->freshness, $a->task->getTitle()]
            <=> [$a->status->urgency->weight(), $b->status->freshness, $b->task->getTitle()]);

        $this->items = $items;
    }

    /**
     * How clean the shared home feels: how well its shared tasks are looked after (TaskStatus::care()),
     * on average — the ones that come back on their own, and the occasional ones signalled.
     * Private zones (bedrooms, en-suite bathrooms) count for points, not for the Casa.
     */
    public function cleanliness(): int
    {
        $freshness = array_map(
            static fn (TaskView $view): int => $view->status->care(),
            array_filter($this->items, static fn (TaskView $view): bool => $view->task->isExpected() && $view->task->isShared()),
        );

        return [] === $freshness ? 100 : (int) round(array_sum($freshness) / \count($freshness));
    }

    /** @return list<TaskView> */
    public function pressing(): array
    {
        return array_values(array_filter($this->items, static fn (TaskView $view): bool => $view->status->urgency->isPressing()));
    }

    /**
     * @param iterable<Zone> $zones
     *
     * @return list<ZoneSummary> shared rooms first, then private ones
     */
    public function byZone(iterable $zones): array
    {
        $summaries = [];
        foreach ($zones as $zone) {
            $summaries[] = new ZoneSummary($zone, array_values(array_filter($this->items, static fn (TaskView $view): bool => $view->task->getZone() === $zone)));
        }
        usort($summaries, static fn (ZoneSummary $a, ZoneSummary $b): int => $a->zone->isPrivate() <=> $b->zone->isPrivate());

        return $summaries;
    }

    /** @return list<TaskView> pressing tasks someone counts on this member for: theirs, or one they stand in for */
    public function pressingFor(Member $member): array
    {
        return array_values(array_filter(
            $this->pressing(),
            static fn (TaskView $view): bool => null !== $view->task->getAssignee() && true === $view->reminder?->isPersonalFor($member),
        ));
    }

    /** @return list<TaskView> */
    public function recurring(?int $zoneId = null): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (TaskView $view): bool => $view->task->getKind()->isPlanned() && (null === $zoneId || $view->task->getZone()?->getId() === $zoneId),
        ));
    }

    /** @return list<TaskView> the tasks reported in one tap, by title */
    public function quick(): array
    {
        $quick = array_values(array_filter($this->items, static fn (TaskView $view): bool => TaskKind::Quick === $view->task->getKind()));
        usort($quick, static fn (TaskView $a, TaskView $b): int => $a->task->getTitle() <=> $b->task->getTitle());

        return $quick;
    }

    /** @return list<TaskView> occasional tasks asleep, ready to be raised when the need arises, by title */
    public function dormant(): array
    {
        $dormant = array_values(array_filter($this->items, static fn (TaskView $view): bool => TaskKind::Occasional === $view->task->getKind() && !$view->task->isRaised()));
        usort($dormant, static fn (TaskView $a, TaskView $b): int => $a->task->getTitle() <=> $b->task->getTitle());

        return $dormant;
    }

    /** @return list<TaskView> every occasional task, raised or not, by title */
    public function occasional(?int $zoneId = null): array
    {
        $occasional = array_values(array_filter(
            $this->items,
            static fn (TaskView $view): bool => TaskKind::Occasional === $view->task->getKind() && (null === $zoneId || $view->task->getZone()?->getId() === $zoneId),
        ));
        usort($occasional, static fn (TaskView $a, TaskView $b): int => $a->task->getTitle() <=> $b->task->getTitle());

        return $occasional;
    }

    /** @return list<TaskView> */
    public function oneOff(): array
    {
        return array_values(array_filter($this->items, static fn (TaskView $view): bool => TaskKind::OneOff === $view->task->getKind()));
    }
}
