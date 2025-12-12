<?php

namespace App\Form;

use App\Entity\Customer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CustomerType extends AbstractType
{
    private Security $security;

    // Inject the Security service
    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Get the logged-in user's email
        $user = $this->security->getUser();
        $email = $user ? $user->getUserIdentifier() : '';

        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'data' => $email,    // pre-fill the email
                'disabled' => true,  // make it read-only
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'required' => false,
            ])
            ->add('licenseNumber', TextType::class, [
                'label' => 'Driver License',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Customer::class,
        ]);
    }
}
