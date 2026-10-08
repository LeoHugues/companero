<?php

namespace App\Form;

use App\Entity\Household;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Household> */
class HouseholdType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['empty_data' => '', 'label' => 'Nom de la coloc'])
            ->add('cleaningDay', WeekdayType::class, ['label' => 'Jour de ménage'])
            ->add('weeklyGoal', IntegerType::class, [
                'label' => 'Objectif de la maison',
                'help' => 'Les points de toute la coloc sur la semaine. Atteint, il rapporte un bonus à chacun et fait avancer la série de la coloc.',
                'attr' => ['min' => 10, 'step' => 10],
            ])
            ->add('cleaningDayBoost', CheckboxType::class, [
                'label' => 'Boost du jour de ménage',
                'help' => 'Ce jour-là, +1 pt tous les 3 pts pour tout le monde',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Household::class]);
    }
}
