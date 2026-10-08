<?php

namespace App\Form;

use App\Entity\Household;
use App\Entity\Member;
use App\Task\CompletionChange;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<CompletionChange> */
class CompletionType extends AbstractType
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
            ->add('completedAt', DateTimeType::class, [
                'label' => 'Fait quand ?',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['max' => $this->clock->now()->format('Y-m-d\TH:i')],
            ])
            ->add('member', EntityType::class, [
                'class' => Member::class,
                'label' => 'Par qui ?',
                'expanded' => true,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $members): QueryBuilder => $members->createQueryBuilder('m')
                    ->andWhere('m.household = :household')
                    ->setParameter('household', $household)
                    ->orderBy('m.name', 'ASC'),
            ])
            ->add('points', IntegerType::class, ['label' => 'Ça valait combien ?', 'attr' => ['min' => 0, 'max' => 500, 'step' => 5]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CompletionChange::class]);
        $resolver->setRequired('household');
        $resolver->setAllowedTypes('household', Household::class);
    }
}
