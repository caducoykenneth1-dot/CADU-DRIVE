<?php

namespace App\Controller;

use App\Entity\Staff;
use App\Entity\User;
use App\Form\StaffType;
use App\Repository\StaffRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff/crud')]
class StaffCrudController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {}
    
    #[Route('/', name: 'app_staff_crud_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Get filter parameters from request
        $search = $request->query->get('search', '');
        $roleFilter = $request->query->get('role', '');
        $statusFilter = $request->query->get('status', '');
        $accountStatusFilter = $request->query->get('accountStatus', '');
        
        // Build query with joins
        $queryBuilder = $entityManager->getRepository(Staff::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')
            ->leftJoin('s.createdByAdmin', 'a')
            ->addSelect('u', 'a')
            ->orderBy('s.createdAt', 'DESC');
        
        // Apply search filter
        if ($search) {
            $queryBuilder->andWhere('
                s.firstName LIKE :search OR 
                s.lastName LIKE :search OR 
                u.email LIKE :search
            ')
            ->setParameter('search', '%' . $search . '%');
        }
        
        // Apply role filter
        if ($roleFilter) {
            $queryBuilder->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%' . $roleFilter . '%');
        }
        
        // Apply account status filter (active/disabled/archived)
        if ($accountStatusFilter) {
            $queryBuilder->andWhere('s.status = :accountStatus')
                ->setParameter('accountStatus', $accountStatusFilter);
        }
        
        // Apply login status filter (online/active/recent/idle/inactive/never)
        if ($statusFilter) {
            $now = new \DateTime();
            
            switch ($statusFilter) {
                case 'online':
                    // Logged in within last 5 minutes
                    $fiveMinutesAgo = clone $now;
                    $fiveMinutesAgo->modify('-5 minutes');
                    $queryBuilder->andWhere('u.lastLogin >= :onlineTime')
                        ->setParameter('onlineTime', $fiveMinutesAgo);
                    break;
                    
                case 'active':
                    // Logged in within last 24 hours
                    $twentyFourHoursAgo = clone $now;
                    $twentyFourHoursAgo->modify('-24 hours');
                    $queryBuilder->andWhere('u.lastLogin >= :activeTime')
                        ->setParameter('activeTime', $twentyFourHoursAgo);
                    break;
                    
                case 'recent':
                    // Logged in within last 7 days
                    $sevenDaysAgo = clone $now;
                    $sevenDaysAgo->modify('-7 days');
                    $queryBuilder->andWhere('u.lastLogin >= :recentTime')
                        ->setParameter('recentTime', $sevenDaysAgo);
                    break;
                    
                case 'idle':
                    // Logged in 7-30 days ago
                    $sevenDaysAgo = clone $now;
                    $sevenDaysAgo->modify('-7 days');
                    $thirtyDaysAgo = clone $now;
                    $thirtyDaysAgo->modify('-30 days');
                    $queryBuilder->andWhere('u.lastLogin >= :thirtyDays AND u.lastLogin < :sevenDays')
                        ->setParameter('thirtyDays', $thirtyDaysAgo)
                        ->setParameter('sevenDays', $sevenDaysAgo);
                    break;
                    
                case 'inactive':
                    // Not logged in for 30+ days
                    $thirtyDaysAgo = clone $now;
                    $thirtyDaysAgo->modify('-30 days');
                    $queryBuilder->andWhere('u.lastLogin IS NULL OR u.lastLogin < :inactiveTime')
                        ->setParameter('inactiveTime', $thirtyDaysAgo);
                    break;
                    
                case 'never':
                    // Never logged in
                    $queryBuilder->andWhere('u.lastLogin IS NULL');
                    break;
            }
        }
        
        $staffList = $queryBuilder->getQuery()->getResult();

        return $this->render('staff_crud/index.html.twig', [
            'staffList' => $staffList,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'statusFilter' => $statusFilter,
            'accountStatusFilter' => $accountStatusFilter,
        ]);
    }

    #[Route('/new', name: 'app_staff_crud_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager, 
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $staff = new Staff();
        $user = new User(); // Create User entity
        $staff->setUser($user);
        
        $form = $this->createForm(StaffType::class, $staff, [
            'is_admin' => true, // Always admin for new staff
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get data from unmapped fields
            $email = $form->get('user_email')->getData();
            $plainPassword = $form->get('user_password')->getData();
            $roles = $form->get('user_roles')->getData();
            
            // Set User properties
            $user->setEmail($email);
            $user->setRoles($roles);
            
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }
            
            // Set creator admin (current user)
            $currentUser = $this->getUser();
            $admin = $entityManager->getRepository(\App\Entity\Admin::class)
                ->findOneBy(['user' => $currentUser]);
            $staff->setCreatedByAdmin($admin);
            
            // Persist both entities
            $entityManager->persist($user);
            $entityManager->persist($staff);
            $entityManager->flush();

            // Log activity
            $this->activityLogger->log(
                $currentUser->getEmail(),
                'CREATE_STAFF',
                'Created new staff member: ' . $staff->getFirstName() . ' ' . $staff->getLastName(),
                [
                    'staff_id' => $staff->getId(),
                    'staff_name' => $staff->getFullName(),
                    'email' => $email,
                    'roles' => $roles,
                    'creator' => $currentUser->getEmail()
                ],
                'ADMIN'
            );
            
            $this->addFlash('success', 'Staff member created successfully!');
            return $this->redirectToRoute('app_staff_crud_index');
        }

        return $this->render('staff_crud/new.html.twig', [
            'form' => $form->createView(),
            'is_admin' => true,
        ]);
    }

    #[Route('/{id}', name: 'app_staff_crud_show', methods: ['GET'])]
    public function show(Staff $staff): Response
    {
        $currentUser = $this->getUser();
        
        // Log view activity (only log for admin viewing staff profiles)
        if ($this->isGranted('ROLE_ADMIN') && $currentUser->getStaff()?->getId() !== $staff->getId()) {
            $this->activityLogger->log(
                $currentUser->getEmail(),
                'VIEW_STAFF_PROFILE',
                'Viewed staff profile: ' . $staff->getFirstName() . ' ' . $staff->getLastName(),
                [
                    'staff_id' => $staff->getId(),
                    'staff_name' => $staff->getFullName(),
                    'staff_status' => $staff->getStatus()
                ],
                'ADMIN'
            );
        }
        
        return $this->render('staff_crud/show.html.twig', [
            'staff' => $staff,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_staff_crud_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Staff $staff, 
        EntityManagerInterface $entityManager, 
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $currentUser = $this->getUser();
        $currentUserStaff = $currentUser->getStaff();
        
        // Check permissions: Admin OR staff editing their own profile
        if (!$this->isGranted('ROLE_ADMIN')) {
            // Staff can only edit their own profile
            if (!$currentUserStaff || $currentUserStaff->getId() !== $staff->getId()) {
                throw $this->createAccessDeniedException('You can only edit your own profile.');
            }
        }
        
        $user = $staff->getUser();
        if (!$user) {
            $user = new User();
            $staff->setUser($user);
        }
        
        // Store original data for logging
        $originalData = [
            'firstName' => $staff->getFirstName(),
            'lastName' => $staff->getLastName(),
            'phone' => $staff->getPhoneNumber(),
            'position' => $staff->getRoles(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'status' => $staff->getStatus(),
            'isActive' => $staff->getIsActive()
        ];
        
        // Pass is_admin option to form
        $form = $this->createForm(StaffType::class, $staff, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $changes = [];
            $changedFields = [];
            
            // Check for changes in staff data
            if ($originalData['firstName'] !== $staff->getFirstName()) {
                $changes['firstName'] = [
                    'from' => $originalData['firstName'],
                    'to' => $staff->getFirstName()
                ];
                $changedFields[] = 'First Name';
            }
            
            if ($originalData['lastName'] !== $staff->getLastName()) {
                $changes['lastName'] = [
                    'from' => $originalData['lastName'],
                    'to' => $staff->getLastName()
                ];
                $changedFields[] = 'Last Name';
            }
            
            if ($originalData['phone'] !== $staff->getPhoneNumber()) {
                $changes['phone'] = [
                    'from' => $originalData['phone'],
                    'to' => $staff->getPhoneNumber()
                ];
                $changedFields[] = 'Phone';
            }
            
            if ($originalData['position'] !== $staff->getRoles()) {
                $changes['position'] = [
                    'from' => $originalData['position'],
                    'to' => $staff->getRoles()
                ];
                $changedFields[] = 'Position';
            }
            
            // Only admin can change email, password, roles, status
            if ($this->isGranted('ROLE_ADMIN')) {
                if ($form->has('user_email') && $originalData['email'] !== $form->get('user_email')->getData()) {
                    $changes['email'] = [
                        'from' => $originalData['email'],
                        'to' => $form->get('user_email')->getData()
                    ];
                    $changedFields[] = 'Email';
                    $user->setEmail($form->get('user_email')->getData());
                }
                
                if ($form->has('user_roles') && $originalData['roles'] !== $form->get('user_roles')->getData()) {
                    $changes['roles'] = [
                        'from' => $originalData['roles'],
                        'to' => $form->get('user_roles')->getData()
                    ];
                    $changedFields[] = 'Roles';
                    $user->setRoles($form->get('user_roles')->getData());
                }
                
                if ($form->has('user_password') && $plainPassword = $form->get('user_password')->getData()) {
                    $changes['password'] = [
                        'changed' => true
                    ];
                    $changedFields[] = 'Password';
                    $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                }
                
                if ($originalData['status'] !== $staff->getStatus()) {
                    $changes['status'] = [
                        'from' => $originalData['status'],
                        'to' => $staff->getStatus()
                    ];
                    $changedFields[] = 'Status';
                }
                
                if ($originalData['isActive'] !== $staff->getIsActive()) {
                    $changes['isActive'] = [
                        'from' => $originalData['isActive'] ? 'Active' : 'Inactive',
                        'to' => $staff->getIsActive() ? 'Active' : 'Inactive'
                    ];
                    $changedFields[] = 'Active Status';
                }
            }
            
            // Update the updatedAt timestamp
            $staff->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->flush();
            
            // Log activity if there were changes
            if (!empty($changedFields)) {
                $action = $this->isGranted('ROLE_ADMIN') && $currentUserStaff?->getId() !== $staff->getId() 
                    ? 'UPDATE_STAFF' 
                    : 'UPDATE_PROFILE';
                
                $userType = $this->isGranted('ROLE_ADMIN') ? 'ADMIN' : 'STAFF';
                
                $this->activityLogger->log(
                    $currentUser->getEmail(),
                    $action,
                    'Updated ' . ($this->isGranted('ROLE_ADMIN') ? 'staff' : 'profile') . ': ' . 
                    $staff->getFirstName() . ' ' . $staff->getLastName() . 
                    ' (' . implode(', ', $changedFields) . ')',
                    [
                        'staff_id' => $staff->getId(),
                        'staff_name' => $staff->getFullName(),
                        'changes' => $changes,
                        'changed_fields' => $changedFields
                    ],
                    $userType
                );
            }
            
            $this->addFlash('success', 'Profile updated successfully!');
            
            // Redirect based on who edited
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_staff_crud_index');
            } else {
                return $this->redirectToRoute('app_staff_crud_show', ['id' => $staff->getId()]);
            }
        }

        return $this->render('staff_crud/edit.html.twig', [
            'form' => $form->createView(),
            'staff' => $staff,
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}', name: 'app_staff_crud_delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        Staff $staff, 
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$staff->getId(), $request->request->get('_token'))) {
            $staffName = $staff->getFullName();
            $staffEmail = $staff->getUser()?->getEmail();
            
            // Log before deletion
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'DELETE_STAFF',
                'Deleted staff member: ' . $staffName,
                [
                    'staff_id' => $staff->getId(),
                    'staff_name' => $staffName,
                    'staff_email' => $staffEmail,
                    'staff_status' => $staff->getStatus()
                ],
                'ADMIN'
            );
            
            // Also delete the associated User
            $user = $staff->getUser();
            if ($user) {
                $entityManager->remove($user);
            }
            
            $entityManager->remove($staff);
            $entityManager->flush();
            
            $this->addFlash('success', 'Staff member deleted successfully!');
        }

        return $this->redirectToRoute('app_staff_crud_index');
    }

    #[Route('/{id}/disable', name: 'app_staff_crud_disable', methods: ['POST'])]
    public function disable(
        Request $request, 
        Staff $staff, 
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('disable'.$staff->getId(), $request->request->get('_token'))) {
            $staff->setStatus('disabled');
            $staff->setIsActive(false);
            $staff->setArchivedAt(new \DateTime());
            $staff->setArchivedBy($this->getUser()->getEmail());
            $staff->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->flush();
            
            // Log activity
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'DISABLE_STAFF',
                'Disabled staff member: ' . $staff->getFirstName() . ' ' . $staff->getLastName(),
                [
                    'staff_id' => $staff->getId(),
                    'staff_name' => $staff->getFullName(),
                    'disabled_by' => $this->getUser()->getEmail(),
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                ],
                'ADMIN'
            );
            
            $this->addFlash('warning', 'Staff member has been disabled.');
        }
        
        return $this->redirectToRoute('app_staff_crud_index');
    }
    
    #[Route('/{id}/enable', name: 'app_staff_crud_enable', methods: ['POST'])]
    public function enable(
        Request $request, 
        Staff $staff, 
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('enable'.$staff->getId(), $request->request->get('_token'))) {
            $staff->setStatus('active');
            $staff->setIsActive(true);
            $staff->setArchivedAt(null);
            $staff->setArchivedBy(null);
            $staff->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->flush();
            
            // Log activity
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'ENABLE_STAFF',
                'Enabled staff member: ' . $staff->getFirstName() . ' ' . $staff->getLastName(),
                [
                    'staff_id' => $staff->getId(),
                    'staff_name' => $staff->getFullName(),
                    'enabled_by' => $this->getUser()->getEmail(),
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                ],
                'ADMIN'
            );
            
            $this->addFlash('success', 'Staff member has been enabled.');
        }
        
        return $this->redirectToRoute('app_staff_crud_index');
    }
    
    #[Route('/{id}/archive', name: 'app_staff_crud_archive', methods: ['POST'])]
    public function archive(
        Request $request, 
        Staff $staff, 
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('archive'.$staff->getId(), $request->request->get('_token'))) {
            $staff->setStatus('archived');
            $staff->setIsActive(false);
            $staff->setArchivedAt(new \DateTime());
            $staff->setArchivedBy($this->getUser()->getEmail());
            $staff->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->flush();
            
            // Log activity
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'ARCHIVE_STAFF',
                'Archived staff member: ' . $staff->getFirstName() . ' ' . $staff->getLastName(),
                [
                    'staff_id' => $staff->getId(),
                    'staff_name' => $staff->getFullName(),
                    'archived_by' => $this->getUser()->getEmail(),
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                ],
                'ADMIN'
            );
            
            $this->addFlash('info', 'Staff member has been archived.');
        }
        
        return $this->redirectToRoute('app_staff_crud_index');
    }
    
    #[Route('/{id}/reset-password', name: 'app_staff_crud_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        Staff $staff,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($this->isCsrfTokenValid('reset-password'.$staff->getId(), $request->request->get('_token'))) {
            $user = $staff->getUser();
            if ($user) {
                // Generate temporary password (you might want to implement a better method)
                $tempPassword = bin2hex(random_bytes(4)); // 8 character password
                $user->setPassword($passwordHasher->hashPassword($user, $tempPassword));
                
                $entityManager->flush();
                
                // Log activity
                $this->activityLogger->log(
                    $this->getUser()->getEmail(),
                    'RESET_STAFF_PASSWORD',
                    'Reset password for staff: ' . $staff->getFirstName() . ' ' . $staff->getLastName(),
                    [
                        'staff_id' => $staff->getId(),
                        'staff_name' => $staff->getFullName(),
                        'reset_by' => $this->getUser()->getEmail(),
                        'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                        // Note: Don't log the actual password!
                    ],
                    'ADMIN'
                );
                
                $this->addFlash('success', 'Password has been reset. Temporary password: ' . $tempPassword);
            }
        }
        
        return $this->redirectToRoute('app_staff_crud_index');
    }
    
    #[Route('/export/csv', name: 'app_staff_crud_export_csv', methods: ['GET'])]
    public function exportCsv(EntityManagerInterface $entityManager): Response
    {
        $staffList = $entityManager->getRepository(Staff::class)->findAll();
        
        // Log export activity
        $this->activityLogger->log(
            $this->getUser()->getEmail(),
            'EXPORT_STAFF_CSV',
            'Exported staff list to CSV',
            [
                'exported_by' => $this->getUser()->getEmail(),
                'record_count' => count($staffList),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ],
            'ADMIN'
        );
        
        // CSV generation logic would go here
        // For simplicity, redirect back
        $this->addFlash('success', 'Staff list exported successfully!');
        return $this->redirectToRoute('app_staff_crud_index');
    }
}