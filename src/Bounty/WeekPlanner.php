<?php

namespace App\Bounty;

use App\Calendar\Week;
use App\Entity\Bounty;
use App\Entity\Household;
use App\Entity\Task;
use App\Enum\BountyKind;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Random\Randomizer;

/**
 * Plans a week: hides a few surprises in task cards, at random. A card with a surprise
 * goes up one rarity until someone does it and finds what it holds.
 */
final class WeekPlanner
{
    /** About one card in five, at least one, never more than four. */
    private const SHARE = 0.2;
    private const MAX = 4;

    /** What a surprise may hold, and how often. */
    private const DRAWS = [
        [BountyKind::Points, 10, 18],
        [BountyKind::Points, 15, 12],
        [BountyKind::Points, 25, 5],
        [BountyKind::Xp, 30, 12],
        [BountyKind::Xp, 60, 5],
        [BountyKind::YellowCard, 0, 14],
        [BountyKind::XpBoost, 0, 8],
        [BountyKind::TeamBoost, 0, 6],
        [BountyKind::StreakFreeze, 0, 6],
        [BountyKind::Treat, 0, 8],
    ];

    private readonly Randomizer $randomizer;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskRepository $tasks,
        ?Randomizer $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer();
    }

    /** @return list<Bounty> the surprises hidden this time; none if the week was already planned */
    public function plan(Household $household, Week $week): array
    {
        if (!$household->planBounties($week->start)) {
            return [];
        }

        $candidates = array_values(array_filter($this->tasks->findActive($household), self::isCandidate(...)));
        $bounties = [];
        if ([] !== $candidates) {
            $count = min(self::MAX, max(1, (int) round(\count($candidates) * self::SHARE)));
            $keys = \count($candidates) <= $count ? array_keys($candidates) : $this->randomizer->pickArrayKeys($candidates, $count);
            foreach ($keys as $key) {
                $task = $candidates[$key];
                [$kind, $amount] = $this->draw(!$household->getPets()->isEmpty());
                $bounties[] = $bounty = new Bounty($task, $week->start, $kind, $amount);
                $this->entityManager->persist($bounty);
            }
        }
        $this->entityManager->flush();

        return $bounties;
    }

    /** Cards that come back on their own, not every day: express tasks have no card, daily ones would be too easy. */
    private static function isCandidate(Task $task): bool
    {
        return $task->getKind()->isPlanned() && !$task->isDaily();
    }

    /** @return array{BountyKind, int} */
    private function draw(bool $hasPets): array
    {
        $draws = array_values(array_filter(self::DRAWS, static fn (array $draw): bool => $hasPets || BountyKind::Treat !== $draw[0]));
        $roll = $this->randomizer->getInt(1, array_sum(array_column($draws, 2)));
        foreach ($draws as [$kind, $amount, $weight]) {
            $roll -= $weight;
            if ($roll <= 0) {
                return [$kind, $amount];
            }
        }

        return [BountyKind::Points, 10];
    }
}
