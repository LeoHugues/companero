<?php

namespace App\Form;

use App\Entity\CharterRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<CharterRule> */
class CharterRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // A sentence, often longer than a line on a phone: a field that grows with it.
            ->add('text', TextareaType::class, [
                'empty_data' => '',
                'label' => 'La règle',
                'help' => 'Une phrase, à la première personne si possible : « je… », « on… ».',
                'attr' => ['rows' => 2, 'maxlength' => 200, 'placeholder' => 'On ferme bien la porte du frigo', 'class' => 'min-h-[64px]'],
            ])
            ->add('why', TextareaType::class, [
                'label' => 'Pourquoi ?',
                'help' => 'Facultatif : ce qui se déplie sous la règle. Une raison, une anecdote, une source.',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Sinon le joint fatigue, et le frigo glace.'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CharterRule::class,
            'new' => true,
            'empty_data' => static fn (FormInterface $form): CharterRule => new CharterRule(
                $form->getConfig()->getOption('household'),
                (string) $form->get('text')->getData(),
                $form->get('why')->getData(),
            ),
        ]);
        $resolver->setRequired('household');
    }
}
