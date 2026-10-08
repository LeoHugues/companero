<?php

namespace App\Reward;

use App\Enum\GiftKind;

/** What each new level brings. */
final class LevelGifts
{
    private const CYCLE = [GiftKind::TeamBoost, GiftKind::StreakFreeze, GiftKind::FriendBoost, GiftKind::XpBoost];

    /** @return list<GiftKind> */
    public function forLevel(int $level, bool $hasPets): array
    {
        if ($level < 2) {
            return [];
        }

        $gifts = [self::CYCLE[($level - 2) % \count(self::CYCLE)]];
        if ($hasPets) {
            $gifts[] = GiftKind::Treat;
        }

        return $gifts;
    }
}
