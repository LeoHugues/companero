<?php

namespace App\Enum;

/**
 * The sky above the Casa, from her mood: the cleaner the home, the finer the weather.
 * Neglected, it rains on her roof; very neglected, it storms.
 */
enum CasaWeather: string
{
    case Sunny = 'sunny';
    case Fair = 'fair';
    case Cloudy = 'cloudy';
    case Rainy = 'rainy';
    case Stormy = 'stormy';

    /** Below this cleanliness, a neglected Casa's rain turns to a storm. */
    public const STORM_BELOW = 30;

    public static function of(CasaMood $mood, int $cleanliness): self
    {
        return match ($mood) {
            CasaMood::Radiant => self::Sunny,
            CasaMood::Okay => self::Fair,
            CasaMood::Dusty => self::Cloudy,
            CasaMood::Neglected => $cleanliness < self::STORM_BELOW ? self::Stormy : self::Rainy,
        };
    }

    /** In a few words, for the scene's description. */
    public function label(): string
    {
        return match ($this) {
            self::Sunny => 'grand soleil',
            self::Fair => 'beau temps, quelques nuages',
            self::Cloudy => 'ciel couvert',
            self::Rainy => 'il pleut',
            self::Stormy => 'orage',
        };
    }

    public function raining(): bool
    {
        return \in_array($this, [self::Rainy, self::Stormy], true);
    }

    /** The sun shows (or, at night, the moon and stars) only when the sky is fair enough. */
    public function clear(): bool
    {
        return \in_array($this, [self::Sunny, self::Fair], true);
    }

    /** @return list<string> what she may say about the sky when tapped, on top of her mood's lines */
    public function taps(): array
    {
        return match ($this) {
            self::Sunny => ["Grand soleil sur la coloc\u{202F}! C’est grâce à vous, ça."],
            self::Fair => ["Encore deux ou trois nuages… Une tâche et je les chasse\u{202F}!"],
            self::Cloudy => ["Le ciel se couvre… Un coup de balai et le soleil revient\u{202F}?"],
            self::Rainy => ['Il pleut sur mon toit… Une tâche et ça se lève, promis.'],
            self::Stormy => ["Ça gronde, là-haut\u{202F}! Même les chats se cachent. Un petit coup de main\u{202F}?"],
        };
    }
}
