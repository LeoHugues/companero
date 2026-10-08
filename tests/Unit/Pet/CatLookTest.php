<?php

namespace App\Tests\Unit\Pet;

use App\Entity\Household;
use App\Entity\Pet;
use App\Enum\CasaMood;
use App\Pet\CatLook;
use PHPUnit\Framework\TestCase;

final class CatLookTest extends TestCase
{
    public function testOurCatsAreDrawnFromHowTheyAreDescribed(): void
    {
        $household = new Household('La coloc', new \DateTimeImmutable());

        $tishka = CatLook::of(new Pet($household, 'Tishka', description: 'Chat roux, petit et mince, à poils longs'));
        self::assertSame('ginger', $tishka->coat);
        self::assertTrue($tishka->fluffy);
        self::assertFalse($tishka->loaf);
        self::assertLessThan(1, $tishka->scale);

        $big = CatLook::of(new Pet($household, 'Gizmo', description: 'Gros chat, presque un maine coon : brun foncé tigré, plus clair vers le ventre'));
        self::assertSame('brown', $big->coat);
        self::assertTrue($big->striped);
        self::assertTrue($big->loaf, 'A big cat lies like a loaf.');
        self::assertGreaterThan(1, $big->scale);

        $plain = CatLook::of(new Pet($household, 'Minou'));
        self::assertSame('grey', $plain->coat);
        self::assertSame(1.0, $plain->scale);
    }

    public function testTheCasaFeelsNeglectedBelowHalfClean(): void
    {
        self::assertSame(CasaMood::Neglected, CasaMood::fromCleanliness(40));
        self::assertSame(CasaMood::Dusty, CasaMood::fromCleanliness(70));
        self::assertSame(CasaMood::Okay, CasaMood::fromCleanliness(85));
        self::assertSame(CasaMood::Radiant, CasaMood::fromCleanliness(95));
        self::assertNotEmpty(CasaMood::Neglected->taps());
    }
}
