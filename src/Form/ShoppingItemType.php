<?php

namespace App\Form;

use App\Entity\ShoppingItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<ShoppingItem> */
class ShoppingItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' => 'Ce qui manque',
                'help' => 'Avec ce qu’il faut savoir pour acheter le bon : « Ampoule E27 (salle de bain) ».',
                'attr' => ['maxlength' => ShoppingItem::NAME_MAX_LENGTH, 'autocomplete' => 'off'],
            ])
            ->add('urgent', CheckboxType::class, [
                'label' => 'Urgent',
                'help' => 'Il n’y en a plus du tout : en haut de la liste.',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ShoppingItem::class]);
    }
}
