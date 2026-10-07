<?php

namespace App\Enum;

enum CasaMood: string
{
    case Dusty = 'dusty';
    case Okay = 'okay';
    case Radiant = 'radiant';

    public static function fromCleanliness(int $cleanliness): self
    {
        return match (true) {
            $cleanliness >= 92 => self::Radiant,
            $cleanliness >= 80 => self::Okay,
            default => self::Dusty,
        };
    }

    public function speech(): string
    {
        return match ($this) {
            self::Dusty => 'Je me sens un peu poussiéreuse… On s’y met ?',
            self::Okay => 'Ah, ça respire déjà mieux ! Encore un petit effort ?',
            self::Radiant => 'Je brille ! Vous êtes la meilleure coloc.',
        };
    }
}
