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
 * An exchange rate ("0,92": what one unit of a currency is worth in another), kept as a decimal string.
 *
 * @extends AbstractType<?string>
 */
class RateType extends AbstractType
{
    public const SCALE = 6;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addViewTransformer(new CallbackTransformer(
            static fn (?string $rate): string => null === $rate ? '' : Currency::rate($rate),
            static function (?string $typed): ?string {
                if (null === $typed || '' === trim($typed)) {
                    return null;
                }

                return Currency::parse($typed, self::SCALE) ?? throw new TransformationFailedException('Not a rate.');
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => false,
            'invalid_message' => 'Un taux comme 0,92.',
            'attr' => ['inputmode' => 'decimal', 'autocomplete' => 'off'],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
