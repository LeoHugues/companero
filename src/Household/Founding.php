<?php

namespace App\Household;

use App\Entity\Household;
use Symfony\Component\Validator\Constraints as Assert;

/** What the first member fills in to create the household. */
class Founding extends Registration
{
    #[Assert\NotBlank(message: 'Donne un nom à ta coloc.')]
    #[Assert\Length(max: 80)]
    public string $householdName = '';

    #[Assert\Range(min: 1, max: 7)]
    public int $cleaningDay = Household::DEFAULT_CLEANING_DAY;
}
