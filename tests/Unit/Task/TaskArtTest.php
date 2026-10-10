<?php

namespace App\Tests\Unit\Task;

use App\Task\TaskArt;
use App\Tests\Unit\TaskFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaskArtTest extends TestCase
{
    use TaskFactory;

    /** @return iterable<array{string, ?string}> */
    public static function titles(): iterable
    {
        yield ['Aspirateur salon et cuisine', 'vacuum'];
        yield ['Serpillière salon, cuisine et toilettes', 'mop'];
        yield ['Réparer la chasse d’eau', 'plumbing'];
        yield ['Nettoyer les toilettes', 'toilet'];
        yield ['Sortir les poubelles', 'bin'];
        yield ['Faire le verre', 'bin'];
        yield ['Nettoyer les vitres du salon', 'window'];
        yield ['Nettoyer la grande table de la terrasse', 'terrace'];
        yield ['Plans de travail (javel ou vinaigre)', 'counter'];
        yield ['Vider le lave-vaisselle', 'counter'];
        yield ['Tondre autour de la maison', null];
        yield ['Ranger la console', null];
    }

    #[DataProvider('titles')]
    public function testThePictureOfACardIsGuessedFromItsTitle(string $title, ?string $picture): void
    {
        $task = $this->task();
        $task->setTitle($title);

        self::assertSame($picture, TaskArt::of($task));
    }
}
