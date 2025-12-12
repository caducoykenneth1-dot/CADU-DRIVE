<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'First name is required']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter first name',
                ],
                'label' => 'First Name',
            ])
            ->add('lastName', TextType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Last name is required']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter last name',
                ],
                'label' => 'Last Name',
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(['message' => 'Email is required']),
                    new Email(['message' => 'Please enter a valid email address']),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter email address',
                ],
                'label' => 'Email Address',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}