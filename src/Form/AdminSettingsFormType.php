<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * MVP Settings Form - Minimum required fields for booking workflow
 */
final class AdminSettingsFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // System & Status Settings
        $builder->add('SYSTEM_MAINTENANCE_MODE', CheckboxType::class, [
            'label' => 'Maintenance Mode',
            'required' => false,
            'help' => 'Take the site offline for maintenance or updates',
        ]);

        $builder->add('SYSTEM_DEFAULT_TIMEZONE', TimezoneType::class, [
            'label' => 'Default Timezone',
            'help' => 'Standard timezone for all rental dates and times',
        ]);

        // Financial Settings
        $builder->add('FINANCE_CURRENCY_SYMBOL', TextType::class, [
            'label' => 'Currency Symbol',
            'help' => 'Symbol displayed in price fields (e.g., $, €, £)',
            'attr' => ['maxlength' => 5, 'placeholder' => '$'],
        ]);

        $builder->add('FINANCE_TAX_RATE_PERCENT', NumberType::class, [
            'label' => 'Tax Rate (%)',
            'help' => 'Global tax/VAT percentage applied to all rental totals',
            'scale' => 2,
            'attr' => ['min' => 0, 'max' => 100, 'step' => 0.01],
        ]);

        $builder->add('LATE_RETURN_FEE_HOURLY', NumberType::class, [
            'label' => 'Hourly Late Return Fee',
            'help' => 'Penalty charge for each hour a vehicle is returned late',
            'scale' => 2,
            'attr' => ['min' => 0, 'step' => 0.01],
        ]);

        // Booking Logic Settings
        $builder->add('BOOKING_MIN_PERIOD_HOURS', NumberType::class, [
            'label' => 'Minimum Booking Period (hours)',
            'help' => 'Shortest rental duration allowed',
            'scale' => 0,
            'attr' => ['min' => 1, 'step' => 1],
        ]);

        $builder->add('RENTAL_BUFFER_HOURS', NumberType::class, [
            'label' => 'Preparation Buffer (hours)',
            'help' => 'Required downtime between consecutive rentals for vehicle preparation',
            'scale' => 0,
            'attr' => ['min' => 0, 'step' => 1],
        ]);

        $builder->add('RENTAL_ADVANCE_NOTICE_HOURS', NumberType::class, [
            'label' => 'Minimum Advance Notice (hours)',
            'help' => 'Lead time required before a vehicle can be picked up',
            'scale' => 0,
            'attr' => ['min' => 0, 'step' => 1],
        ]);

        // Email Settings
        $builder->add('EMAIL_SENDER_ADDRESS', EmailType::class, [
            'label' => 'System Sender Email',
            'help' => 'From address for automated emails (confirmations, notifications)',
            'attr' => ['placeholder' => 'noreply@domain.com'],
        ]);

        $builder->add('EMAIL_ADMIN_ALERT_ADDRESS', EmailType::class, [
            'label' => 'Admin Alert Email',
            'help' => 'Recipient for staff notifications (new bookings, issues)',
            'attr' => ['placeholder' => 'admin@domain.com'],
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'Save Settings',
            'attr' => ['class' => 'px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label_attr' => ['class' => 'block text-sm font-medium text-gray-300 mb-1'],
            'attr' => ['class' => 'w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500'],
        ]);
    }
}
