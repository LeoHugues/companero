<?php

namespace App\Enum;

enum TaskKind: string
{
    /** Comes back once a given number of days has passed since it was last done. */
    case Rolling = 'rolling';
    /** Comes back on a fixed weekday and time (e.g. bins on Tuesday evening). */
    case Scheduled = 'scheduled';
    /** Done once, then gone. */
    case OneOff = 'one_off';

    public function label(): string
    {
        return match ($this) {
            self::Rolling => 'Régulière',
            self::Scheduled => 'À jour fixe',
            self::OneOff => 'Ponctuelle',
        };
    }

    public function isRecurring(): bool
    {
        return self::OneOff !== $this;
    }
}
