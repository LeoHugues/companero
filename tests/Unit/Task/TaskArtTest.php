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
        yield ['Ranger la vaisselle de l’égouttoir', 'counter'];
        yield ['Vider le lave-vaisselle', 'dishwasher'];
        yield ['Nettoyer sous le lave-vaisselle', 'dishwasher'];
        yield ['Nettoyer le frigo', 'fridge'];
        yield ['Nettoyer le lave-linge', 'washer'];
        yield ['Ranger et nettoyer les placards', 'cupboard'];
        yield ['Ranger le placard de l’entrée', 'cupboard'];
        yield ['Nettoyer le parking', 'parking'];
        yield ['A/R Déchetterie', 'dump'];
        yield ['Nettoyer et ranger le tiroir à couverts', 'cutlery'];
        yield ['Nettoyer vomi Gizmo', 'mess'];
        yield ['Toile d’araignée', 'cobweb'];
        yield ['Tondre autour de la maison', 'mower'];
        yield ['Tonte tour de la maison', 'mower'];
        yield ['Ranger le salon', 'sofa'];
        yield ['Aspirateur salon et cuisine', 'vacuum'];
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
