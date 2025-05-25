<?php

namespace App\Form;

use App\Entity\Dragdrop;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DragdropType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('phrase', TextType::class, [
                'label' => 'Phrase',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('arabicTranslation', TextType::class, [
                'label' => 'Traduction Arabe',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('niveau', ChoiceType::class, [
                'label' => 'Niveau',
                'choices' => [
                    'Niveau 1' => 1,
                    'Niveau 2' => 2,
                    'Niveau 3' => 3,
                ],
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('langue', ChoiceType::class, [
                'label' => 'Langue',
                'choices' => [
                    'Français' => 'Français',
                    'Anglais' => 'Anglais',
                    'Espagnol' => 'Espagnol',
                    'Allemand' => 'Allemand',
                ],
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Dragdrop::class,
        ]);
    }
}