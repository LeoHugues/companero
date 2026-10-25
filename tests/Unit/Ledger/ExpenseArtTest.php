<?php

namespace App\Tests\Unit\Ledger;

use App\Enum\ExpenseCategory;
use App\Ledger\ExpenseArt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExpenseArtTest extends TestCase
{
    /** @return iterable<array{string, ExpenseCategory}> */
    public static function titles(): iterable
    {
        yield ['Courses Lidl', ExpenseCategory::Groceries];
        yield ['Boulangerie', ExpenseCategory::Groceries];
        yield ['Loyer d’octobre', ExpenseCategory::Rent];
        yield ['Location du chalet', ExpenseCategory::Rent];
        yield ['Facture EDF', ExpenseCategory::Energy];
        yield ['Électricité', ExpenseCategory::Energy];
        yield ['Internet Free', ExpenseCategory::Internet];
        yield ['Netflix', ExpenseCategory::Internet];
        yield ['Pizzas du vendredi', ExpenseCategory::Restaurant];
        yield ['Fondue à Genève', ExpenseCategory::Restaurant];
        yield ['Uber Eats', ExpenseCategory::Restaurant];
        yield ['Produits ménagers', ExpenseCategory::House];
        yield ['Ikea', ExpenseCategory::House];
        yield ['Essence', ExpenseCategory::Transport];
        yield ['Billets de train', ExpenseCategory::Transport];
        yield ['Pharmacie', ExpenseCategory::Health];
        yield ['Croquettes', ExpenseCategory::Pets];
        yield ['Cadeau pour Léa', ExpenseCategory::Gifts];
        yield ['Places de cinéma', ExpenseCategory::Leisure];
        yield ['Un truc', ExpenseCategory::Other];
    }

    #[DataProvider('titles')]
    public function testTheCategoryIsGuessedFromTheTitle(string $title, ExpenseCategory $category): void
    {
        self::assertSame($category, ExpenseArt::guess($title));
    }
}
