<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Customer;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $userPasswordHasher, 
        EntityManagerInterface $entityManager
    ): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Get the form data
            $firstName = $form->get('firstName')->getData();
            $lastName = $form->get('lastName')->getData();
            $phone = $form->get('phone')->getData();

            // Create a Customer entity and link it to the User
            $customer = new Customer();
            $customer->setEmail($user->getEmail());
            $customer->setFirstName($firstName);
            $customer->setLastName($lastName);
            $customer->setPhone($phone);
            $customer->setUser($user);
            $customer->setCreatedAt(new \DateTimeImmutable());
            $customer->setUpdatedAt(new \DateTimeImmutable());

            // Set user roles
            $user->setRoles(['ROLE_CUSTOMER']);

            // Persist both entities
            $entityManager->persist($user);
            $entityManager->persist($customer);
            $entityManager->flush();

            // Add flash message
            $this->addFlash('success', 'Registration successful! You can now log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}