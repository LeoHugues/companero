<?php

namespace App\Form;

use App\Entity\Household;
use App\Entity\Task;
use App\Enum\TaskKind;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * "I did this": one of the household's templates, done now or a few days ago.
 *
 * @extends AbstractType<array{task: ?Task, doneAt: ?\DateTimeImmutable}>
 */
class LogCompletionType extends AbstractType
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
            ->add('task', EntityType::class, [
                'class' => Task::class,
                'label' => 'Qu’est-ce que tu as fait ?',
                'placeholder' => 'Choisir…',
                'choice_label' => 'title',
                'group_by' => static fn (Task $task): string => TaskKind::Quick === $task->getKind() ? 'Express' : ($task->getZones()->first() ?: null)?->getName() ?? 'Toute la coloc',
                'query_builder' => static fn (EntityRepository $tasks): QueryBuilder => $tasks->createQueryBuilder('t')
                    ->andWhere('t.household = :household')
                    ->andWhere('t.archivedAt IS NULL')
                    ->andWhere('t.kind != :oneOff')
                    ->setParameter('household', $household)
                    ->setParameter('oneOff', TaskKind::OneOff)
                    ->orderBy('t.title', 'ASC'),
                'constraints' => [new NotNull(message: 'Choisis ce que tu as fait.')],
            ])
            ->add('doneAt', DateTimeType::class, [
                'label' => 'Quand ?',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'data' => $this->clock->now(),
                'attr' => ['max' => $this->clock->now()->format('Y-m-d\TH:i')],
                'constraints' => [
                    new NotNull(message: 'Quand ?'),
                    new LessThanOrEqual('now', message: 'Pas dans le futur : ce qui est noté est déjà fait.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('household');
        $resolver->setAllowedTypes('household', Household::class);
    }
}
