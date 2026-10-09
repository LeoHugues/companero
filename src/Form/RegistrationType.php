<?php

namespace App\Form;

use App\Household\Registration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Registration> */
class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['with_name']) {
            $builder->add('name', TextType::class, [
                'label' => 'Ton prénom',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'given-name'],
            ]);
        }
        $builder
            ->add('username', TextType::class, [
                'label' => 'Ton pseudo',
                'help' => 'Pour te connecter. Ton prénom fait très bien l’affaire.',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'username', 'autocapitalize' => 'none', 'autocorrect' => 'off', 'spellcheck' => 'false'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Un mot de passe',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'new-password'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Registration::class,
            // false: claiming a profile, whose first name is already known.
            'with_name' => true,
        ]);
        $resolver->setAllowedTypes('with_name', 'bool');
    }
}
