<?php

namespace App\Twig;

use App\Shopping\ItemArt;
use Psr\Clock\ClockInterface;
use Twig\Attribute\AsTwigFunction;

/** The drawing of what is missing, and when it was noticed or bought. */
final readonly class ShoppingLabels
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    /** @return array{icon: string, tile: string} */
    #[AsTwigFunction('shopping_art')]
    public function art(string $name): array
    {
        return ItemArt::of($name);
    }

    /** "aujourd'hui", "hier", "lundi" in the last week, "le 14/10" before. */
    #[AsTwigFunction('shopping_day')]
    public function day(\DateTimeImmutable $at): string
    {
        $days = (int) $at->setTime(0, 0)->diff($this->clock->now()->setTime(0, 0))->format('%r%a');

        return match (true) {
            $days <= 0 => 'aujourd’hui',
            1 === $days => 'hier',
            $days < 7 => TaskLabels::weekday((int) $at->format('N')),
            default => 'le '.$at->format('d/m'),
        };
    }
}
