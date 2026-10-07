<?php

namespace App\Task;

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
     * How clean the shared home feels: the average freshness of shared recurring tasks.
     * Private zones (bedrooms, en-suite bathrooms) count for points, not for the Casa.
     */
    public function cleanliness(): int
    {
        $freshness = array_map(
            static fn (TaskView $view): int => $view->status->freshness,
            array_filter($this->items, static fn (TaskView $view): bool => $view->task->getKind()->isRecurring() && $view->task->isShared()),
        );

        return [] === $freshness ? 100 : (int) round(array_sum($freshness) / \count($freshness));
    }

    /** @return list<TaskView> */
    public function pressing(): array
    {
        return array_values(array_filter($this->items, static fn (TaskView $view): bool => $view->status->urgency->isPressing()));
    }

    /** @return list<TaskView> */
    public function recurring(?int $zoneId = null): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (TaskView $view): bool => $view->task->getKind()->isRecurring() && (null === $zoneId || $view->task->getZone()?->getId() === $zoneId),
        ));
    }

    /** @return list<TaskView> */
    public function oneOff(): array
    {
        return array_values(array_filter($this->items, static fn (TaskView $view): bool => !$view->task->getKind()->isRecurring()));
    }
}
