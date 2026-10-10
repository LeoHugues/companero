<?php

namespace App\Enum;

/** How the Casa feels, from the cleanliness of the shared home. */
enum CasaMood: string
{
    case Neglected = 'neglected';
    case Dusty = 'dusty';
    case Okay = 'okay';
    case Radiant = 'radiant';

    public static function fromCleanliness(int $cleanliness): self
    {
        return match (true) {
            $cleanliness >= 92 => self::Radiant,
            $cleanliness >= 80 => self::Okay,
            $cleanliness >= 55 => self::Dusty,
            default => self::Neglected,
        };
    }

    /** Her mood in a few words, as a title above the cleanliness gauge. */
    public function title(): string
    {
        return match ($this) {
            self::Neglected => 'Un peu négligée',
            self::Dusty => 'Un peu poussiéreuse',
            self::Okay => 'Contente',
            self::Radiant => 'Rayonnante',
        };
    }

    /** @return array{int, string}|null the cleanliness of her next mood, and what it changes — none once radiant */
    public function next(): ?array
    {
        return match ($this) {
            self::Neglected => [55, 'elle respire'],
            self::Dusty => [80, 'elle sourit'],
            self::Okay => [92, 'elle rayonne'],
            self::Radiant => null,
        };
    }

    public function speech(): string
    {
        return match ($this) {
            self::Neglected => "Pfiou… j’ai des toiles d’araignée partout. Un petit coup de main\u{202F}?",
            self::Dusty => "Je me sens un peu poussiéreuse… On s’y met\u{202F}?",
            self::Okay => "Ah, ça respire déjà mieux\u{202F}! Encore un petit effort\u{202F}?",
            self::Radiant => "Je brille\u{202F}! Vous êtes la meilleure coloc.",
        };
    }

    /** @return list<string> what she says when someone taps her, at random */
    public function taps(): array
    {
        return match ($this) {
            self::Neglected => [
                "Atchoum\u{202F}! Pardon, c’est la poussière.",
                "Un coup de plumeau, peut-être\u{202F}? Glisse ton doigt sur moi\u{202F}!",
                'Même les chats éternuent, c’est dire.',
                'Une seule tâche et je me sens déjà mieux, promis.',
            ],
            self::Dusty => [
                "Hihi, ça chatouille\u{202F}!",
                "Tu veux bien me dépoussiérer\u{202F}? Glisse ton doigt sur moi.",
                "Atchoum\u{202F}! Oups.",
                'On est sur la bonne voie, je le sens.',
            ],
            self::Okay => [
                "Coucou\u{202F}!",
                "Hihi, arrête, ça chatouille\u{202F}!",
                "Je suis bien, là. Encore un effort et je brille\u{202F}!",
                "Tu as vu les chats\u{202F}? Ils ont l’air contents.",
            ],
            self::Radiant => [
                "Je brille tellement que je me vois dans les vitres\u{202F}!",
                "Câlin\u{202F}!",
                'Vous êtes trop forts, franchement.',
                'Même la litière sent bon. Enfin, presque.',
            ],
        };
    }
}
