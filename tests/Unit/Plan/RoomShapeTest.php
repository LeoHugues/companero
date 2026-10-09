<?php

namespace App\Tests\Unit\Plan;

use App\Plan\RoomShape;
use PHPUnit\Framework\TestCase;

final class RoomShapeTest extends TestCase
{
    public function testAPolygonWithItsNameInTheMiddle(): void
    {
        $shape = RoomShape::parse('20,98 50,98 50,116 20,116');

        self::assertSame('20,98 50,98 50,116 20,116', $shape?->svgPoints());
        self::assertEquals([35.0, 107.0], $shape->label);
        self::assertFalse($shape->isUpright());
    }

    public function testTheNameCanBePlacedByHand(): void
    {
        self::assertEquals([45.5, 28.0], RoomShape::parse('20,21 71,21 71,51 45,36 20,51 @45.5,28')?->label);
    }

    public function testNonsenseIsNoShape(): void
    {
        self::assertNull(RoomShape::parse('salon'));
        self::assertNull(RoomShape::parse('1,2 3,4'));
        self::assertNull(RoomShape::parse(null));
    }

    public function testNarrowRoomsAreWrittenUpwards(): void
    {
        self::assertTrue(RoomShape::parse('20,81 25,81 25,97 20,97')?->isUpright());
        self::assertTrue(RoomShape::parse('58,92 71,92 71,97 58,97')?->isLow());
    }

    public function testALongNameGoesOnTwoLinesInASmallRoom(): void
    {
        $fit = RoomShape::parse('58,81 71,81 71,91 58,91')?->fit('Terrasse entrée');

        self::assertSame(['Terrasse', 'entrée'], $fit['lines'] ?? null);
        self::assertLessThanOrEqual(2.3, $fit['size']);
    }

    public function testTheCountOfTasksStaysInsideASlantedRoom(): void
    {
        // The living room, under the slanted big terrace: the corner of its box is on the terrace.
        $living = RoomShape::parse('20,52 45,37 58,44.4 50,54.4 50,81 57,81 57,97 26,97 26,80 20,80 @35,66');
        $terrace = RoomShape::parse('20,21 71,21 71,51 45,36 20,51');
        self::assertNotNull($living);
        self::assertTrue($terrace?->contains($living->maxX() - 2.2, $living->minY() + 2.2));

        [$x, $y] = $living->badge();
        self::assertTrue($living->contains($x, $y));
        self::assertFalse($terrace->contains($x, $y));

        // A plain rectangle: its top right corner, as before.
        self::assertSame([68.8, 94.2], RoomShape::parse('58,92 71,92 71,97 58,97')?->badge());
    }
}
