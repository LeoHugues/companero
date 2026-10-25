<?php

namespace App\Form;

use App\Ledger\Currency;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An amount of money, typed the French way or not ("12,50", "12.5", "1 234,56 €"), kept in cents.
 *
 * @extends AbstractType<?int>
 */
class AmountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addViewTransformer(new CallbackTransformer(
            static fn (?int $cents): string => null === $cents ? '' : number_format($cents / 100, 2, ',', ''),
            static function (?string $typed): ?int {
                if (null === $typed || '' === trim($typed)) {
                    return null;
                }
                $decimal = Currency::parse($typed) ?? throw new TransformationFailedException('Not an amount.');

                return Currency::toCents($decimal);
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
            'invalid_message' => 'Un montant comme 12,50.',
            'attr' => ['inputmode' => 'decimal', 'autocomplete' => 'off', 'placeholder' => '0,00'],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
