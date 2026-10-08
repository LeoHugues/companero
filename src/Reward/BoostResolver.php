<?php

namespace App\Reward;

use App\Entity\Boost;
use App\Entity\Member;
use App\Enum\BoostKind;
use App\Repository\BoostRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Which boosts a member enjoys right now: the automatic one of the cleaning day, a boost a coloc
 * activated for everyone, one offered to them, their own XP boost. Boosts of a kind do not add up.
 */
final class BoostResolver implements ResetInterface
{
    public const POINTS_PER_EXTRA = 3;

    /** @var array<string, list<Boost>> */
    private array $cache = [];

    public function __construct(
        private readonly BoostRepository $boosts,
    ) {
    }

    public function points(Member $member, \DateTimeImmutable $at): ?ActiveBoost
    {
        return $this->active($member, $at)[BoostKind::Points->value] ?? null;
    }

    public function xp(Member $member, \DateTimeImmutable $at): ?ActiveBoost
    {
        return $this->active($member, $at)[BoostKind::Xp->value] ?? null;
    }

    /** @return array<string, ActiveBoost> indexed by boost kind */
    public function active(Member $member, \DateTimeImmutable $at): array
    {
        $household = $member->getHousehold();
        $active = [];

        foreach ($this->stored($member, $at) as $boost) {
            $beneficiary = $boost->getBeneficiary();
            if (null !== $beneficiary && $beneficiary !== $member) {
                continue;
            }
            $grantedBy = $boost->getGrantedBy();
            $source = match (true) {
                BoostKind::Xp === $boost->getKind() => 'Boost d’XP',
                null === $beneficiary => 'Boost coloc'.(null !== $grantedBy ? ' de '.$grantedBy->getName() : ''),
                default => 'Offert par '.($grantedBy?->getName() ?? 'un coloc'),
            };
            // A boost meant for this member wins over one for everyone.
            if (!isset($active[$boost->getKind()->value]) || null !== $beneficiary) {
                $active[$boost->getKind()->value] = new ActiveBoost($boost->getKind(), $source, $boost->getEndsAt(), $grantedBy);
            }
        }

        if (!isset($active[BoostKind::Points->value]) && $household->hasCleaningDayBoost() && $household->isCleaningDay($at)) {
            $active[BoostKind::Points->value] = new ActiveBoost(BoostKind::Points, 'Jour de ménage', $at->setTime(0, 0)->modify('+1 day'));
        }

        return $active;
    }

    /** @return list<Boost> */
    private function stored(Member $member, \DateTimeImmutable $at): array
    {
        $key = $member->getHousehold()->getId().'@'.$at->format('U');

        return $this->cache[$key] ??= $this->boosts->findActive($member->getHousehold(), $at);
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
