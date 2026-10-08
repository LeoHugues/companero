<?php

namespace App\Enum;

/** What a surprise hidden in a task card holds: points, XP, or a gift. */
enum BountyKind: string
{
    case Points = 'points';
    case Xp = 'xp';
    case TeamBoost = 'team_boost';
    case XpBoost = 'xp_boost';
    case StreakFreeze = 'streak_freeze';
    case YellowCard = 'yellow_card';
    case Treat = 'treat';

    /** The gift it brings, if it is one. */
    public function gift(): ?GiftKind
    {
        return match ($this) {
            self::Points, self::Xp => null,
            self::TeamBoost => GiftKind::TeamBoost,
            self::XpBoost => GiftKind::XpBoost,
            self::StreakFreeze => GiftKind::StreakFreeze,
            self::YellowCard => GiftKind::YellowCard,
            self::Treat => GiftKind::Treat,
        };
    }

    public function label(int $amount = 0): string
    {
        return match ($this) {
            self::Points => \sprintf('+%d pts', $amount),
            self::Xp => \sprintf('+%d XP', $amount),
            default => $this->gift()->label(),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Points => 'star',
            self::Xp => 'spark',
            default => $this->gift()->icon(),
        };
    }
}
