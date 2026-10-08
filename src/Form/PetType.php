<?php

namespace App\Form;

use App\Entity\Household;
use App\Entity\Pet;
use App\Enum\PetSpecies;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Pet> */
class PetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['empty_data' => '', 'label' => 'Nouvel animal', 'attr' => ['placeholder' => 'Tishka']])
            ->add('species', EnumType::class, [
                'class' => PetSpecies::class,
                'label' => 'C’est un…',
                'expanded' => true,
                'choice_label' => static fn (PetSpecies $species): string => $species->label(),
                'data' => PetSpecies::Cat,
            ])
            ->add('description', TextType::class, ['label' => 'À quoi il ressemble ?', 'required' => false, 'attr' => ['placeholder' => 'Roux, petit, à poils longs']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Pet::class,
            'empty_data' => static fn (FormInterface $form): Pet => new Pet(
                $form->getConfig()->getOption('household'),
                (string) $form->get('name')->getData(),
                $form->get('species')->getData() ?? PetSpecies::Cat,
            ),
        ]);
        $resolver->setRequired('household');
        $resolver->setAllowedTypes('household', Household::class);
    }
}
