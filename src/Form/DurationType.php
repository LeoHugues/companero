<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A number of hours, typed in hours or in days ("2 jours", "4 heures").
 *
 * @extends AbstractType<?int>
 */
class DurationType extends AbstractType
{
    private const DAY = 24;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', IntegerType::class, ['label' => false, 'required' => false, 'attr' => ['min' => 1, 'aria-label' => 'Durée']])
            ->add('unit', ChoiceType::class, [
                'label' => false,
                'expanded' => true,
                'choices' => ['heures' => 'hours', 'jours' => 'days'],
            ])
            ->addModelTransformer(new CallbackTransformer(
                static fn (?int $hours): array => null !== $hours && 0 === $hours % self::DAY
                    ? ['amount' => intdiv($hours, self::DAY), 'unit' => 'days']
                    : ['amount' => $hours, 'unit' => 'hours'],
                static fn (?array $duration): ?int => null === ($duration['amount'] ?? null) || (int) $duration['amount'] <= 0
                    ? null
                    : (int) $duration['amount'] * ('days' === ($duration['unit'] ?? null) ? self::DAY : 1),
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['required' => false]);
    }
}
