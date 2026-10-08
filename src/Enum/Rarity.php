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
}
