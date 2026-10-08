<?php

namespace App\Enum;

/** How rare a task card is, like in a card game: chosen for each task, common unless said otherwise. */
enum Rarity: string
{
    case Common = 'common';
    case Rare = 'rare';
    case Epic = 'epic';
    case Legendary = 'legendary';

    public function label(): string
    {
        return match ($this) {
            self::Common => 'Commune',
            self::Rare => 'Rare',
            self::Epic => 'Épique',
            self::Legendary => 'Légendaire',
        };
    }

    /** One class up: what a card becomes while it hides a surprise. */
    public function next(): self
    {
        return match ($this) {
            self::Common => self::Rare,
            self::Rare => self::Epic,
            self::Epic, self::Legendary => self::Legendary,
        };
    }
}
