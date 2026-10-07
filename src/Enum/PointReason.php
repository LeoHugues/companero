<?php

namespace App\Enum;

enum PointReason: string
{
    case Task = 'task';
    case Punctuality = 'punctuality';
    case Rescue = 'rescue';
    case Adjustment = 'adjustment';
    case TeamBonus = 'team_bonus';

    public function label(): string
    {
        return match ($this) {
            self::Task => 'Tâche',
            self::Punctuality => 'Ponctualité',
            self::Rescue => 'Rattrapage',
            self::Adjustment => 'Ajustement',
            self::TeamBonus => 'Bonus collectif',
        };
    }
}
