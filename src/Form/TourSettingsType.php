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
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;

/**
 * The last step of the tour: one's own goal (nothing chosen in advance, so that it is chosen),
 * days at home and reminders.
 *
 * @extends AbstractType<Member>
 */
class TourSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('goal', ChoiceType::class, [
                'label' => 'Mon objectif de la semaine',
                'mapped' => false,
                'expanded' => true,
                'choices' => ProfileType::goalChoices(),
                'placeholder' => false,
                'constraints' => [new NotNull(message: 'Choisis ton objectif : tu pourras le changer quand tu veux.')],
            ])
            ->add('presenceDays', RangeType::class, [
                'label' => 'Je suis là combien de jours par semaine ?',
                'mapped' => false,
                'attr' => ['min' => 0, 'max' => Presence::FULL_WEEK, 'step' => 1],
                'constraints' => [new Range(min: 0, max: Presence::FULL_WEEK)],
            ])
            ->add('notifyCleaningDay', CheckboxType::class, ['label' => 'Matin du jour de ménage', 'help' => 'La liste du jour, à 9 h', 'required' => false])
            ->add('notifyOverdue', CheckboxType::class, ['label' => 'Quand une tâche compte sur moi', 'help' => 'Une tâche dont j’ai la charge, ou qu’on vient de signaler', 'required' => false])
            ->add('notifyWeeklyReview', CheckboxType::class, ['label' => 'Bilan du dimanche soir', 'help' => 'Le résumé de la semaine, à 20 h', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Member::class]);
    }
}
