<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ChangePasswordController extends AbstractController
{
    #[Route('/admin/change-password', name: 'app_admin_change_password')]
    public function index(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // 1. Check if user is logged in
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        /** @var User $user */
        $user = $this->getUser();
        
        // 2. Create the form
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);
        
        // 3. Handle form submission
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $currentPassword = $form->get('currentPassword')->getData();
            
            // Verify current password
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Current password is incorrect.');
                return $this->redirectToRoute('app_admin_change_password');
            }
            
            // Get new password from form
            $newPassword = $data['newPassword'];
            
            // Hash and set new password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            
            // Save to database
            $entityManager->persist($user);
            $entityManager->flush();
            
            // Success message
            $this->addFlash('success', 'Password changed successfully!');
            return $this->redirectToRoute('app_admin_dashboard'); // Redirect to admin dashboard
        }
        
        // 4. Render the template
        // In ChangePasswordController.php
         return $this->render('change_password/index.html.twig', [  // ← Remove "admin/"
    'form' => $form->createView(),
]);
    }
}