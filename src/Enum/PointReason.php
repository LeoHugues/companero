<?php

namespace App\Enum;

enum PointReason: string
{
    case Task = 'task';
    /** No longer earned (only boosts add to a task now), kept for the lines already in the journal. */
    case Punctuality = 'punctuality';
    /** No longer earned, kept for the lines already in the journal. */
    case Rescue = 'rescue';
    case Adjustment = 'adjustment';
    case TeamBonus = 'team_bonus';
    case Boost = 'boost';
    /** Counts for the XP only, not for the weekly goal. */
    case XpBoost = 'xp_boost';
    /** A surprise hidden in a task card. */
    case Bounty = 'bounty';
    /** A surprise of XP: counts for the XP only. */
    case BountyXp = 'bounty_xp';

    public function label(): string
    {
        return match ($this) {
            self::Task => 'Tâche',
            self::Punctuality => 'Ponctualité',
            self::Rescue => 'Rattrapage',
            self::Adjustment => 'Ajustement',
            self::TeamBonus => 'Bonus collectif',
            self::Boost => 'Boost',
            self::XpBoost => 'Boost d’XP',
            self::Bounty => 'Surprise',
            self::BountyXp => 'Surprise d’XP',
        };
    }

    /** @return list<self> the lines that count for the XP only, not for the weekly goal */
    public static function xpOnly(): array
    {
        return [self::XpBoost, self::BountyXp];
    }

    /** Kept when a completion is moved: they were earned once, not worked out again from the task. */
    public function followsCompletion(): bool
    {
        return \in_array($this, [self::Adjustment, self::Bounty, self::BountyXp], true);
    }
}
