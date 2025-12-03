<?php

namespace App\Controller;

use App\Form\AdminSettingsFormType;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AdminSettingsController extends AbstractController
{
    #[Route('/admin/settings', name: 'app_admin_settings')]
    public function index(Request $request, SettingsService $settingsService): Response
    {
        // Get all current settings
        $currentSettings = $settingsService->all();

        // Create form with current values
        $form = $this->createForm(AdminSettingsFormType::class, $currentSettings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            // Save each setting with appropriate type
            $settingsService->set('SYSTEM_MAINTENANCE_MODE', $data['SYSTEM_MAINTENANCE_MODE'], 'boolean');
            $settingsService->set('SYSTEM_DEFAULT_TIMEZONE', $data['SYSTEM_DEFAULT_TIMEZONE'], 'string');
            $settingsService->set('FINANCE_CURRENCY_SYMBOL', $data['FINANCE_CURRENCY_SYMBOL'], 'string');
            $settingsService->set('FINANCE_TAX_RATE_PERCENT', $data['FINANCE_TAX_RATE_PERCENT'], 'float');
            $settingsService->set('LATE_RETURN_FEE_HOURLY', $data['LATE_RETURN_FEE_HOURLY'], 'float');
            $settingsService->set('BOOKING_MIN_PERIOD_HOURS', $data['BOOKING_MIN_PERIOD_HOURS'], 'integer');
            $settingsService->set('RENTAL_BUFFER_HOURS', $data['RENTAL_BUFFER_HOURS'], 'integer');
            $settingsService->set('RENTAL_ADVANCE_NOTICE_HOURS', $data['RENTAL_ADVANCE_NOTICE_HOURS'], 'integer');
            $settingsService->set('EMAIL_SENDER_ADDRESS', $data['EMAIL_SENDER_ADDRESS'], 'string');
            $settingsService->set('EMAIL_ADMIN_ALERT_ADDRESS', $data['EMAIL_ADMIN_ALERT_ADDRESS'], 'string');

            // Clear cache to reload fresh settings
            $settingsService->clearCache();

            $this->addFlash('success', 'Settings have been updated successfully!');
            return $this->redirectToRoute('app_admin_settings');
        }

        return $this->render('admin/settings.html.twig', [
            'form' => $form,
            'settings' => $currentSettings,
        ]);
    }
}