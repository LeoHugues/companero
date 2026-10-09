<?php

namespace App\Form;

use App\Entity\Household;
use App\Entity\Member;
use App\Entity\Pet;
use App\Enum\PetSpecies;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<Pet> */
class PetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $household = $options['household'];
        $builder
            ->add('name', TextType::class, ['empty_data' => '', 'label' => $options['new'] ? 'Nouvel animal' : 'Son nom', 'attr' => ['placeholder' => 'Tishka']])
            ->add('species', EnumType::class, [
                'class' => PetSpecies::class,
                'label' => 'C’est un…',
                'expanded' => true,
                'choice_label' => static fn (PetSpecies $species): string => $species->label(),
            ] + ($options['new'] ? ['data' => PetSpecies::Cat] : []))
            ->add('description', TextType::class, ['label' => 'À quoi il ressemble ?', 'required' => false, 'attr' => ['placeholder' => 'Roux, petit, à poils longs']])
            ->add('owners', EntityType::class, [
                'class' => Member::class,
                'label' => 'Ses maîtres',
                'help' => 'Prévenus d’abord pour ses tâches ; quand aucun n’est là, les autres colocs présents prennent le relais.',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $members): QueryBuilder => $members->createQueryBuilder('m')
                    ->andWhere('m.household = :household')
                    ->setParameter('household', $household)
                    ->orderBy('m.name', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Pet::class,
            'new' => true,
            'empty_data' => static fn (FormInterface $form): Pet => new Pet(
                $form->getConfig()->getOption('household'),
                (string) $form->get('name')->getData(),
                $form->get('species')->getData() ?? PetSpecies::Cat,
            ),
        ]);
        $resolver->setRequired('household');
        $resolver->setAllowedTypes('household', Household::class);
        $resolver->setAllowedTypes('new', 'bool');
    }
}
