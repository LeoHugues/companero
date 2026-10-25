<?php

namespace App\Form;

use App\Ledger\ShareInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

/** @extends AbstractType<ShareInput> */
class ShareInputType extends AbstractType
{
    public const MAX_WEIGHT = 99;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('included', CheckboxType::class, ['label' => $options['label'], 'required' => false])
            ->add('weight', IntegerType::class, [
                'label' => 'Parts',
                'required' => false,
                // Said under the split, like what is left to split.
                'error_bubbling' => true,
                'attr' => ['min' => 0, 'max' => self::MAX_WEIGHT, 'inputmode' => 'numeric'],
                'constraints' => [new Range(min: 0, max: self::MAX_WEIGHT, notInRangeMessage: 'De {{ min }} à {{ max }} parts.')],
            ])
            ->add('amount', AmountType::class, ['label' => 'Montant', 'required' => false, 'error_bubbling' => true]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ShareInput::class, 'empty_data' => static fn (): ShareInput => new ShareInput(false)]);
    }
}
