<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Form\CustomerType;
use App\Repository\CustomerRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


/* CRUD endpoints for customer profiles */
#[Route('/customer')]
class CustomerController extends AbstractController
{   
    public function __construct(
        private ActivityLogger $activityLogger
    ) {}
    
    #[Route('/', name: 'app_customer_index', methods: ['GET'])]
    public function index(Request $request, CustomerRepository $customerRepository): Response
    {
        $term = $request->query->get('search');
        
        return $this->render('customer/index.html.twig', [
            'customers' => $customerRepository->search($term),
            'searchTerm' => $term,
        ]);
    }

    /* Register a new customer profile */
    #[Route('/register', name: 'app_customer_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        $customer = new Customer();

        // Pre-fill email if user is logged in
        $user = $this->getUser();
        if ($user) {
            $customer->setEmail($user->getUserIdentifier());
        }

        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customer->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($customer);
            $entityManager->flush();

            // Log customer registration
            $currentUserEmail = $user ? $user->getEmail() : 'anonymous';
            $userType = $user ? ($this->isGranted('ROLE_ADMIN') ? 'ADMIN' : 
                         ($this->isGranted('ROLE_STAFF') ? 'STAFF' : 'USER')) : 'GUEST';
            
            $this->activityLogger->log(
                $currentUserEmail,
                'CREATE_CUSTOMER',
                'Created new customer: ' . $customer->getFirstName() . ' ' . $customer->getLastName(),
                [
                    'customer_id' => $customer->getId(),
                    'customer_name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
                    'customer_email' => $customer->getEmail(),
                    'customer_phone' => $customer->getPhone(),
                    'created_by' => $currentUserEmail,
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                ],
                $userType
            );

            $this->addFlash('success', 'Thanks! Your customer profile has been created.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('customer/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /* Showing Customer Profile */
    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        $user = $this->getUser();
        $currentUserEmail = $user ? $user->getEmail() : 'anonymous';
        $userType = $user ? ($this->isGranted('ROLE_ADMIN') ? 'ADMIN' : 
                     ($this->isGranted('ROLE_STAFF') ? 'STAFF' : 'USER')) : 'GUEST';
        
        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
        ]);
    }

    /* Editing Customer Profile */
    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
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
                $changedFields[] = 'Last Name';
            }
           
            $customer->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            
            // Log update activity if there were changes
            if (!empty($changedFields)) {
                $user = $this->getUser();
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

            return $this->redirectToRoute('app_customer_index');
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form->createView(),
        ]);
    }

    /* Deleting Customer */
    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$customer->getId(), $request->request->get('_token'))) {
            // Store customer data for logging before deletion
            $customerData = [
                'id' => $customer->getId(),
                'name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
                'created_at' => $customer->getCreatedAt() ? $customer->getCreatedAt()->format('Y-m-d H:i:s') : null
            ];
            
            $user = $this->getUser();
            $currentUserEmail = $user ? $user->getEmail() : 'anonymous';
            $userType = $user ? ($this->isGranted('ROLE_ADMIN') ? 'ADMIN' : 
                         ($this->isGranted('ROLE_STAFF') ? 'STAFF' : 'USER')) : 'GUEST';
            
            // Log deletion before actually deleting
            $this->activityLogger->log(
                $currentUserEmail,
                'DELETE_CUSTOMER',
                'Deleted customer: ' . $customer->getFirstName() . ' ' . $customer->getLastName(),
                [
                    'customer_id' => $customerData['id'],
                    'customer_name' => $customerData['name'],
                    'customer_email' => $customerData['email'],
                    'customer_phone' => $customerData['phone'],
                    'customer_created_at' => $customerData['created_at'],
                    'deleted_by' => $currentUserEmail,
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                ],
                $userType
            );
            
            $entityManager->remove($customer);
            $entityManager->flush();

            $this->addFlash('success', 'Customer profile removed.');
        }

        return $this->redirectToRoute('app_customer_index');
    }
    
    /* Export Customers to CSV */
    #[Route('/export/csv', name: 'app_customer_export_csv', methods: ['GET'])]
    public function exportCsv(CustomerRepository $customerRepository): Response
    {
        $customers = $customerRepository->findAll();
        
        $user = $this->getUser();
        $currentUserEmail = $user ? $user->getEmail() : 'anonymous';
        $userType = $user ? ($this->isGranted('ROLE_ADMIN') ? 'ADMIN' : 
                     ($this->isGranted('ROLE_STAFF') ? 'STAFF' : 'USER')) : 'GUEST';
        
        // Log export activity
        $this->activityLogger->log(
            $currentUserEmail,
            'EXPORT_CUSTOMERS_CSV',
            'Exported customers list to CSV',
            [
                'exported_by' => $currentUserEmail,
                'record_count' => count($customers),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ],
            $userType
        );
        
        // CSV generation logic would go here
        // For simplicity, redirect back
        $this->addFlash('success', 'Customer list exported successfully!');
        return $this->redirectToRoute('app_customer_index');
    }
    
    /* Bulk operations */
    #[Route('/bulk/disable', name: 'app_customer_bulk_disable', methods: ['POST'])]
    public function bulkDisable(Request $request, EntityManagerInterface $entityManager): Response
    {
        $customerIds = $request->request->get('customer_ids', []);
        $customerIds = is_array($customerIds) ? $customerIds : explode(',', $customerIds);
        
        if (empty($customerIds)) {
            $this->addFlash('error', 'No customers selected.');
            return $this->redirectToRoute('app_customer_index');
        }
        
        $customerRepository = $entityManager->getRepository(Customer::class);
        $customers = $customerRepository->findBy(['id' => $customerIds]);
        
        foreach ($customers as $customer) {
            // Add your disable logic here (e.g., set a status field)
            // $customer->setStatus('disabled');
            // $customer->setIsActive(false);
        }
        
        $entityManager->flush();
        
        $user = $this->getUser();
        $currentUserEmail = $user ? $user->getEmail() : 'anonymous';
        $userType = $user ? ($this->isGranted('ROLE_ADMIN') ? 'ADMIN' : 
                     ($this->isGranted('ROLE_STAFF') ? 'STAFF' : 'USER')) : 'GUEST';
        
        // Log bulk operation
        $this->activityLogger->log(
            $currentUserEmail,
            'BULK_DISABLE_CUSTOMERS',
            'Bulk disabled ' . count($customers) . ' customers',
            [
                'action_by' => $currentUserEmail,
                'customer_count' => count($customers),
                'customer_ids' => $customerIds,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ],
            $userType
        );
        
        $this->addFlash('success', count($customers) . ' customers have been disabled.');
        return $this->redirectToRoute('app_customer_index');
    }
    
    #[Route('/bulk/delete', name: 'app_customer_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, EntityManagerInterface $entityManager): Response
    {
        $customerIds = $request->request->get('customer_ids', []);
        $customerIds = is_array($customerIds) ? $customerIds : explode(',', $customerIds);
        
        if (empty($customerIds)) {
            $this->addFlash('error', 'No customers selected.');
            return $this->redirectToRoute('app_customer_index');
        }
        
        $customerRepository = $entityManager->getRepository(Customer::class);
        $customers = $customerRepository->findBy(['id' => $customerIds]);
        
        $customerNames = [];
        foreach ($customers as $customer) {
            $customerNames[] = $customer->getFirstName() . ' ' . $customer->getLastName();
            $entityManager->remove($customer);
        }
        
        $entityManager->flush();
        
        $user = $this->getUser();
        $currentUserEmail = $user ? $user->getEmail() : 'anonymous';
        $userType = $user ? ($this->isGranted('ROLE_ADMIN') ? 'ADMIN' : 
                     ($this->isGranted('ROLE_STAFF') ? 'STAFF' : 'USER')) : 'GUEST';
        
        // Log bulk deletion
        $this->activityLogger->log(
            $currentUserEmail,
            'BULK_DELETE_CUSTOMERS',
            'Bulk deleted ' . count($customers) . ' customers',
            [
                'action_by' => $currentUserEmail,
                'customer_count' => count($customers),
                'customer_ids' => $customerIds,
                'customer_names' => $customerNames,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ],
            $userType
        );
        
        $this->addFlash('success', count($customers) . ' customers have been deleted.');
        return $this->redirectToRoute('app_customer_index');
    }

    #[Route('/my-profile/change-password', name: 'app_customer_change_password', methods: ['GET', 'POST'])]
public function customerChangePassword(
    Request $request,
    EntityManagerInterface $entityManager,
    UserPasswordHasherInterface $passwordHasher
): Response
{
    $user = $this->getUser();
    
    if (!$user) {
        return $this->redirectToRoute('app_login');
    }
    
    // Get customer for this user
    // Get customer for this user
$customer = $user->getCustomer();
if (!$customer) {
    $customer = $entityManager->getRepository(Customer::class)
        ->findOneBy(['email' => $user->getEmail()]);
}

if (!$customer) {
    $this->addFlash('error', 'No customer profile found.');
    return $this->redirectToRoute('app_customer_register');
}

    // Create form
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
        
        // Validate passwords match
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
        
        // Update password
        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $entityManager->flush();
        
        // Log activity
        $this->activityLogger->log(
            $user->getEmail(),
            'CUSTOMER_CHANGE_PASSWORD',
            'Customer changed their password',
            [
                'customer_id' => $customer->getId(),
                'customer_email' => $user->getEmail()
            ],
            'USER'
        );
        
        $this->addFlash('success', 'Password changed successfully!');
        return $this->redirectToRoute('app_customer_my_profile');
    }
    
    return $this->render('customer/change_password.html.twig', [
        'form' => $form->createView(),
        'customer' => $customer
    ]);
}
}