<?php

namespace App\Twig;

use App\Entity\Pet;
use App\Enum\PetSpecies;
use App\Pet\CatLook;
use Twig\Attribute\AsTwigFunction;

final class PetLooks
{
    /** How a cat of the household is drawn (its portrait in the household's settings), null for another animal. */
    #[AsTwigFunction('cat_look')]
    public static function catLook(Pet $pet): ?CatLook
    {
        return PetSpecies::Cat === $pet->getSpecies() ? CatLook::of($pet) : null;
    }
}
