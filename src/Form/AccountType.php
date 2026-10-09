<?php

namespace App\Form;

use App\Entity\Member;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * How I log in: my username, and a new password if I want one. Nothing touches the member until it
 * is all valid (a username changed in memory would log them out), hence unmapped fields.
 *
 * @extends AbstractType<array<string, mixed>>
 */
class AccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Mon pseudo',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'username', 'autocapitalize' => 'none', 'autocorrect' => 'off', 'spellcheck' => 'false'],
                'constraints' => [
                    new NotBlank(message: 'Choisis un pseudo.'),
                    new Length(min: 2, max: Member::USERNAME_MAX_LENGTH, minMessage: 'Au moins {{ limit }} caractères.'),
                    new Regex(Member::USERNAME_PATTERN, message: 'Des lettres, des chiffres, des points ou des tirets, sans espace.'),
                ],
            ])
            ->add('newPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'help' => 'Laisse vide pour garder l’actuel.',
                'required' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [new Length(min: 8, minMessage: 'Au moins {{ limit }} caractères.')],
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'help' => 'Pour confirmer que c’est bien toi.',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new UserPassword(message: 'Ce n’est pas ton mot de passe actuel.')],
            ]);
    }
}
