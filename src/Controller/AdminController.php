<?php

namespace App\Controller;

use App\Entity\Staff;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Form\AdminProfileType;
use App\Form\ChangePasswordType;
use App\Repository\CarRepository;
use App\Repository\CustomerRepository;
use App\Repository\RentalRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\ActivityLogger;
use Symfony\Component\Security\Http\Attribute\IsGranted; // ADD THIS LINE




class AdminController extends AbstractController
{
    private ActivityLogger $activityLogger;
    
    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }
    

 #[Route('/admin/my-profile', name: 'app_admin_profile')]
public function profile(ActivityLogRepository $activityLogRepository, Request $request): Response
{
    $this->denyAccessUnlessGranted('ROLE_ADMIN');
    
    $user = $this->getUser();
    
    // Get pagination parameters
    $page = $request->query->getInt('page', 1);
    $limit = 10;  // Items per page
    $offset = ($page - 1) * $limit;
    
    // Get activity logs for THIS admin user only
    $activityLogs = $activityLogRepository->createQueryBuilder('a')
        ->where('a.user = :user')
        ->setParameter('user', $user)
        ->orderBy('a.createdAt', 'DESC')
        ->setFirstResult($offset)
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
    
    // Count total logs for this user
    $totalItems = $activityLogRepository->count(['user' => $user]);
    $totalPages = ceil($totalItems / $limit);
    
    // Debug: Check what we're getting
    error_log("Admin Profile - User ID: " . $user->getId());
    error_log("Admin Profile - Logs found: " . count($activityLogs));
    error_log("Admin Profile - Total items: " . $totalItems);
    
    return $this->render('admin/profile.html.twig', [
        'user' => $user,
        'admin' => $user,
        'activityLogs' => $activityLogs,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'totalItems' => $totalItems,
        'limit' => $limit,
    ]);
}

   #[Route('/admin/profile/edit', name: 'app_admin_edit_profile')]
public function editProfile(Request $request, EntityManagerInterface $entityManager): Response
{
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    /** @var User $user */
    $user = $this->getUser();

    $form = $this->createForm(AdminProfileType::class, $user);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        // Get changes BEFORE flushing
        $uow = $entityManager->getUnitOfWork();
        $uow->computeChangeSets(); // compute changes manually

        $changeSet = $uow->getEntityChangeSet($user);
        $changedFields = [];

        foreach ($changeSet as $field => $changes) {
            [$oldValue, $newValue] = $changes;

            // Format values as strings for logging
            $oldStr = is_object($oldValue) ? (string)$oldValue : (string)$oldValue;
            $newStr = is_object($newValue) ? (string)$newValue : (string)$newValue;

            $changedFields[$field] = ['old' => $oldStr, 'new' => $newStr];
        }

        $entityManager->persist($user);
        $entityManager->flush();

        // Prepare description
        if (!empty($changedFields)) {
            $description = 'Updated fields: ';
            foreach ($changedFields as $field => $values) {
                $description .= "$field: '{$values['old']}' → '{$values['new']}'; ";
            }
        } else {
            $description = 'No fields changed';
        }

        // Log changes
        $this->activityLogger->log(
            'PROFILE_UPDATED',
            $description,
            json_encode($changedFields),
            $user
        );

        $this->addFlash('success', 'Profile updated successfully!');
        return $this->redirectToRoute('app_admin_profile');
    }

    return $this->render('admin/edit_profile.html.twig', [
        'form' => $form->createView(),
        'user' => $user,
    ]);
}


    // In AdminController.php
#[Route('/admin/change-password', name: 'app_admin_change_password', methods: ['GET', 'POST'])]
public function changePassword(
    Request $request,
    UserPasswordHasherInterface $passwordHasher,
    EntityManagerInterface $entityManager
): Response {
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    $user = $this->getUser();
    
    $form = $this->createForm(ChangePasswordType::class);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        $data = $form->getData();
        $currentPassword = $data['currentPassword'];
        $newPassword = $data['newPassword'];
        
        // Verify current password
        if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
            $this->addFlash('error', 'Current password is incorrect.');
            return $this->redirectToRoute('app_admin_change_password');
        }
        
        // Admin: Stronger password requirement
        if (strlen($newPassword) < 10) {
            $this->addFlash('error', 'Admin password must be at least 10 characters long.');
            return $this->redirectToRoute('app_admin_change_password');
        }
        
        // Update password
        $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        
        // Log successful password change - USE SAME FORMAT AS STAFF
        $this->activityLogger->log(
        'PASSWORD_CHANGED',
        'Admin successfully changed password',
        json_encode(['user_email' => $user->getEmail()]),  // Use getEmail() not setEmail()
            $user  // Pass the user object
        );
        $entityManager->flush();
        
        $this->addFlash('success', 'Admin password changed successfully!');
        return $this->redirectToRoute('app_admin_profile');
    }
    
    return $this->render('admin/change_password.html.twig', [
        'form' => $form->createView(),
    ]);
}

  #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(
        CustomerRepository $customerRepository,
        CarRepository $carRepository,
        RentalRequestRepository $rentalRequestRepository,
        EntityManagerInterface $entityManager,
        ActivityLogRepository $activityLogRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $now = new \DateTimeImmutable();
        $startOfMonth = $now->modify('first day of this month midnight');
        
        // Business Statistics
        $totalCustomers = $customerRepository->count([]);
        $newCustomersThisMonth = (int) $customerRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdAt >= :startOfMonth')
            ->setParameter('startOfMonth', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalCars = $carRepository->count([]);
        $totalRentals = $rentalRequestRepository->count([]);
        
        $activeRentals = (int) $rentalRequestRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.startDate <= :now')
            ->andWhere('r.endDate >= :now')
            ->andWhere('r.status = :status')
            ->setParameter('now', $now)
            ->setParameter('status', 'approved')
            ->getQuery()
            ->getSingleScalarResult();
        
        $upcomingReturn = $rentalRequestRepository->createQueryBuilder('r')
            ->where('r.endDate >= :now')
            ->andWhere('r.status = :status')
            ->setParameter('now', $now)
            ->setParameter('status', 'approved')
            ->orderBy('r.endDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        $recentCustomers = $customerRepository->createQueryBuilder('c')
            ->leftJoin('c.rentalRequests', 'r')
            ->addSelect('r')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();
        
        $recentRentalRequests = $rentalRequestRepository->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        $fleetSnapshot = $carRepository->createQueryBuilder('c')
            ->leftJoin('c.status', 'status')
            ->addSelect('status')
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        // Staff Management Statistics
        $totalStaff = $entityManager->getRepository(Staff::class)->count([]);
        
        $activeStaff = $entityManager->getRepository(Staff::class)
            ->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $recentStaff = $entityManager->getRepository(Staff::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')
            ->addSelect('u')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        // Get recent activity logs
        $recentLogs = $activityLogRepository->findRecentLogs(10);
        
    
        
        return $this->render('admin/dashboard.html.twig', [
            // Business Stats
            'stats' => [
                'totalCustomers' => $totalCustomers,
                'newCustomersThisMonth' => $newCustomersThisMonth,
                'totalCars' => $totalCars,
                'totalRentals' => $totalRentals,
                'activeRentals' => $activeRentals,
                'fleetUtilization' => $totalCars > 0 ? (int) round(($activeRentals / max($totalCars, 1)) * 100) : 0,
                'nextReturn' => $upcomingReturn,
            ],
            'recentCustomers' => $recentCustomers,
            'recentRentalRequests' => $recentRentalRequests,
            'fleetSnapshot' => $fleetSnapshot,
            
            // Staff Management Data
            'totalStaff' => $totalStaff,
            'activeStaff' => $activeStaff,
            'recentStaff' => $recentStaff,
            'activeSessions' => 1,
            
            // Activity Logs
            'recentLogs' => $recentLogs,
        ]);
    }

    // SIMPLER VERSION - Add this to your existing controller
#[Route('/admin/rental-requests/view', name: 'app_admin_rental_requests_view')]
#[IsGranted('ROLE_ADMIN')]
public function adminRentalView(RentalRequestRepository $rentalRequestRepository): Response
{
    // Use parameter injection
    $pendingRequests = $rentalRequestRepository->findBy(
        ['status' => 'pending'],
        ['createdAt' => 'DESC']
    );
    
    $approvedRequests = $rentalRequestRepository->findBy(
        ['status' => 'approved'],
        ['approvedAt' => 'DESC']
    );
    
    $rejectedRequests = $rentalRequestRepository->findBy(
        ['status' => 'rejected'],
        ['rejectedAt' => 'DESC']
    );

    return $this->render('admin/rental_requests_view.html.twig', [
        'pendingRequests' => $pendingRequests,
        'approvedRequests' => $approvedRequests,
        'rejectedRequests' => $rejectedRequests,
    ]);
}
// Also add this method for the detail view
// src/Controller/AdminController.php


// Change your method to accept the repository as parameter:
#[Route('/admin/rental-requests/view/{id}', name: 'app_admin_rental_request_view')]
#[IsGranted('ROLE_ADMIN')]
public function adminRentalViewDetail(int $id, RentalRequestRepository $rentalRequestRepository): Response
{
    $rentalRequest = $rentalRequestRepository->find($id);
    
    if (!$rentalRequest) {
        throw $this->createNotFoundException('Rental request not found');
    }

    return $this->render('admin/view_detail.html.twig', [
        'rentalRequest' => $rentalRequest,
    ]);
}


}