<?php

namespace App\Task;

use App\Entity\Member;
use App\Entity\Zone;
use App\Enum\TaskKind;
use App\Enum\Urgency;

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
            $summaries[] = new ZoneSummary($zone, array_values(array_filter($this->items, static fn (TaskView $view): bool => $view->task->isIn($zone))));
        }
        usort($summaries, static fn (ZoneSummary $a, ZoneSummary $b): int => $a->zone->isPrivate() <=> $b->zone->isPrivate());

        return $summaries;
    }

    /** @return list<TaskView> pressing tasks someone counts on this member for: theirs, or one they stand in for */
    public function pressingFor(Member $member): array
    {
        return array_values(array_filter(
            $this->pressing(),
            static fn (TaskView $view): bool => null !== $view->reminder
                && (null !== $view->task->getAssignee() ? $view->reminder->isPersonalFor($member) : [] !== $view->reminder->owners && $view->reminder->concerns($member)),
        ));
    }

    /** @return list<TaskView> the member's hand on the home page: what they took, and what is counted on them */
    public function inHandOf(Member $member): array
    {
        $counted = $this->pressingFor($member);

        return array_values(array_filter($this->items, static fn (TaskView $view): bool => $view->isReservedBy($member) || \in_array($view, $counted, true)));
    }

    /**
     * @return list<TaskView> what the Casa asks for, at most $limit: free tasks whose moment approaches or has come,
     *                        a hidden surprise first — never an express task, nor one resting after it was done
     */
    public function quests(int $limit = 4): array
    {
        $quests = array_values(array_filter($this->items, static fn (TaskView $view): bool => $view->isFree()
            && null === $view->availableAt
            && TaskKind::Quick !== $view->task->getKind()
            && (Urgency::Fresh !== $view->status->urgency || null !== $view->bounty)));
        usort($quests, static fn (TaskView $a, TaskView $b): int => (null === $a->bounty) <=> (null === $b->bounty));

        return \array_slice($quests, 0, $limit);
    }

    /**
     * The card "Piocher" deals, one at a time: the most pressing free task — late first, then the ones whose moment
     * has come —, a hidden surprise first among those as pressing; null once none is left.
     */
    public function draw(): ?TaskView
    {
        $drawn = null;
        foreach ($this->toCareFor() as $view) {
            if (!$view->isFree() || null !== $view->availableAt) {
                continue;
            }
            if (null !== $drawn && $view->status->urgency !== $drawn->status->urgency) {
                break;
            }
            if (null !== $view->bounty) {
                return $view;
            }
            $drawn ??= $view;
        }

        return $drawn;
    }

    /** @return list<TaskView> what "Prendre soin de la Casa" lists: everything but the express tasks and the occasional ones still asleep */
    public function toCareFor(): array
    {
        return array_values(array_filter($this->items, static fn (TaskView $view): bool => TaskKind::Quick !== $view->task->getKind()
            && (TaskKind::Occasional !== $view->task->getKind() || $view->task->isRaised())));
    }

    /** @return list<TaskView> */
    public function recurring(?int $zoneId = null): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (TaskView $view): bool => $view->task->getKind()->isPlanned() && (null === $zoneId || $view->task->getZones()->exists(static fn (int $key, Zone $zone): bool => $zone->getId() === $zoneId)),
        ));
    }

    /** @return list<TaskView> the tasks reported in one tap, in the order chosen for the home page (then by title) */
    public function quick(): array
    {
        $quick = array_values(array_filter($this->items, static fn (TaskView $view): bool => TaskKind::Quick === $view->task->getKind()));
        usort($quick, static fn (TaskView $a, TaskView $b): int => [$a->task->getQuickPosition() ?? \PHP_INT_MAX, $a->task->getTitle()] <=> [$b->task->getQuickPosition() ?? \PHP_INT_MAX, $b->task->getTitle()]);

        return $quick;
    }

    /** @return list<TaskView> the express tasks shown in "En un geste" on the home page */
    public function quickShown(): array
    {
        return array_values(array_filter($this->quick(), static fn (TaskView $view): bool => !$view->task->isQuickHidden()));
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
            static fn (TaskView $view): bool => TaskKind::Occasional === $view->task->getKind() && (null === $zoneId || $view->task->getZones()->exists(static fn (int $key, Zone $zone): bool => $zone->getId() === $zoneId)),
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
