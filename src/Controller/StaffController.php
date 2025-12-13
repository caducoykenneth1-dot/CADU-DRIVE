<?php

namespace App\Controller;

use App\Entity\RentalRequest;
use App\Entity\Staff;
use App\Form\StaffProfileType;
use App\Repository\RentalRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use App\Service\ActivityLogger;


class StaffController extends AbstractController
{
    private ActivityLogger $activityLogger;
    
    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }
    
    /* === STAFF MANAGEMENT === */
    #[Route('/admin/staff/overview', name: 'app_staff_overview')]
    public function overview(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $user = $this->getUser();
       
        
        $staffList = $entityManager->getRepository(Staff::class)
            ->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')
            ->leftJoin('s.createdByAdmin', 'a')
            ->addSelect('u', 'a')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('admin/staff_overview.html.twig', [
            'staffList' => $staffList,
        ]);
    }
    
    /* === RENTAL REQUEST MANAGEMENT === */
    #[Route('/staff/rental-requests', name: 'app_staff_rental_requests')]
    public function rentalRequests(RentalRequestRepository $rentalRequestRepo): Response
    {
        // Allow both ROLE_ADMIN and ROLE_STAFF
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        
        
        
        // Fetch with customer and car joins
        $pendingRequests = $rentalRequestRepo->createQueryBuilder('r')
            ->leftJoin('r.customer', 'c')
            ->leftJoin('r.car', 'car')
            ->addSelect('c', 'car')
            ->where('r.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        $approvedRequests = $rentalRequestRepo->createQueryBuilder('r')
            ->leftJoin('r.customer', 'c')
            ->leftJoin('r.car', 'car')
            ->addSelect('c', 'car')
            ->where('r.status = :status')
            ->setParameter('status', 'approved')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        $rejectedRequests = $rentalRequestRepo->createQueryBuilder('r')
            ->leftJoin('r.customer', 'c')
            ->leftJoin('r.car', 'car')
            ->addSelect('c', 'car')
            ->where('r.status = :status')
            ->setParameter('status', 'rejected')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('staff/rental_requests.html.twig', [
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'rejectedRequests' => $rejectedRequests,
        ]);
    }
    
    #[Route('/staff/rental-request/{id}/approve', name: 'app_staff_rental_request_approve')]
    public function approveRequest(RentalRequest $request, EntityManagerInterface $em): Response
    {
        // Allow both ROLE_ADMIN and ROLE_STAFF
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        $request->setStatus('approved');
        $em->flush();
        
        // Log the approval
        $this->activityLogger->log(
            $user->getEmail(),
            'APPROVE_RENTAL_REQUEST',
            ($isAdmin ? 'Admin' : 'Staff') . ' approved rental request',
            [
                'rental_request_id' => $request->getId(),
                'customer_email' => $request->getCustomer()->getEmail(),
                'car' => $request->getCar()->getMake() . ' ' . $request->getCar()->getModel(),
                'dates' => $request->getStartDate()->format('Y-m-d') . ' to ' . $request->getEndDate()->format('Y-m-d')
            ],
            $isAdmin ? 'ADMIN' : 'STAFF'
        );
        
        $this->addFlash('success', 'Rental request #' . $request->getId() . ' approved!');
        
        return $this->redirectToRoute('app_staff_rental_requests');
    }
    
    #[Route('/staff/rental-request/{id}/reject', name: 'app_staff_rental_request_reject')]
    public function rejectRequest(RentalRequest $request, EntityManagerInterface $em): Response
    {
        // Allow both ROLE_ADMIN and ROLE_STAFF
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        $request->setStatus('rejected');
        $em->flush();
        
        // Log the rejection
        $this->activityLogger->log(
            $user->getEmail(),
            'REJECT_RENTAL_REQUEST',
            ($isAdmin ? 'Admin' : 'Staff') . ' rejected rental request',
            [
                'rental_request_id' => $request->getId(),
                'customer_email' => $request->getCustomer()->getEmail(),
                'car' => $request->getCar()->getMake() . ' ' . $request->getCar()->getModel(),
                'dates' => $request->getStartDate()->format('Y-m-d') . ' to ' . $request->getEndDate()->format('Y-m-d')
            ],
            $isAdmin ? 'ADMIN' : 'STAFF'
        );
        
        $this->addFlash('warning', 'Rental request #' . $request->getId() . ' rejected.');
        
        return $this->redirectToRoute('app_staff_rental_requests');
    }
    
    /* === STAFF DASHBOARD === */
    #[Route('/staff/dashboard', name: 'app_staff_dashboard')]
    public function dashboard(RentalRequestRepository $rentalRequestRepo): Response
    {
        // Allow both ROLE_ADMIN and ROLE_STAFF
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
      
        // Get fresh pending requests
        $pendingRequests = $rentalRequestRepo->findBy(
            ['status' => 'pending'],
            ['createdAt' => 'DESC'],
            5
        );
        
        // Get counts without date filtering (since we don't have updatedAt)
        $totalApproved = $rentalRequestRepo->count(['status' => 'approved']);
        $totalRejected = $rentalRequestRepo->count(['status' => 'rejected']);
        
        $stats = [
            'pendingRequests' => count($pendingRequests),
            'totalApproved' => $totalApproved, // Changed from approvedToday
            'totalRejected' => $totalRejected, // Changed from rejectedThisWeek
            'totalHandled' => $totalApproved + $totalRejected,
        ];
        
        return $this->render('staff/dashboard.html.twig', [
            'pendingRequests' => $pendingRequests,
            'stats' => $stats,
            'recentActivity' => [],
        ]);
    }
    
    /* === STAFF PROFILE MANAGEMENT === */
    #[Route('/staff/profile', name: 'app_staff_profile')]
    public function profile(): Response
    {
        // Allow both ROLE_ADMIN and ROLE_STAFF
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
       
        
        $staff = $this->getUser()->getStaff();
        
        if (!$staff) {
            throw $this->createNotFoundException('Staff profile not found.');
        }
        
        return $this->render('staff/profile.html.twig', [
            'staff' => $staff,
        ]);
    }
    
#[Route('/staff/profile/edit', name: 'app_staff_profile_edit')]
public function editProfile(Request $request, EntityManagerInterface $em): Response
{
    // Allow both ROLE_ADMIN and ROLE_STAFF
    if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
        throw $this->createAccessDeniedException();
    }
    
    $user = $this->getUser();
    $isAdmin = $this->isGranted('ROLE_ADMIN');
    
    $staff = $this->getUser()->getStaff();
    
    if (!$staff) {
        throw $this->createNotFoundException('Staff profile not found.');
    }
    
    // Store original values BEFORE form submission
    $originalFirstName = $staff->getFirstName();
    $originalLastName = $staff->getLastName();
    $originalPhoneNumber = $staff->getPhoneNumber();
    $originalDepartment = $staff->getDepartment();
    
    $form = $this->createForm(StaffProfileType::class, $staff);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        $staff->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();
        
        // Track what actually changed
        $changes = [];
        
        if ($originalFirstName !== $staff->getFirstName()) {
            $changes[] = "First name changed from '{$originalFirstName}' to '{$staff->getFirstName()}'";
        }
        
        if ($originalLastName !== $staff->getLastName()) {
            $changes[] = "Last name changed from '{$originalLastName}' to '{$staff->getLastName()}'";
        }
        
        if ($originalPhoneNumber !== $staff->getPhoneNumber()) {
            $oldPhone = $originalPhoneNumber ?? 'empty';
            $newPhone = $staff->getPhoneNumber() ?? 'empty';
            $changes[] = "Phone number changed from '{$oldPhone}' to '{$newPhone}'";
        }
        
        if ($originalDepartment !== $staff->getDepartment()) {
            $oldDept = $originalDepartment ?? 'empty';
            $newDept = $staff->getDepartment() ?? 'empty';
            $changes[] = "Department changed from '{$oldDept}' to '{$newDept}'";
        }
        
        // Create a meaningful description based on what changed
        $changeDescription = empty($changes) 
            ? 'No fields changed' 
            : implode(', ', $changes);
        
        // Log profile update with specific changes
        $this->activityLogger->log(
            $user->getEmail(),
            'UPDATE_STAFF_PROFILE',
            ($isAdmin ? 'Admin' : 'Staff') . ' updated their profile: ' . $changeDescription,
            [
                'staff_id' => $staff->getId(),
                'staff_email' => $user->getEmail(),
                'staff_name' => $staff->getFullName(),
                'changes' => $changes,
                'original_first_name' => $originalFirstName,
                'original_last_name' => $originalLastName,
                'original_phone' => $originalPhoneNumber,
                'original_department' => $originalDepartment,
                'new_first_name' => $staff->getFirstName(),
                'new_last_name' => $staff->getLastName(),
                'new_phone' => $staff->getPhoneNumber(),
                'new_department' => $staff->getDepartment()
            ],
            $isAdmin ? 'ADMIN' : 'STAFF'
        );
        
        $this->addFlash('success', 'Profile updated successfully!');
        
        // If something actually changed, show what was changed
        if (!empty($changes)) {
            $this->addFlash('info', 'Changes made: ' . $changeDescription);
        }
        
        return $this->redirectToRoute('app_staff_profile');
    }
    
    return $this->render('staff/edit_profile.html.twig', [
        'form' => $form->createView(),
        'staff' => $staff,
    ]);
}

    #[Route('/staff/change-password', name: 'app_staff_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // Allow both ROLE_ADMIN and ROLE_STAFF
        if (!$this->isGranted('ROLE_STAFF') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        
        // Log access to password change page
      
        
        // Create a simple form for password change
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
                return $this->redirectToRoute('app_staff_change_password');
            }
            
            // Check password length
            if (strlen($newPassword) < 8) {
               
                
                $this->addFlash('error', 'Password must be at least 8 characters long.');
                return $this->redirectToRoute('app_staff_change_password');
            }
            
            // Verify current password
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
               
                
                $this->addFlash('error', 'Current password is incorrect.');
                return $this->redirectToRoute('app_staff_change_password');
            }
            
            // Update password
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $entityManager->flush();
            
            // Log successful password change
            $this->activityLogger->log(
                $user->getEmail(),
                'PASSWORD_CHANGED',
                ($isAdmin ? 'Admin' : 'Staff') . ' successfully changed password',
                ['user_email' => $user->getEmail()],
                $isAdmin ? 'ADMIN' : 'STAFF'
            );
            
            $this->addFlash('success', 'Password changed successfully!');
            
            // Redirect to staff profile
            return $this->redirectToRoute('app_staff_profile');
        }
        
        return $this->render('staff/change_password.html.twig', [
            'form' => $form->createView()
        ]);
    }
}