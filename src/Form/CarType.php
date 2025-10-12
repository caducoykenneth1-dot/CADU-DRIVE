<?php

namespace App\Form;

use App\Entity\Car;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('make')
            ->add('model')
            ->add('description')
            ->add('year')
            ->add('price')
            ->add('image', FileType::class, [
                'label' => 'Car Image (JPG or PNG file)',
                'mapped' => false, // as we will handle the file upload manually
                'required' => false, // image is not mandatory
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Car::class,
        ]);
    }
}