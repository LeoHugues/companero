<?php

namespace App\Form;

use App\Entity\Household;
use App\Entity\Zone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Zone> */
class ZoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['empty_data' => '', 'label' => 'Nouvelle zone', 'attr' => ['placeholder' => 'Salle de bain du haut']])
            ->add('private', CheckboxType::class, [
                'label' => 'Privée',
                'help' => 'Une chambre, une salle de bain perso : ça compte pour les points, pas pour l’humeur de la Casa.',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Zone::class,
            'empty_data' => static fn (FormInterface $form): Zone => new Zone(
                $form->getConfig()->getOption('household'),
                (string) $form->get('name')->getData(),
                (bool) $form->get('private')->getData(),
            ),
        ]);
        $resolver->setRequired('household');
        $resolver->setAllowedTypes('household', Household::class);
    }
}
