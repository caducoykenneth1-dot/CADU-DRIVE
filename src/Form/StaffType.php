<?php

namespace App\Form;

use App\Entity\Staff;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class StaffType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('department', TextType::class, [
                'label' => 'Department',
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('hireDate', DateType::class, [
                'label' => 'Hire Date',
                'required' => false,
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ]);
        
        // Add User fields conditionally
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($options) {
            $staff = $event->getData();
            $form = $event->getForm();
            
            $user = $staff?->getUser() ?? new User();
            
            // Only show these fields to admins
            if ($options['is_admin'] ?? false) {
                $form->add('user_email', EmailType::class, [
                    'label' => 'Email',
                    'data' => $user->getEmail(),
                    'mapped' => false,
                    'attr' => ['class' => 'form-control'],
                ])
                ->add('user_roles', ChoiceType::class, [
                    'label' => 'Roles',
                    'data' => $user->getRoles(),
                    'choices' => [
                        'Administrator' => 'ROLE_ADMIN',
                        'Staff Member' => 'ROLE_STAFF',
                        'User' => 'ROLE_USER',
                    ],
                    'multiple' => true,
                    'expanded' => true,
                    'mapped' => false,
                    'attr' => ['class' => 'form-check-input'],
                    'label_attr' => ['class' => 'form-check-label'],
                ])
                ->add('user_password', PasswordType::class, [
                    'label' => 'Password (leave blank to keep current)',
                    'required' => false,
                    'mapped' => false,
                    'attr' => ['class' => 'form-control'],
                ])
                ->add('status', ChoiceType::class, [
                    'label' => 'Status',
                    'choices' => [
                        'Active' => 'active',
                        'Disabled' => 'disabled',
                        'Archived' => 'archived',
                    ],
                    'attr' => ['class' => 'form-control'],
                ])
                ->add('isActive', CheckboxType::class, [
                    'label' => 'Account Active',
                    'required' => false,
                    'attr' => ['class' => 'form-check-input'],
                    'label_attr' => ['class' => 'form-check-label'],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Staff::class,
            'is_admin' => false, // Default to false
        ]);
    }
}