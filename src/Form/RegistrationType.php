<?php

namespace App\Form;

use App\Household\Registration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Registration> */
class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Ton prénom',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'given-name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Ton e-mail',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Un mot de passe',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'new-password'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Registration::class]);
    }
}
