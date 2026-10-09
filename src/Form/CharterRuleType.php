<?php

namespace App\Form;

use App\Entity\CharterRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<CharterRule> */
class CharterRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('text', TextType::class, [
                'empty_data' => '',
                'label' => $options['new'] ? 'Une nouvelle règle' : 'La règle',
                'attr' => ['placeholder' => 'On ferme la porte du frigo', 'maxlength' => 200],
            ])
            ->add('why', TextareaType::class, [
                'label' => 'Pourquoi ? (facultatif)',
                'help' => 'Ce qui se déplie sous la règle : une raison, une anecdote, une source.',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'min-h-[84px] py-2.5'],
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
