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
    /** Never planned, never late: done when needed and reported in one tap (emptied the dishwasher…). */
    case Quick = 'quick';
    /** Sleeps until it is needed (the cat was sick, the flush leaks): someone says "ça arrive", its card shows up until done. */
    case Occasional = 'occasional';

    public function label(): string
    {
        return match ($this) {
            self::Rolling => 'Régulière',
            self::Scheduled => 'À jour fixe',
            self::OneOff => 'Ponctuelle',
            self::Quick => 'Express',
            self::Occasional => 'Occasionnelle',
        };
    }

    /** Stays in the list once done. */
    public function isRecurring(): bool
    {
        return self::OneOff !== $this;
    }

    /** Comes back on its own and grows more urgent: it is what the Casa's cleanliness is made of. */
    public function isPlanned(): bool
    {
        return self::Rolling === $this || self::Scheduled === $this;
    }
}
