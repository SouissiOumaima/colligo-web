<?php

namespace App\Form;

use App\Entity\Child;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class ChildType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est requis.']),
                ],
            ])
            ->add('age', IntegerType::class, [
                'label' => 'Âge',
                'constraints' => [
                    new NotBlank(['message' => 'L\'âge est requis.']),
                    new Positive(['message' => 'L\'âge doit être positif.']),
                ],
            ])
            ->add('language', ChoiceType::class, [
                'label' => 'Langue',
                'choices' => [
                    'Français' => 'fr',
                    'Anglais' => 'en',
                    'Espagnol' => 'es',
                    'Allemand' => 'de',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'La langue est requise.']),
                ],
            ])
            ->add('avatar', ChoiceType::class, [
                'choices' => [
                    'Avatar 1' => 'avatar1',
                    'Avatar 2' => 'avatar2',
                    'Avatar 3' => 'avatar3',
                    'Avatar 4' => 'avatar4',
                    'Avatar 5' => 'avatar5',
                    'Avatar 6' => 'avatar6',
                    'Avatar 7' => 'avatar7',
                    'Avatar 8' => 'avatar8',
                    'Avatar 9' => 'avatar9',
                    'Avatar 10' => 'avatar10',
                    'Avatar 11' => 'avatar11',
                    'Avatar 12' => 'avatar12',
                    'Avatar 13' => 'avatar13',
                ],
                'label' => 'Choisis un avatar',
                'constraints' => [
                    new NotBlank(['message' => 'Un avatar est requis.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Child::class,
        ]);
    }
}