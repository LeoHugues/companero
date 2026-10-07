<?php

namespace App\Form;

use App\Household\Founding;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Founding> */
class FoundingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('householdName', TextType::class, ['empty_data' => '', 'label' => 'Le nom de la coloc', 'attr' => ['placeholder' => 'La coloc des Lilas']])
            ->add('cleaningDay', WeekdayType::class, ['label' => 'Le jour de ménage']);
    }

    public function getParent(): string
    {
        return RegistrationType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Founding::class]);
    }
}
