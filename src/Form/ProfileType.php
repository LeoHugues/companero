<?php

namespace App\Form;

use App\Entity\Member;
use App\Entity\Presence;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/** @extends AbstractType<Member> */
class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('weeklyGoal', ChoiceType::class, [
                'label' => 'Mon objectif de la semaine',
                'expanded' => true,
                // 70 points a week ≈ 5 minutes a day.
                'choices' => array_combine(array_map(static fn (int $goal): string => \sprintf('%d pts · %d min/j', $goal, $goal / 14), Member::GOAL_CHOICES), Member::GOAL_CHOICES),
            ])
            ->add('presenceDays', RangeType::class, [
                'label' => 'Je suis là combien de jours par semaine ?',
                'mapped' => false,
                'attr' => ['min' => 0, 'max' => Presence::FULL_WEEK, 'step' => 1],
                'constraints' => [new Range(min: 0, max: Presence::FULL_WEEK)],
            ])
            ->add('notifyCleaningDay', CheckboxType::class, ['label' => 'Matin du jour de ménage', 'help' => 'La liste du jour, à 9 h', 'required' => false])
            ->add('notifyOverdue', CheckboxType::class, ['label' => 'Quand une tâche compte sur moi', 'help' => 'Une tâche à faire dont j’ai la charge, ou qu’on vient de signaler : au plus un rappel par jour pour chacune', 'required' => false])
            ->add('notifyWeeklyReview', CheckboxType::class, ['label' => 'Bilan du dimanche soir', 'help' => 'Le résumé de la semaine, à 20 h', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Member::class]);
    }
}
