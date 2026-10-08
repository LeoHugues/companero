<?php

namespace App\Reward;

use App\Entity\Member;
use App\Enum\BoostKind;

/** A boost in effect for a member right now, wherever it comes from. */
final readonly class ActiveBoost
{
    public function __construct(
        public BoostKind $kind,
        /** "Jour de ménage", "Boost coloc de Léo", "Offert par Robin"… */
        public string $source,
        public \DateTimeImmutable $endsAt,
        public ?Member $grantedBy = null,
    ) {
    }

    /** +1 every 3 points: fairer than a flat bonus, small tasks get a little, big ones a lot. */
    public function bonusFor(int $points): int
    {
        return intdiv(max(0, $points), BoostResolver::POINTS_PER_EXTRA);
    }
}
