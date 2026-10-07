<?php

namespace App\Form;

use App\Entity\Household;
use Symfony\Component\Form\AbstractType;
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
            ->add('cleaningDay', WeekdayType::class, ['label' => 'Jour de ménage']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Household::class]);
    }
}
