<?php

namespace App\Form;

use App\Twig\TaskLabels;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<int> */
class WeekdayType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $days = range(1, 7);

        $resolver->setDefaults([
            'choices' => array_combine(array_map(static fn (int $day): string => ucfirst(TaskLabels::weekday($day)), $days), $days),
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
