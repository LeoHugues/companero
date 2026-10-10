<?php

namespace App\Twig\Components;

use App\Entity\Member;
use App\Entity\Pet;
use App\Enum\CasaMood;
use App\Enum\PetSpecies;
use App\Pet\CatLook;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * La Casa: the household as a character whose face follows how clean the home is, with the
 * cats of the coloc around her. She blinks, looks around, sleeps at night; tapped, she answers;
 * dusted with a finger, she giggles (see casa_controller.js).
 */
#[AsTwigComponent]
final class Casa
{
    /** Where the dust gathers around her, in the scene's coordinates: [x, y, size]. */
    private const DUST_SPOTS = [[96, 166, 8], [237, 156, 7], [123, 113, 6], [216, 175, 9], [86, 137, 6], [249, 117, 6], [150, 182, 5], [190, 98, 5]];
    private const MAX_CATS = 2;

    public CasaMood $mood = CasaMood::Okay;
    public int $cleanliness = 100;
    /** Shown instead of the mood's sentence, e.g. right after a task was done. */
    public ?string $speech = null;
    public bool $reacting = false;
    public bool $showSpeech = true;
    /** Planted across the whole width, in her landscape (the home page); otherwise on her own, a bubble above. */
    public bool $hero = false;

    public function __construct(
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    public function restSpeech(): string
    {
        return $this->asleep() ? 'Zzz… Je dors, mais je t’entends. Bonne nuit !' : $this->mood->speech();
    }

    /** From 11 pm to 7 am she sleeps — unless someone just did a task. */
    public function asleep(): bool
    {
        $hour = (int) $this->clock->now()->format('G');

        return !$this->reacting && ($hour >= 23 || $hour < 7);
    }

    /** How she looks: the mood, or her happiest face while thanking someone. */
    public function face(): CasaMood
    {
        return $this->reacting ? CasaMood::Radiant : $this->mood;
    }

    public function wallColor(): string
    {
        return match ($this->face()) {
            CasaMood::Neglected => '#E0BE78',
            CasaMood::Dusty => '#EEC25C',
            default => '#F6C453',
        };
    }

    public function mouthPath(): string
    {
        return match ($this->face()) {
            CasaMood::Neglected => 'M86 139 Q100 127 114 139',
            CasaMood::Dusty => 'M86 136 Q100 129 114 136',
            CasaMood::Okay => 'M86 130 Q100 141 114 130',
            CasaMood::Radiant => 'M84 127 Q100 151 116 127 Z',
        };
    }

    public function mouthFilled(): bool
    {
        return CasaMood::Radiant === $this->face();
    }

    public function blushing(): bool
    {
        return \in_array($this->face(), [CasaMood::Radiant, CasaMood::Okay], true);
    }

    /** @return list<array{x: int, y: int, size: int}> the dirtier the home, the more dust around her */
    public function dust(): array
    {
        $count = max(0, (int) round((96 - $this->cleanliness) / 4));

        return array_map(
            static fn (array $spot): array => ['x' => $spot[0], 'y' => $spot[1], 'size' => $spot[2]],
            \array_slice(self::DUST_SPOTS, 0, $count),
        );
    }

    /** @return list<string> what she may say when tapped */
    public function taps(): array
    {
        return $this->asleep() ? ['Mmh… encore cinq minutes…', 'Zzz… chut, les chats dorment.', 'Il est tard ! Les tâches attendront demain.'] : $this->mood->taps();
    }

    /** @return list<array{look: CatLook, x: int, y: int}> the cats of the coloc, around her: the one sitting on the left, the big one lying on the right */
    public function cats(): array
    {
        $member = $this->security->getUser();
        if (!$member instanceof Member) {
            return [];
        }
        $cats = array_map(
            CatLook::of(...),
            array_values(array_filter($member->getHousehold()->getPets()->toArray(), static fn (Pet $pet): bool => PetSpecies::Cat === $pet->getSpecies())),
        );
        usort($cats, static fn (CatLook $a, CatLook $b): int => $a->loaf <=> $b->loaf ?: $a->scale <=> $b->scale);
        $cats = \array_slice($cats, 0, self::MAX_CATS);

        $placed = [];
        $taken = [];
        foreach ($cats as $look) {
            $side = $look->loaf ? 'right' : 'left';
            $side = isset($taken[$side]) ? ('left' === $side ? 'right' : 'left') : $side;
            $taken[$side] = true;
            $placed[] = ['look' => $look, 'x' => 'right' === $side ? 288 : 48, 'y' => 'right' === $side ? 200 : 198];
        }

        return $placed;
    }
}
