<?php

namespace App\History;

use App\Entity\Completion;
use App\Entity\Member;
use App\Entity\Zone;
use App\Enum\TaskCategory;

/** Everything done over a period, by one member or by the whole household, day by day. */
final readonly class History
{
    /**
     * @param list<Completion>         $completions latest first
     * @param array<int, int>          $points      points earned for each completion, by completion id
     * @param list<\DateTimeImmutable> $days        every day of the period, oldest first
     * @param list<Member>             $members     the household's members (for the household view)
     */
    public function __construct(
        public ?Member $member,
        public int $days,
        public array $completions,
        private array $points,
        public array $period,
        public array $members = [],
    ) {
    }

    public function pointsOf(Completion $completion): int
    {
        return $this->points[$completion->getId()] ?? 0;
    }

    public function totalPoints(): int
    {
        return array_sum(array_map($this->pointsOf(...), $this->completions));
    }

    /** @return list<array{day: \DateTimeImmutable, completions: list<Completion>, points: int}> days with something done, latest first */
    public function byDay(): array
    {
        $days = [];
        foreach ($this->completions as $completion) {
            $key = $completion->getCompletedAt()->format('Y-m-d');
            $days[$key] ??= ['day' => $completion->getCompletedAt()->setTime(0, 0), 'completions' => [], 'points' => 0];
            $days[$key]['completions'][] = $completion;
            $days[$key]['points'] += $this->pointsOf($completion);
        }

        return array_values($days);
    }

    /** @return list<array{day: \DateTimeImmutable, points: int}> every day of the period, oldest first, for the chart */
    public function daily(): array
    {
        $byDay = array_column(array_map(static fn (array $d): array => [$d['day']->format('Y-m-d'), $d['points']], $this->byDay()), 1, 0);

        return array_map(static fn (\DateTimeImmutable $day): array => ['day' => $day, 'points' => $byDay[$day->format('Y-m-d')] ?? 0], $this->period);
    }

    public function bestDayPoints(): int
    {
        return max([1, ...array_column($this->daily(), 'points')]);
    }

    /** @return list<array{member: Member, count: int, points: int}> each member's share, most points first */
    public function byMember(): array
    {
        $shares = [];
        foreach ($this->members as $member) {
            $theirs = array_filter($this->completions, static fn (Completion $c): bool => $c->getMember() === $member);
            $shares[] = ['member' => $member, 'count' => \count($theirs), 'points' => array_sum(array_map($this->pointsOf(...), $theirs))];
        }
        usort($shares, static fn (array $a, array $b): int => $b['points'] <=> $a['points']);

        return $shares;
    }

    /** @return list<array{name: string, count: int}> where the work was done, busiest first */
    public function byZone(int $limit = 4): array
    {
        // A task in several rooms counts for each of them.
        $counts = array_count_values(array_merge([], ...array_map(
            static fn (Completion $c): array => [] !== ($rooms = $c->getTask()->getZones()->map(static fn (Zone $zone): string => $zone->getName())->toArray())
                ? array_values($rooms)
                : [$c->getTask()->getPet()?->getName() ?? (TaskCategory::Pets === $c->getTask()->getCategory() ? 'Les animaux' : 'Toute la coloc')],
            $this->completions,
        )));
        arsort($counts);

        return \array_slice(array_map(static fn (string $name, int $count): array => ['name' => $name, 'count' => $count], array_keys($counts), $counts), 0, $limit);
    }
}
