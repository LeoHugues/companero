<?php

namespace App\Form;

use App\Entity\Absence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Absence> */
class AbsenceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, ['empty_data' => '', 'label' => 'C’est quoi ?', 'attr' => ['placeholder' => 'Vacances, week-end chez mes parents…']])
            ->add('startsOn', DateType::class, ['label' => 'Du', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endsOn', DateType::class, ['label' => 'Au', 'widget' => 'single_text', 'input' => 'datetime_immutable']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Absence::class]);
    }
}
