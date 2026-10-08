<?php

namespace App\Form;

use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Pet;
use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\TaskCategory;
use App\Enum\TaskKind;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;

/** @extends AbstractType<Task> */
class TaskType extends AbstractType
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Household $household */
        $household = $options['household'];

        $builder
            ->add('title', TextType::class, [
                'empty_data' => '',
                'label' => 'Quoi ?',
                'attr' => ['placeholder' => 'Ex. Appeler le proprio pour la fuite'],
            ])
            ->add('kind', EnumType::class, [
                'class' => TaskKind::class,
                'label' => 'Elle revient ?',
                'expanded' => true,
                'choice_label' => static fn (TaskKind $kind): string => $kind->label(),
            ])
            ->add('category', EnumType::class, [
                'class' => TaskCategory::class,
                'label' => 'Type',
                'expanded' => true,
                'choice_label' => static fn (TaskCategory $category): string => $category->label(),
            ])
            ->add('zone', EntityType::class, [
                'class' => Zone::class,
                'label' => 'Où ?',
                'required' => false,
                'expanded' => true,
                'placeholder' => 'Toute la coloc',
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $zones): QueryBuilder => $zones->createQueryBuilder('z')
                    ->andWhere('z.household = :household')
                    ->setParameter('household', $household)
                    ->orderBy('z.name', 'ASC'),
            ])
            ->add('points', IntegerType::class, ['label' => 'Ça vaut combien ?', 'attr' => ['min' => 5, 'max' => 500, 'step' => 5]])
            ->add('rhythmDays', IntegerType::class, ['label' => 'Tous les combien de jours ?', 'required' => false, 'attr' => ['min' => 1]])
            ->add('weeklyCommitment', IntegerType::class, [
                'label' => 'Au moins combien de fois par semaine ?',
                'help' => 'Facultatif : l’engagement de la coloc, vérifié au bilan.',
                'required' => false,
                'attr' => ['min' => 1],
            ])
            ->add('scheduledWeekday', WeekdayType::class, ['label' => 'Quel jour ?', 'required' => false, 'placeholder' => 'Choisir…', 'every_day' => true])
            ->add('scheduledTime', TimeType::class, ['label' => 'À quelle heure ?', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('cooldownHours', DurationType::class, [
                'label' => 'Pas avant combien de temps à nouveau ?',
                'help' => 'Une fois faite, elle ne peut pas être refaite avant ce délai. Laisse vide pour aucun délai.',
            ])
            ->add('dueAt', DateTimeType::class, ['label' => 'Pour quand ?', 'help' => 'Laisse vide si ce n’est pas pressé.', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable']);

        $members = static fn (EntityRepository $members): QueryBuilder => $members->createQueryBuilder('m')
            ->andWhere('m.household = :household')
            ->setParameter('household', $household)
            ->orderBy('m.name', 'ASC');
        if (!$household->getPets()->isEmpty()) {
            $builder->add('pet', EntityType::class, [
                'class' => Pet::class,
                'label' => 'Pour qui ?',
                'required' => false,
                'expanded' => true,
                'placeholder' => 'Tout le monde',
                'choice_label' => 'name',
                'choices' => $household->getPets()->toArray(),
            ]);
        }
        $builder
            ->add('assignee', EntityType::class, [
                'class' => Member::class,
                'label' => 'Qui s’en charge ?',
                'help' => 'C’est cette personne qui reçoit le rappel quand elle est à la maison.',
                'required' => false,
                'expanded' => true,
                'placeholder' => 'Personne en particulier',
                'choice_label' => 'name',
                'query_builder' => $members,
            ])
            ->add('backup', EntityType::class, [
                'class' => Member::class,
                'label' => 'Et quand elle n’est pas là, qui prévenir ?',
                'required' => false,
                'expanded' => true,
                'placeholder' => 'Toute la coloc présente',
                'choice_label' => 'name',
                'query_builder' => $members,
            ]);

        if ($options['allow_done']) {
            $builder
                ->add('done', CheckboxType::class, [
                    'label' => 'C’est déjà fait',
                    'help' => 'Pour noter ce qui a été fait ces derniers jours : les points comptent à la date indiquée.',
                    'mapped' => false,
                    'required' => false,
                ])
                ->add('doneAt', DateTimeType::class, [
                    'label' => 'Fait quand ?',
                    'mapped' => false,
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'data' => $this->clock->now(),
                    'attr' => ['max' => $this->clock->now()->format('Y-m-d\TH:i')],
                    'constraints' => [new LessThanOrEqual('now', message: 'Pas dans le futur : ce qui est noté est déjà fait.')],
                ]);
        }

        if ($options['allow_reservation']) {
            $builder->add('reserve', CheckboxType::class, [
                'label' => 'Je m’en occupe',
                'help' => 'Sinon, elle part dans la liste de la coloc.',
                'mapped' => false,
                'required' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
            'allow_reservation' => false,
            'allow_done' => false,
        ]);
        $resolver->setRequired('household');
        $resolver->setAllowedTypes('household', Household::class);
        $resolver->setAllowedTypes('allow_reservation', 'bool');
        $resolver->setAllowedTypes('allow_done', 'bool');
    }
}
