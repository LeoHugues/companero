<?php

namespace App\Enum;

/** How rare a task card is, like in a card game: the more a task is worth, the rarer its card. */
enum Rarity: string
{
    case Common = 'common';
    case Rare = 'rare';
    case Epic = 'epic';
    case Legendary = 'legendary';

    /** Lowest base points of each rarity (10 pts ≈ 5 minutes of effort). */
    public const RARE_POINTS = 20;
    public const EPIC_POINTS = 30;
    public const LEGENDARY_POINTS = 50;

    public static function fromPoints(int $points): self
    {
        return match (true) {
            $points >= self::LEGENDARY_POINTS => self::Legendary,
            $points >= self::EPIC_POINTS => self::Epic,
            $points >= self::RARE_POINTS => self::Rare,
            default => self::Common,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Common => 'Commune',
            self::Rare => 'Rare',
            self::Epic => 'Épique',
            self::Legendary => 'Légendaire',
        };
    }
}
