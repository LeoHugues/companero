<?php

namespace App\Enum;

enum Urgency: string
{
    case Fresh = 'fresh';
    case Soon = 'soon';
    case Due = 'due';
    case Late = 'late';

    public function weight(): int
    {
        return match ($this) {
            self::Fresh => 0,
            self::Soon => 1,
            self::Due => 2,
            self::Late => 3,
        };
    }

    public function max(self $other): self
    {
        return $other->weight() > $this->weight() ? $other : $this;
    }

    /** Upper bound of the freshness gauge, so that the bar never contradicts the status. */
    public function maxFreshness(): int
    {
        return match ($this) {
            self::Fresh => 100,
            self::Soon => 59,
            self::Due => 25,
            self::Late => 0,
        };
    }

    public function isPressing(): bool
    {
        return $this->weight() >= self::Due->weight();
    }
}
