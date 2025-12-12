<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use App\Form\CustomerType;  // Add this import



class CustomerProfileController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {}
    
    /* My Profile - for customers to view their own profile */
    #[Route('/my-profile', name: 'app_customer_my_profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function myProfile(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Method 1: Get customer from user relationship
        $customer = $user->getCustomer();
        
        // Method 2: If not linked, find by email
        if (!$customer) {
            $customerRepository = $entityManager->getRepository(Customer::class);
            $customer = $customerRepository->findOneBy(['email' => $user->getEmail()]);
        }
        
        // Method 3: If still not found, check database manually
        if (!$customer) {
            $customer = $entityManager->getRepository(Customer::class)
                ->createQueryBuilder('c')
                ->where('c.email = :email')
                ->setParameter('email', $user->getEmail())
                ->getQuery()
                ->getOneOrNullResult();
        }
        
        // If customer exists but not linked to user, link them
        if ($customer && !$customer->getUser()) {
            $customer->setUser($user);
            $entityManager->flush();
        }
        
        // If no customer profile found
        // If no customer profile found
            if (!$customer) {
                $this->addFlash('error', 'No customer profile found. Please create a customer profile.');
                return $this->redirectToRoute('app_register');
            }
        
        // Get customer's rental history
        $rentalRequests = $entityManager->getRepository(\App\Entity\RentalRequest::class)
            ->findBy(
                ['customer' => $customer],
                ['createdAt' => 'DESC']
            );
        
     
        
        // Calculate rental statistics
        $totalRentals = count($rentalRequests);
        $approvedRentals = 0;
        $pendingRentals = 0;
        $rejectedRentals = 0;
        
        foreach ($rentalRequests as $rental) {
            switch ($rental->getStatus()) {
                case 'approved':
                    $approvedRentals++;
                    break;
                case 'pending':
                    $pendingRentals++;
                    break;
                case 'rejected':
                    $rejectedRentals++;
                    break;
            }
        }
        
        return $this->render('customer_profile/index.html.twig', [
            'customer' => $customer,
            'rental_requests' => $rentalRequests,
            'total_rentals' => $totalRentals,
            'approved_rentals' => $approvedRentals,
            'pending_rentals' => $pendingRentals,
            'rejected_rentals' => $rejectedRentals,
            'can_edit' => true // Customer can always edit their own profile
        ]);
    }

    /* Change Password - FIXED VERSION */
    #[Route('/profile/change-password', name: 'app_customer_change_password', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        
        // Create form for GET requests
        $form = $this->createFormBuilder()
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current Password',
                'attr' => ['placeholder' => 'Enter your current password']
            ])
            ->add('newPassword', PasswordType::class, [
                'label' => 'New Password',
                'attr' => ['placeholder' => 'Enter new password (min 8 characters)']
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Confirm New Password',
                'attr' => ['placeholder' => 'Confirm new password']
            ])
            ->getForm();
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $currentPassword = $data['currentPassword'];
            $newPassword = $data['newPassword'];
            $confirmPassword = $data['confirmPassword'];
            
            // Validate CSRF token (automatic with Symfony forms)
            
            // Check if passwords match
            if ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'New passwords do not match.');
                return $this->render('customer/change_password.html.twig', [
                    'form' => $form->createView()
                ]);
            }
            
            // Check password length
            if (strlen($newPassword) < 8) {
                $this->addFlash('error', 'Password must be at least 8 characters long.');
                return $this->render('customer/change_password.html.twig', [
                    'form' => $form->createView()
                ]);
            }
            
            // Verify current password
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Current password is incorrect.');
                return $this->render('customer/change_password.html.twig', [
                    'form' => $form->createView()
                ]);
            }
            
            // Hash and set new password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            
            $entityManager->flush();
            
            // Log password change
            $this->activityLogger->log(
                $user->getEmail(),
                'CHANGE_PASSWORD',
                'Changed account password',
                [
                    'user_email' => $user->getEmail(),
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                ],
                'USER'
            );
            
            $this->addFlash('success', 'Password changed successfully!');
            
            // Redirect to customer profile
            $customer = $user->getCustomer();
            if ($customer) {
                return $this->redirectToRoute('app_customer_my_profile');
            }
            return $this->redirectToRoute('app_customer_register');
        }
        
        // GET request - show form
        return $this->render('customer_profile/change_password.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /* Editing Customer Profile */
#[Route('/profile/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
{
    // Check authorization
    $user = $this->getUser();
    $isOwnProfile = $user && $user->getCustomer() && $user->getCustomer()->getId() === $customer->getId();
    $isAdmin = $this->isGranted('ROLE_ADMIN');
    $isStaff = $this->isGranted('ROLE_STAFF');
    
    // Only allow editing if it's their own profile, or they're admin/staff
    if (!$isOwnProfile && !$isAdmin && !$isStaff) {
        throw $this->createAccessDeniedException('You cannot edit this customer profile.');
    }
    
    // Store original data for logging
    $originalData = [
        'firstName' => $customer->getFirstName(),
        'lastName' => $customer->getLastName(),
        'email' => $customer->getEmail(),
        'phone' => $customer->getPhone(),
        'licenseNumber' => $customer->getLicenseNumber()
    ];
    
    $form = $this->createForm(CustomerType::class, $customer);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $changes = [];
        $changedFields = [];
        
        // Check for changes
        if ($originalData['firstName'] !== $customer->getFirstName()) {
            $changes['firstName'] = [
                'from' => $originalData['firstName'],
                'to' => $customer->getFirstName()
            ];
            $changedFields[] = 'First Name';
        }
        
        if ($originalData['lastName'] !== $customer->getLastName()) {
            $changes['lastName'] = [
                'from' => $originalData['lastName'],
                'to' => $customer->getLastName()
            ];
            $changedFields[] = 'Last Name';
        }
        
        if ($originalData['email'] !== $customer->getEmail()) {
            $changes['email'] = [
                'from' => $originalData['email'],
                'to' => $customer->getEmail()
            ];
            $changedFields[] = 'Email';
        }
        
        if ($originalData['phone'] !== $customer->getPhone()) {
            $changes['phone'] = [
                'from' => $originalData['phone'],
                'to' => $customer->getPhone()
            ];
            $changedFields[] = 'Phone';
        }                           
        
        if ($originalData['licenseNumber'] !== $customer->getLicenseNumber()) {
            $changes['licenseNumber'] = [
                'from' => $originalData['licenseNumber'],
                'to' => $customer->getLicenseNumber()
            ];
            $changedFields[] = 'License Number';
        }
       
        $customer->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();
        
        // Refresh user entity if editing own profile
        if ($isOwnProfile && $user) {
            $entityManager->refresh($user);
        }
        
        // Log update activity if there were changes
        if (!empty($changedFields)) {
            $currentUserEmail = $user ? $user->getEmail() : 'anonymous';
            $userType = $user ? ($this->isGranted('ROLE_ADMIN') ? 'ADMIN' : 
                         ($this->isGranted('ROLE_STAFF') ? 'STAFF' : 'USER')) : 'GUEST';
            
            $this->activityLogger->log(
                $currentUserEmail,
                'UPDATE_CUSTOMER',
                'Updated customer: ' . $customer->getFirstName() . ' ' . $customer->getLastName() . 
                ' (' . implode(', ', $changedFields) . ')',
                [
                    'customer_id' => $customer->getId(),
                    'customer_name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
                    'changes' => $changes,
                    'changed_fields' => $changedFields,
                    'updated_by' => $currentUserEmail,
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                ],
                $userType
            );
        }

        $this->addFlash('success', 'Customer profile updated.');

        // Redirect based on who is editing
        if ($isOwnProfile) {
            // Customer editing their own profile
            return $this->redirectToRoute('app_customer_my_profile');
        } else {
            // Admin/Staff editing a customer
            return $this->redirectToRoute('app_customer_index');
        }
    }

    return $this->render('customer_profile/edit_profile.html.twig', [
        'customer' => $customer,
        'form' => $form->createView(),
    ]);
}
}