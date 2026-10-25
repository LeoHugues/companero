<?php

namespace App\Form;

use App\Entity\Participant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * "Pour qui": one line per participant of the account, named by their id.
 *
 * @extends AbstractType<array<int, \App\Ledger\ShareInput>>
 */
class SplitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<Participant> $participants */
        $participants = $options['participants'];
        foreach ($participants as $participant) {
            $builder->add((string) $participant->getId(), ShareInputType::class, [
                'label' => $participant->getName(),
                'property_path' => '['.$participant->getId().']',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['label' => false, 'error_bubbling' => false]);
        $resolver->setRequired('participants');
        $resolver->setAllowedTypes('participants', 'array');
    }
}
