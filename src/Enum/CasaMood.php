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
            self::Dusty => "Je me sens un peu poussiéreuse… On s’y met\u{202F}?",
            self::Okay => "Ah, ça respire déjà mieux\u{202F}! Encore un petit effort\u{202F}?",
            self::Radiant => "Je brille\u{202F}! Vous êtes la meilleure coloc.",
        };
    }
}
