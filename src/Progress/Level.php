<?php

namespace App\Progress;

final readonly class Level
{
    public function __construct(
        public int $number,
        public string $rank,
        public int $xp,
        public int $floor,
        public int $ceiling,
        public string $nextRank,
    ) {
    }

    public function progress(): int
    {
        return (int) floor(100 * ($this->xp - $this->floor) / max(1, $this->ceiling - $this->floor));
    }

    public function xpToNext(): int
    {
        return $this->ceiling - $this->xp;
    }
}
