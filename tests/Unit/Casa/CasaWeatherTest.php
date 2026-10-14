<?php

namespace App\Tests\Unit\Casa;

use App\Enum\CasaMood;
use App\Enum\CasaWeather;
use PHPUnit\Framework\TestCase;

final class CasaWeatherTest extends TestCase
{
    public function testTheCleanerTheHomeTheFinerTheWeather(): void
    {
        $weather = static fn (int $cleanliness): CasaWeather => CasaWeather::of(CasaMood::fromCleanliness($cleanliness), $cleanliness);

        self::assertSame(CasaWeather::Sunny, $weather(95));
        self::assertSame(CasaWeather::Fair, $weather(85));
        self::assertSame(CasaWeather::Cloudy, $weather(70));
        self::assertSame(CasaWeather::Rainy, $weather(40));
        self::assertSame(CasaWeather::Stormy, $weather(20));
    }

    public function testOnlyANeglectedCasaGetsWet(): void
    {
        self::assertTrue(CasaWeather::Rainy->raining());
        self::assertTrue(CasaWeather::Stormy->raining());
        self::assertFalse(CasaWeather::Cloudy->raining());
        self::assertTrue(CasaWeather::Sunny->clear());
        self::assertFalse(CasaWeather::Cloudy->clear());

        foreach (CasaWeather::cases() as $weather) {
            self::assertNotEmpty($weather->taps());
            self::assertNotSame('', $weather->label());
        }
    }
}
