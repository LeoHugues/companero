<?php

namespace App\Form;

use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Pet;
use App\Entity\Task;
use App\Entity\Zone;
use App\Enum\Rarity;
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
    /** A template: it comes back on its own (regular, on a fixed day) or is reported in one tap (express). */
    public const TEMPLATE = 'template';
    /** Something to do once. */
    public const ONE_OFF = 'one_off';

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
                'attr' => ['placeholder' => self::ONE_OFF === $options['mode'] ? 'Ex. Appeler le proprio pour la fuite' : 'Ex. Passer la serpillière'],
            ]);
        if (self::TEMPLATE === $options['mode']) {
            $builder->add('kind', EnumType::class, [
                'class' => TaskKind::class,
                'label' => 'Elle revient comment ?',
                'help' => 'Régulière : tous les tant de jours. À jour fixe : un jour et une heure (les poubelles le mardi soir). Express : souvent et vite fait, un appui quand c’est fait. Occasionnelle : elle dort jusqu’à ce que quelqu’un dise « ça arrive » (le vomi du chat, la chasse d’eau qui fuit).',
                'expanded' => true,
                'choices' => [TaskKind::Rolling, TaskKind::Scheduled, TaskKind::Quick, TaskKind::Occasional],
                'choice_label' => static fn (TaskKind $kind): string => $kind->label(),
            ]);
        }
        $builder
            ->add('category', EnumType::class, [
                'class' => TaskCategory::class,
                'label' => 'Type',
                'expanded' => true,
                'choice_label' => static fn (TaskCategory $category): string => $category->label(),
            ])
            ->add('zones', EntityType::class, [
                'class' => Zone::class,
                'label' => 'Où ?',
                'help' => 'Une pièce, ou plusieurs (le salon avec la cuisine ouverte et ses toilettes) ; aucune : toute la coloc.',
                'required' => false,
                'expanded' => true,
                'multiple' => true,
                'by_reference' => false,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $zones): QueryBuilder => $zones->createQueryBuilder('z')
                    ->andWhere('z.household = :household')
                    ->setParameter('household', $household)
                    ->orderBy('z.name', 'ASC'),
            ])
            ->add('rarity', EnumType::class, [
                'class' => Rarity::class,
                'label' => 'Rareté de la carte',
                'expanded' => true,
                'choice_label' => static fn (Rarity $rarity): string => $rarity->label(),
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
            ->add('warningHours', DurationType::class, [
                'label' => 'Orange combien de temps avant ?',
                'help' => 'La carte prévient que le moment approche. Vide : automatique (aux 60 % du rythme, ou 2 jours avant l’échéance).',
            ])
            ->add('marginHours', DurationType::class, [
                'label' => 'Rouge combien de temps après ?',
                'help' => 'Le délai maximum une fois le moment venu ; au-delà, elle est en retard. Vide : 1 jour.',
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

        if ($options['allow_catalog']) {
            $builder->add('catalog', CheckboxType::class, [
                'label' => 'La garder dans le catalogue',
                'help' => 'La prochaine fois, elle se choisit en un appui au lieu d’être retapée.',
                'mapped' => false,
                'required' => false,
                'data' => true,
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
            'allow_catalog' => false,
            'mode' => self::TEMPLATE,
        ]);
        $resolver->setAllowedValues('mode', [self::TEMPLATE, self::ONE_OFF]);
        $resolver->setRequired('household');
        $resolver->setAllowedTypes('household', Household::class);
        $resolver->setAllowedTypes('allow_reservation', 'bool');
        $resolver->setAllowedTypes('allow_done', 'bool');
        $resolver->setAllowedTypes('allow_catalog', 'bool');
    }
}
