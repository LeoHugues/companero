<?php

namespace App\Form;

use App\Entity\Household;
use App\Entity\Zone;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
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
            ->add('name', TextType::class, ['empty_data' => '', 'label' => $options['with_plan'] ? 'Nom' : 'Nouvelle zone', 'attr' => ['placeholder' => 'Salle de bain du haut']])
            ->add('private', CheckboxType::class, [
                'label' => 'Privée',
                'help' => 'Une chambre, une salle de bain perso : ça compte pour les points, pas pour l’humeur de la Casa.',
                'required' => false,
            ]);

        if ($options['with_plan']) {
            $builder->add('planShape', TextareaType::class, [
                'label' => 'Sa forme sur le plan',
                'required' => false,
                'help' => 'Les coins de la pièce sur une grille, « x,y » séparés par des espaces (ex. 20,98 50,98 50,116 20,116), puis « @x,y » pour placer son nom si besoin. Vide : la pièce s’affiche en tuile sous le plan.',
                'attr' => ['rows' => 3, 'class' => 'font-mono text-sm'],
            ]);
        }
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
        $resolver->setDefault('with_plan', false);
        $resolver->setAllowedTypes('with_plan', 'bool');
        $resolver->setRequired('household');
        $resolver->setAllowedTypes('household', Household::class);
    }
}
