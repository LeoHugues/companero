<?php

namespace App\Plan;

use App\Task\ZoneSummary;

/** The rooms drawn on the plan, the garden around them, and the rooms that have no place on it. */
final readonly class HousePlan
{
    /** Width of the garden around the house, in grid units. */
    public const GARDEN = 4;

    /**
     * @param list<array{room: ZoneSummary, shape: RoomShape}> $drawn
     * @param list<ZoneSummary>                                $others
     */
    private function __construct(
        public array $drawn,
        public ?ZoneSummary $garden,
        public array $others,
    ) {
    }

    /**
     * @param list<ZoneSummary> $rooms
     * @param ?string           $gardenName the zone that surrounds the house instead of being drawn in it
     */
    public static function of(array $rooms, ?string $gardenName = 'Jardin'): self
    {
        $drawn = [];
        $garden = null;
        $others = [];
        foreach ($rooms as $room) {
            $shape = RoomShape::parse($room->zone->getPlanShape());
            if (null !== $shape) {
                $drawn[] = ['room' => $room, 'shape' => $shape];
            } elseif (null !== $gardenName && 0 === strcasecmp($room->zone->getName(), $gardenName)) {
                $garden = $room;
            } else {
                $others[] = $room;
            }
        }

        if ([] === $drawn && null !== $garden) {
            $others[] = $garden;
            $garden = null;
        }

        return new self($drawn, $garden, $others);
    }

    public function isDrawn(): bool
    {
        return [] !== $this->drawn;
    }

    /** @return array{x: float, y: float, width: float, height: float} the house, garden included */
    public function viewBox(): array
    {
        $house = $this->houseBox();

        return ['x' => $house['x'] - self::GARDEN, 'y' => $house['y'] - self::GARDEN, 'width' => $house['width'] + 2 * self::GARDEN, 'height' => $house['height'] + 2 * self::GARDEN];
    }

    /** @return array{x: float, y: float, width: float, height: float} the rectangle around every room */
    public function houseBox(): array
    {
        $shapes = array_column($this->drawn, 'shape');
        $minX = min(array_map(static fn (RoomShape $s): float => $s->minX(), $shapes));
        $minY = min(array_map(static fn (RoomShape $s): float => $s->minY(), $shapes));
        $maxX = max(array_map(static fn (RoomShape $s): float => $s->maxX(), $shapes));
        $maxY = max(array_map(static fn (RoomShape $s): float => $s->maxY(), $shapes));

        return ['x' => $minX, 'y' => $minY, 'width' => $maxX - $minX, 'height' => $maxY - $minY];
    }
}
