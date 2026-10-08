<?php

namespace App\Form;

use App\Entity\Task;
use App\Twig\TaskLabels;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<int> */
class WeekdayType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $days = range(1, 7);

        $resolver->setDefaults([
            'every_day' => false,
            'choices' => static fn (Options $options): array => ($options['every_day'] ? ['Tous les jours' => Task::EVERY_DAY] : [])
                + array_combine(array_map(static fn (int $day): string => ucfirst(TaskLabels::weekday($day)), $days), $days),
        ]);
        $resolver->setAllowedTypes('every_day', 'bool');
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
