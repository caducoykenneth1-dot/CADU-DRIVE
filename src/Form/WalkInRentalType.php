<?php

namespace App\Form;

use App\Entity\RentalRequest;
use App\Entity\Customer;
use App\Entity\Car;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;

class WalkInRentalType extends AbstractType
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Get available cars for the dropdown
        $availableStatus = $this->entityManager->getRepository(\App\Entity\CarStatus::class)
            ->findOneBy(['code' => 'available']);
        
        $availableCars = $availableStatus ? 
            $this->entityManager->getRepository(Car::class)->findBy(['status' => $availableStatus], ['make' => 'ASC', 'model' => 'ASC']) : 
            [];

        // Get existing customers for quick selection
        $existingCustomers = $this->entityManager->getRepository(Customer::class)
            ->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC']);

        $builder
            // Customer Type Selection (Existing or New)
            ->add('customerType', ChoiceType::class, [
                'mapped' => false,
                'choices' => [
                    'New Customer (Walk-in)' => 'new',
                    'Existing Customer' => 'existing'
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'new', // Default to new customer
                'label' => 'Customer Type',
                'attr' => [
                    'class' => 'customer-type-radio'
                ]
            ])
            
            // Existing Customer Dropdown (Hidden by default)
            ->add('existingCustomer', EntityType::class, [
                'class' => Customer::class,
                'choices' => $existingCustomers,
                'choice_label' => function(Customer $customer) {
                    return sprintf('%s %s (%s) - %s', 
                        $customer->getFirstName(), 
                        $customer->getLastName(), 
                        $customer->getEmail(),
                        $customer->getPhone() ?: 'No phone'
                    );
                },
                'placeholder' => 'Select an existing customer...',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'class' => 'form-control select2 existing-customer-field',
                    'data-placeholder' => 'Select an existing customer...',
                    'style' => 'display: none;'
                ],
                'label' => 'Select Existing Customer'
            ])
            
            // Customer Information Fields (For new customers)
            ->add('customerFirstName', TextType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'First name is required']),
                    new Length(['min' => 2, 'max' => 100])
                ],
                'attr' => [
                    'class' => 'form-control new-customer-field',
                    'placeholder' => 'Enter first name'
                ],
                'label' => 'First Name'
            ])
            ->add('customerLastName', TextType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Last name is required']),
                    new Length(['min' => 2, 'max' => 100])
                ],
                'attr' => [
                    'class' => 'form-control new-customer-field',
                    'placeholder' => 'Enter last name'
                ],
                'label' => 'Last Name'
            ])
            ->add('customerEmail', EmailType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Email is required']),
                    new Email(['message' => 'Please enter a valid email address']),
                    new Length(['max' => 180])
                ],
                'attr' => [
                    'class' => 'form-control new-customer-field',
                    'placeholder' => 'email@example.com'
                ],
                'label' => 'Email'
            ])
            ->add('customerPhone', TelType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Length(['max' => 30])
                ],
                'attr' => [
                    'class' => 'form-control new-customer-field',
                    'placeholder' => '(123) 456-7890'
                ],
                'label' => 'Phone (Optional)'
            ])
            ->add('customerLicense', TextType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Length(['max' => 60])
                ],
                'attr' => [
                    'class' => 'form-control new-customer-field',
                    'placeholder' => 'Driver license number'
                ],
                'label' => 'License Number (Optional)'
            ])
            ->add('customerNotes', TextareaType::class, [
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control new-customer-field',
                    'rows' => 2,
                    'placeholder' => 'Any customer notes...'
                ],
                'label' => 'Customer Notes (Optional)'
            ])
            
            // Car Selection
            ->add('car', EntityType::class, [
                'class' => Car::class,
                'choices' => $availableCars,
                'choice_label' => function(Car $car) {
                    $dailyRate = $car->getDailyRate() ?? $car->getPrice() ?? 0;
                    return sprintf('%s %s %s - $%s/day - %s', 
                        $car->getYear(), 
                        $car->getMake(), 
                        $car->getModel(), 
                        number_format($dailyRate, 2),
                        $car->getStatus() ? $car->getStatus()->getLabel() : 'Unknown'
                    );
                },
                'placeholder' => 'Select an available car...',
                'constraints' => [
                    new NotBlank(['message' => 'Please select a car']),
                ],
                'attr' => [
                    'class' => 'form-control select2',
                    'data-placeholder' => 'Select a car...'
                ],
                'label' => 'Select Car'
            ])
            
            // Rental Dates
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Please select a start date']),
                ],
                'attr' => [
                    'class' => 'form-control datepicker',
                    'min' => date('Y-m-d')
                ],
                'label' => 'Start Date'
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Please select an end date']),
                ],
                'attr' => [
                    'class' => 'form-control datepicker',
                    'min' => date('Y-m-d')
                ],
                'label' => 'End Date'
            ])
            
            // Price Display
            ->add('totalPrice', NumberType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                    'placeholder' => 'Will be calculated automatically'
                ],
                'label' => 'Total Price ($)'
            ])
            
            // Rental Notes
            ->add('notes', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Any special instructions or rental notes...'
                ],
                'label' => 'Rental Notes (Optional)'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RentalRequest::class,
            'entity_manager' => null,
        ]);
    }
}