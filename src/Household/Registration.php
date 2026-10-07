<?php

namespace App\Household;

use App\Entity\Member;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/** What someone fills in to join a household. */
#[UniqueEntity(fields: ['email'], message: 'Cette adresse est déjà utilisée.', entityClass: Member::class)]
class Registration
{
    #[Assert\NotBlank(message: 'Comment tu t’appelles ?')]
    #[Assert\Length(max: 40)]
    public string $name = '';

    #[Assert\NotBlank(message: 'Il nous faut ton adresse e-mail.')]
    #[Assert\Email(message: 'Cette adresse ne semble pas valide.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Choisis un mot de passe.')]
    #[Assert\Length(min: 8, minMessage: 'Au moins {{ limit }} caractères.')]
    public string $plainPassword = '';
}
