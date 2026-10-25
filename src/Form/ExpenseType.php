<?php

namespace App\Form;

use App\Entity\Ledger;
use App\Entity\Participant;
use App\Enum\ExpenseCategory;
use App\Enum\ExpenseKind;
use App\Enum\SplitMode;
use App\Ledger\Currency;
use App\Ledger\ExpenseDraft;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An expense, an income or a transfer: what, how much (in another currency, at a rate), who paid, who it was for and
 * how it is split. The colocs are chosen by their faces, the categories by their drawings.
 *
 * @extends AbstractType<ExpenseDraft>
 */
class ExpenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Ledger $ledger */
        $ledger = $options['ledger'];
        $participants = $ledger->getParticipants()->toArray();
        $who = [
            'choices' => $participants,
            'choice_value' => 'id',
            'choice_label' => static fn (Participant $participant): string => $participant->getName(),
            'expanded' => true,
            'placeholder' => false,
        ];

        $builder
            ->add('kind', EnumType::class, [
                'class' => ExpenseKind::class,
                'label' => 'C’est…',
                'expanded' => true,
                'choice_label' => static fn (ExpenseKind $kind): string => $kind->label(),
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
                'empty_data' => '',
                'attr' => ['maxlength' => 80, 'autocomplete' => 'off', 'placeholder' => 'Courses, loyer, pizzas…', 'enterkeyhint' => 'next'],
            ])
            ->add('amount', AmountType::class, ['label' => 'Montant'])
            ->add('currency', ChoiceType::class, [
                'label' => 'Devise',
                'choices' => Currency::choices(),
                'choice_label' => static fn (string $code): string => Currency::symbol($code) === $code ? $code : Currency::symbol($code).' '.$code,
            ])
            ->add('exchangeRate', RateType::class, ['label' => 'Taux de change', 'required' => false])
            ->add('paidBy', ChoiceType::class, ['label' => 'Payé par', 'required' => false] + $who)
            ->add('recipient', ChoiceType::class, ['label' => 'À', 'required' => false] + $who)
            ->add('splitMode', EnumType::class, [
                'class' => SplitMode::class,
                'label' => 'Répartir',
                'expanded' => true,
                'choice_label' => static fn (SplitMode $mode): string => $mode->label(),
            ])
            ->add('split', SplitType::class, ['participants' => $participants])
            ->add('spentOn', DateType::class, [
                'label' => 'Quand ?',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('category', EnumType::class, [
                'class' => ExpenseCategory::class,
                'label' => 'Catégorie',
                'help' => 'Sans choix, elle est devinée d’après le titre.',
                'required' => false,
                'expanded' => true,
                'placeholder' => false,
                'choice_label' => static fn (ExpenseCategory $category): string => $category->label(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ExpenseDraft::class, 'empty_data' => null]);
        $resolver->setRequired('ledger');
        $resolver->setAllowedTypes('ledger', Ledger::class);
    }
}
