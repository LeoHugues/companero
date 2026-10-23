<?php

namespace App\Twig\Components;

use App\Entity\Member;
use App\Entity\Pet;
use App\Enum\CasaMood;
use App\Enum\CasaWeather;
use App\Enum\PetSpecies;
use App\Pet\CatLook;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Twig\Markup;

/**
 * La Casa: the household as a character whose face follows how clean the home is, with the
 * cats of the coloc around her. She blinks, looks around, sleeps at night; tapped, she answers;
 * dusted with a finger, she giggles (see casa_controller.js). In her landscape (hero), the sky
 * follows her too: sun, clouds, rain, storm (CasaWeather).
 */
#[AsTwigComponent]
final class Casa
{
    /** Where the dust gathers around her, in the scene's coordinates: [x, y, size]. */
    private const DUST_SPOTS = [[96, 166, 8], [237, 156, 7], [123, 113, 6], [216, 175, 9], [86, 137, 6], [249, 117, 6], [150, 182, 5], [190, 98, 5]];
    private const MAX_CATS = 2;

    public CasaMood $mood = CasaMood::Okay;
    public int $cleanliness = 100;
    /** Shown instead of the mood's sentence, e.g. right after a task was done. HTML when it is Markup. */
    public string|Markup|null $speech = null;
    /** What she says at rest instead of her mood's sentence, even at night: the task she asks for on the home page (HTML). */
    public ?Markup $line = null;
    public bool $reacting = false;
    public bool $showSpeech = true;
    /** Planted across the whole width, in her landscape (the home page); otherwise on her own, a bubble above. */
    public bool $hero = false;

    public function __construct(
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    public function restSpeech(): string|Markup
    {
        return $this->line ?? $this->ownSpeech();
    }

    /** Her own words: her mood's sentence, or her sleep's. */
    public function ownSpeech(): string
    {
        return $this->asleep() ? 'Zzz… Je dors, mais je t’entends. Bonne nuit !' : $this->mood->speech();
    }

    /** What she says at rest as HTML: what her controller puts back in her bubble once she is done answering. */
    public function restSpeechHtml(): string
    {
        return null !== $this->line ? (string) $this->line : $this->ownSpeechHtml();
    }

    /** Her own words as HTML, for when the last quest is taken (quest_controller.js). */
    public function ownSpeechHtml(): string
    {
        return htmlspecialchars($this->ownSpeech(), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
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

    /** @return list<string> what she may say when tapped — and, under her sky, about the weather */
    public function taps(): array
    {
        if ($this->asleep()) {
            return ['Mmh… encore cinq minutes…', 'Zzz… chut, les chats dorment.', 'Il est tard ! Les tâches attendront demain.'];
        }

        return $this->hero ? [...$this->mood->taps(), ...$this->weather()->taps()] : $this->mood->taps();
    }

    /** The sky of her landscape, from her mood (not her face: a task just done brings a rainbow, not the sun). */
    public function weather(): CasaWeather
    {
        return CasaWeather::of($this->mood, $this->cleanliness);
    }

    /** @return array{sky: string, cloud: string, shade: string} the colours of her sky: warm creams, greying with the weather */
    public function skyColors(): array
    {
        if ($this->asleep() && !$this->weather()->raining()) {
            return ['sky' => '#E3D9CC', 'cloud' => '#EFE7DB', 'shade' => '#D9CEBF'];
        }

        return match ($this->weather()) {
            CasaWeather::Sunny => ['sky' => '#F5E2C4', 'cloud' => '#FCF5EA', 'shade' => '#F0E2CC'],
            CasaWeather::Fair => ['sky' => '#F2E4CF', 'cloud' => '#FAF1E4', 'shade' => '#EFE2CF'],
            CasaWeather::Cloudy => ['sky' => '#E8E0D3', 'cloud' => '#F4EDE2', 'shade' => '#DCD1C1'],
            CasaWeather::Rainy => ['sky' => '#DAD4CA', 'cloud' => '#C7BDAF', 'shade' => '#B1A694'],
            CasaWeather::Stormy => ['sky' => '#CBC4BA', 'cloud' => '#A3988A', 'shade' => '#8B8072'],
        };
    }

    /** @return list<array{x: int, y: int, scale: float, duration: int, delay: int}> the clouds of her sky, in the landscape's coordinates */
    public function clouds(): array
    {
        $clouds = match ($this->weather()) {
            CasaWeather::Sunny => [[34, 66, .7]],
            CasaWeather::Fair => [[243, 58, 1.0], [8, 81, .85]],
            CasaWeather::Cloudy => [[284, 38, 1.5], [196, 72, 1.05], [18, 64, 1.2], [118, -26, .9]],
            CasaWeather::Rainy => [[300, -6, 1.7], [176, -40, 1.9], [36, -14, 1.6], [246, 62, 1.15], [70, 70, 1.0]],
            CasaWeather::Stormy => [[300, -10, 1.9], [176, -44, 2.1], [36, -18, 1.8], [252, 60, 1.35], [72, 66, 1.2], [140, 22, 1.0]],
        };

        return array_map(
            static fn (array $cloud, int $i): array => ['x' => $cloud[0], 'y' => $cloud[1], 'scale' => $cloud[2], 'duration' => 34 + 7 * $i, 'delay' => -5 * $i],
            $clouds,
            array_keys($clouds),
        );
    }

    /** @return list<array{x: int, y: int, duration: int, delay: int}> the raindrops, spread with a fixed stride so that the page does not change on each refresh */
    public function raindrops(): array
    {
        $stormy = CasaWeather::Stormy === $this->weather();
        $drops = [];
        for ($i = 0, $count = $stormy ? 46 : 30; $i < $count; ++$i) {
            $drops[] = [
                'x' => ($i * 47) % 380 - 14,
                'y' => ($i * 29) % 70 - 34,
                'duration' => ($stormy ? 620 : 900) + ($i % 3) * 70,
                'delay' => -(($i * 173) % 1000),
            ];
        }

        return $drops;
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
