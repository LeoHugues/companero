<?php

namespace App\Household;

use App\Entity\Member;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** What someone fills in to join a household, or to claim the profile waiting for them there. */
#[UniqueEntity(fields: ['username'], message: 'Ce pseudo est déjà pris.', entityClass: Member::class)]
class Registration
{
    #[Assert\NotBlank(message: 'Comment tu t’appelles ?')]
    #[Assert\Length(max: 40)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Choisis un pseudo.')]
    #[Assert\Length(min: 2, max: Member::USERNAME_MAX_LENGTH, minMessage: 'Au moins {{ limit }} caractères.')]
    #[Assert\Regex(Member::USERNAME_PATTERN, message: 'Des lettres, des chiffres, des points ou des tirets, sans espace.')]
    public string $username = '' {
        set => Member::normalizeUsername($value);
    }

    #[Assert\NotBlank(message: 'Choisis un mot de passe.')]
    #[Assert\Length(min: 8, minMessage: 'Au moins {{ limit }} caractères.')]
    public string $plainPassword = '';
}
