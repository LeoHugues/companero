<?php

namespace App\Form;

use App\Entity\Household;
use App\Entity\Ledger;
use App\Entity\Member;
use App\Ledger\Currency;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An account: its name, a word about it, its currency, and whether the whole coloc is in it. When it is opened,
 * who is in it: colocs chosen by their faces, guests by their names.
 *
 * @extends AbstractType<Ledger>
 */
class LedgerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'empty_data' => '',
                'attr' => ['maxlength' => Ledger::NAME_MAX_LENGTH, 'autocomplete' => 'off', 'placeholder' => 'Dépenses communes, week-end à Annecy…'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Un mot dessus',
                'required' => false,
                'attr' => ['maxlength' => Ledger::DESCRIPTION_MAX_LENGTH, 'rows' => 2, 'placeholder' => 'Facultatif'],
            ])
            ->add('currency', ChoiceType::class, [
                'label' => 'Devise du compte',
                'choices' => Currency::choices(),
                'disabled' => $options['currency_locked'],
                'help' => $options['currency_locked']
                    ? 'Elle ne change plus une fois des dépenses notées : chacune peut être payée dans une autre devise.'
                    : 'Les équilibres se comptent dans cette devise ; une dépense peut être payée dans une autre.',
            ])
            ->add('everyone', CheckboxType::class, [
                'label' => 'Toute la coloc',
                'help' => 'Ceux qui arrivent dans la coloc y entrent d’eux-mêmes.',
                'required' => false,
            ]);

        /** @var Household|null $household */
        $household = $options['household'];
        if (null !== $household) {
            $builder
                ->add('members', EntityType::class, [
                    'class' => Member::class,
                    'label' => 'Les colocs',
                    'mapped' => false,
                    'expanded' => true,
                    'multiple' => true,
                    'required' => false,
                    'choice_label' => 'name',
                    'data' => $household->getMembers()->toArray(),
                    'query_builder' => static fn (EntityRepository $members): QueryBuilder => $members->createQueryBuilder('m')
                        ->andWhere('m.household = :household')
                        ->setParameter('household', $household)
                        ->orderBy('m.name', 'ASC'),
                ])
                ->add('guests', TextareaType::class, [
                    'label' => 'D’autres personnes',
                    'help' => 'Un prénom par ligne : un ami du week-end, la sœur de passage…',
                    'mapped' => false,
                    'required' => false,
                    'attr' => ['rows' => 2, 'autocomplete' => 'off'],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Ledger::class, 'household' => null, 'currency_locked' => false]);
        $resolver->setAllowedTypes('household', ['null', Household::class]);
        $resolver->setAllowedTypes('currency_locked', 'bool');
    }
}
