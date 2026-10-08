<?php

namespace App\Plan;

/**
 * Where a room is drawn on the plan: a polygon on a grid ("20,52 20,80 26,80 …"),
 * optionally followed by where to write its name ("@38,66"; the middle of the shape otherwise).
 */
final readonly class RoomShape
{
    public const PATTERN = '/^\s*(-?\d+(\.\d+)?,-?\d+(\.\d+)?\s+){2,}-?\d+(\.\d+)?,-?\d+(\.\d+)?(\s*@-?\d+(\.\d+)?,-?\d+(\.\d+)?)?\s*$/';

    /**
     * @param non-empty-list<array{float, float}> $points
     * @param array{float, float}                 $label
     */
    private function __construct(
        public array $points,
        public array $label,
    ) {
    }

    public static function parse(?string $shape): ?self
    {
        if (null === $shape || 1 !== preg_match(self::PATTERN, $shape)) {
            return null;
        }

        [$polygon, $label] = array_pad(explode('@', $shape, 2), 2, null);
        $points = array_map(
            static fn (string $point): array => array_map('floatval', explode(',', $point)),
            preg_split('/\s+/', trim($polygon)) ?: [],
        );
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        return new self(
            $points,
            null !== $label ? array_map('floatval', explode(',', trim($label))) : [(min($xs) + max($xs)) / 2, (min($ys) + max($ys)) / 2],
        );
    }

    /** The "points" attribute of an SVG polygon. */
    public function svgPoints(): string
    {
        return implode(' ', array_map(static fn (array $point): string => $point[0].','.$point[1], $this->points));
    }

    public function minX(): float
    {
        return min(array_column($this->points, 0));
    }

    public function maxX(): float
    {
        return max(array_column($this->points, 0));
    }

    public function minY(): float
    {
        return min(array_column($this->points, 1));
    }

    public function maxY(): float
    {
        return max(array_column($this->points, 1));
    }

    /** Too thin for a name written across: it goes up the room instead. */
    public function isUpright(): bool
    {
        $width = $this->maxX() - $this->minX();

        return $width < 12 && $this->maxY() - $this->minY() > 2 * $width;
    }

    /**
     * The room's name, as big as it fits (and on two lines if it fits better).
     *
     * @return array{lines: list<string>, size: float}
     */
    public function fit(string $name, float $maxSize = 2.3, float $minSize = 1.7): array
    {
        $room = ($this->isUpright() ? $this->maxY() - $this->minY() : $this->maxX() - $this->minX()) - 1.6;
        // Average glyph width of the font, in ems.
        $sizeFor = static fn (string $line): float => $room / (max(1, mb_strlen($line)) * 0.56);

        if ($sizeFor($name) >= $minSize || !str_contains($name, ' ') || $this->isLow() || $this->isUpright()) {
            return ['lines' => [$name], 'size' => min($maxSize, $sizeFor($name))];
        }

        // Break at the space closest to the middle.
        $middle = mb_strlen($name) / 2;
        $spaces = array_keys(array_filter(mb_str_split($name), static fn (string $char): bool => ' ' === $char));
        usort($spaces, static fn (int $a, int $b): int => abs($a - $middle) <=> abs($b - $middle));
        $lines = [mb_substr($name, 0, $spaces[0]), mb_substr($name, $spaces[0] + 1)];

        return ['lines' => $lines, 'size' => min($maxSize, $sizeFor(mb_strlen($lines[0]) > mb_strlen($lines[1]) ? $lines[0] : $lines[1]))];
    }

    /** A low room (toilets…) only has space for one small line. */
    public function isLow(): bool
    {
        return !$this->isUpright() && $this->maxY() - $this->minY() < 8;
    }
}
