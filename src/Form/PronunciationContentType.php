<?php

namespace App\Form;

use App\Entity\PronunciationContent;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Choice;

class PronunciationContentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextType::class, [
                'label' => 'Contenu (lettre, mot ou phrase)',
                'attr' => ['placeholder' => 'Ex. : A, chat, Bonjour !'],
                'constraints' => [
                    new NotBlank(['message' => 'Le contenu ne peut pas être vide.']),
                ],
            ])
            ->add('level', ChoiceType::class, [
                'label' => 'Niveau',
                'choices' => [
                    'Alphabet' => 1,
                    'Mots' => 2,
                    'Phrases' => 3,
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un niveau.']),
                    new Choice(['choices' => [1, 2, 3], 'message' => 'Niveau invalide.']),
                ],
            ])
            ->add('langue', ChoiceType::class, [
                'label' => 'Langue',
                'choices' => [
                    'Français' => 'Français',
                    'Anglais' => 'Anglais',
                    'Espagnol' => 'Espagnol',
                    'Allemand'=> 'Allemand',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner une langue.']),
                    new Choice(['choices' => ['Français', 'Anglais', 'Espagnol','Allemand'], 'message' => 'Langue invalide.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PronunciationContent::class,
        ]);
    }
}