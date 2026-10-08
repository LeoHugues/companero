<?php

namespace App\Enum;

enum PointReason: string
{
    case Task = 'task';
    case Punctuality = 'punctuality';
    case Rescue = 'rescue';
    case Adjustment = 'adjustment';
    case TeamBonus = 'team_bonus';
    case Boost = 'boost';
    /** Counts for the XP only, not for the weekly goal. */
    case XpBoost = 'xp_boost';

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
        };
    }
}
