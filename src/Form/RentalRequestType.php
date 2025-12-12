<?php
namespace App\Form;

use App\Entity\RentalRequest;
use App\Entity\Customer;
use App\Entity\Car;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class RentalRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Start Date',
                'required' => true,
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'End Date',
                'required' => true,
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'label' => 'Additional Notes',
                'attr' => ['rows' => 4]
            ]);
        
        // Only add customer, car, and status fields for staff/admin
        if ($options['is_staff']) {
            $builder
                ->add('customer', EntityType::class, [
                    'class' => Customer::class,
                    'choice_label' => function(Customer $customer) {
                        return $customer->getFirstName() . ' ' . $customer->getLastName() . ' (' . $customer->getEmail() . ')';
                    },
                    'label' => 'Customer',
                    'placeholder' => 'Select a customer',
                    'required' => true,
                ])
                ->add('car', EntityType::class, [
                    'class' => Car::class,
                    'choice_label' => function(Car $car) {
                        return $car->getMake() . ' ' . $car->getModel() . ' (' . $car->getYear() . ') - $' . $car->getPrice() . '/day';
                    },
                    'label' => 'Car',
                    'placeholder' => 'Select a car',
                    'required' => true,
                ])
                ->add('status', ChoiceType::class, [
                    'label' => 'Status',
                    'choices' => [
                        'Pending' => 'pending',
                        'Approved' => 'approved',
                        'Rejected' => 'rejected',
                    ],
                    'required' => true,
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RentalRequest::class,
            'is_staff' => false,
        ]);
        
        $resolver->setAllowedTypes('is_staff', 'bool');
    }
}