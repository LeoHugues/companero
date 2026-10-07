<?php

namespace App\Twig\Components;

use App\Enum\CasaMood;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/** La Casa: the household as a character whose face follows how clean the home is. */
#[AsTwigComponent]
final class Casa
{
    private const DUST_SPOTS = [[26, 146, 8], [167, 136, 7], [53, 93, 6], [146, 155, 9], [17, 117, 6], [179, 97, 6]];

    public CasaMood $mood = CasaMood::Okay;
    public int $cleanliness = 100;
    /** Shown instead of the mood's sentence, e.g. right after a task was done. */
    public ?string $speech = null;
    public bool $reacting = false;
    public bool $showSpeech = true;

    public function restSpeech(): string
    {
        return $this->mood->speech();
    }

    public function mouthPath(): string
    {
        return match ($this->reacting ? CasaMood::Radiant : $this->mood) {
            CasaMood::Dusty => 'M86 136 Q100 128 114 136',
            CasaMood::Okay => 'M86 130 Q100 140 114 130',
            CasaMood::Radiant => 'M84 127 Q100 150 116 127 Z',
        };
    }

    public function mouthFilled(): bool
    {
        return $this->reacting || CasaMood::Radiant === $this->mood;
    }

    public function blushing(): bool
    {
        return $this->reacting || CasaMood::Radiant === $this->mood;
    }

    /** @return list<array{x: int, y: int, size: int}> the dirtier the home, the more dust around it */
    public function dust(): array
    {
        $count = max(0, (int) round((96 - $this->cleanliness) / 4));

        return array_map(
            static fn (array $spot): array => ['x' => $spot[0], 'y' => $spot[1], 'size' => $spot[2]],
            \array_slice(self::DUST_SPOTS, 0, $count),
        );
    }
}
