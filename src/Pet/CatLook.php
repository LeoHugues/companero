<?php

namespace App\Pet;

use App\Entity\Pet;

/**
 * How a cat of the household is drawn around the Casa, read from the words describing it
 * ("Chat roux, petit et mince, à poils longs"): its coat, its build, its fur.
 */
final readonly class CatLook
{
    /** coat => [fur, belly, stripes, eyes] */
    private const COATS = [
        'ginger' => ['#E8873A', '#FADDB4', '#C8682A', '#7FB04A'],
        'brown' => ['#5A3A27', '#B98D63', '#38231A', '#E3A21A'],
        'black' => ['#332823', '#4A3E38', '#2A201C', '#E3A21A'],
        'grey' => ['#8E8780', '#D9D3CB', '#6B655F', '#7FB04A'],
        'white' => ['#F4EDE2', '#FFFFFF', '#E2D6C6', '#5FA3C9'],
        'cream' => ['#E9C99A', '#FBEBD3', '#D3A970', '#7FB04A'],
    ];

    private function __construct(
        public string $name,
        public string $coat,
        /** 0.8 (small and slim) to 1.15 (almost a maine coon) */
        public float $scale,
        public bool $fluffy,
        public bool $striped,
        /** Big cats lie down like a loaf; the others sit. */
        public bool $loaf,
    ) {
    }

    public static function of(Pet $pet): self
    {
        $words = mb_strtolower($pet->getDescription() ?? '');
        $has = static fn (string ...$needles): bool => [] !== array_filter($needles, static fn (string $needle): bool => str_contains($words, $needle));

        $coat = match (true) {
            $has('roux', 'rousse', 'orange') => 'ginger',
            $has('noir') => 'black',
            $has('gris', 'bleu') => 'grey',
            $has('blanc', 'blanche') => 'white',
            $has('crème', 'creme', 'beige', 'sable') => 'cream',
            $has('brun', 'marron', 'chocolat', 'tigré', 'tigre') => 'brown',
            default => 'grey',
        };
        $big = $has('gros', 'grosse', 'grand', 'maine', 'costaud', 'énorme');
        $small = $has('petit', 'petite', 'mince', 'chaton', 'menu');

        return new self(
            $pet->getName(),
            $coat,
            $big ? 1.15 : ($small ? 0.82 : 1.0),
            $has('poils longs', 'poil long', 'angora', 'maine', 'touffu'),
            $has('tigré', 'tigre', 'rayé', 'raye', 'tabby') || 'ginger' === $coat,
            $big,
        );
    }

    public function fur(): string
    {
        return self::COATS[$this->coat][0];
    }

    public function belly(): string
    {
        return self::COATS[$this->coat][1];
    }

    public function stripes(): string
    {
        return self::COATS[$this->coat][2];
    }

    public function eyes(): string
    {
        return self::COATS[$this->coat][3];
    }
}
