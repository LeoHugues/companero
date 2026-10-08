<?php

namespace App\Task;

use App\Entity\Zone;
use App\Enum\CasaMood;

/** How a room of the house is doing: one tile of the plan. */
final readonly class ZoneSummary
{
    /** @param list<TaskView> $tasks every active task of the zone, most pressing first */
    public function __construct(
        public Zone $zone,
        public array $tasks,
    ) {
    }

    /** Average freshness of the zone's planned tasks; a room without any is considered fine. */
    public function cleanliness(): int
    {
        $freshness = array_map(
            static fn (TaskView $view): int => $view->status->freshness,
            array_filter($this->tasks, static fn (TaskView $view): bool => $view->task->getKind()->isPlanned()),
        );

        return [] === $freshness ? 100 : (int) round(array_sum($freshness) / \count($freshness));
    }

    public function mood(): CasaMood
    {
        return CasaMood::fromCleanliness($this->cleanliness());
    }

    public function pressingCount(): int
    {
        return \count(array_filter($this->tasks, static fn (TaskView $view): bool => $view->status->urgency->isPressing()));
    }
}
