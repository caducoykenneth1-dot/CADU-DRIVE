<?php

namespace App\Controller;

use App\Entity\RentalRequest;
use App\Entity\Car;
use App\Entity\CarStatus;
use App\Entity\Customer; 
use App\Form\RentalRequestType;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RentalRequestController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger
    ) {}
    
    /**
     * Create rental request from car page
     */
    #[Route('/car/{id}/request-rental', name: 'app_rental_request_new')]
    public function new(
        Car $car, 
        Request $request, 
        EntityManagerInterface $entityManager
    ): Response
    {
        // Check if user is logged in
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
            $entityManager->persist($customer);
        }
        
        // Only create new customer if absolutely doesn't exist
        if (!$customer) {
            $customer = new Customer();
            $customer->setEmail($user->getEmail());
            $customer->setFirstName('');
            $customer->setLastName('');
            $customer->setUser($user);
            $customer->setCreatedAt(new \DateTimeImmutable());
            $customer->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->persist($customer);
        }
        
        // Create new rental request
        $rentalRequest = new RentalRequest();
        $rentalRequest->setCustomer($customer);
        $rentalRequest->setCar($car);
        $rentalRequest->setCreatedAt(new \DateTime());
        $rentalRequest->setStatus('pending');
        
        // Create form (regular users don't need is_staff option)
        $form = $this->createForm(RentalRequestType::class, $rentalRequest, [
            'is_staff' => false
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($rentalRequest);
            $entityManager->flush();

            // Log CREATE activity
            $this->activityLogger->log(
                $user->getEmail(),
                'CREATE_RENTAL_REQUEST',
                'Rental request created for car: ' . $car->getMake() . ' ' . $car->getModel(),
                [
                    'rental_request_id' => $rentalRequest->getId(),
                    'car_id' => $car->getId(),
                    'car_make' => $car->getMake(),
                    'car_model' => $car->getModel(),
                    'customer_id' => $customer->getId(),
                    'customer_name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
                    'customer_email' => $customer->getEmail(),
                    'start_date' => $rentalRequest->getStartDate() ? $rentalRequest->getStartDate()->format('Y-m-d') : null,
                    'end_date' => $rentalRequest->getEndDate() ? $rentalRequest->getEndDate()->format('Y-m-d') : null,
                    'status' => $rentalRequest->getStatus()
                ],
                $user->getStaff() ? 'STAFF' : 'USER'
            );
            
            $this->addFlash('success', 
                'Your rental request has been submitted! ' .
                'Our staff will review and approve it within 24 hours.'
            );
            
            return $this->redirectToRoute('app_car_show', ['id' => $car->getId()]);
        }
        
        return $this->render('rental_request/new.html.twig', [
            'form' => $form->createView(),
            'car' => $car,
        ]);
    }
    
    /**
     * Approve rental request
     */
    #[Route('/rental-request/{id}/approve', name: 'app_rental_request_approve', methods: ['POST'])]
    public function approve(RentalRequest $rentalRequest, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Only staff can approve
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        if ($this->isCsrfTokenValid('approve'.$rentalRequest->getId(), $request->request->get('_token'))) {
            // Store old status for logging
            $oldStatus = $rentalRequest->getStatus();
            
            // Update rental request status
            $rentalRequest->setStatus('approved');
            $rentalRequest->setApprovedAt(new \DateTime());

            // Update the car status using CarStatus entity
            $car = $rentalRequest->getCar();

            if ($car) {
                // Fetch CarStatus entity where code = 'rented'
                $carStatus = $entityManager->getRepository(CarStatus::class)
                    ->findOneBy(['code' => 'rented']);

                if ($carStatus) {
                    $car->setStatus($carStatus);
                    $car->setUpdatedAt(new \DateTime());
                } else {
                    $this->addFlash('error', 'CarStatus "rented" not found in database.');
                }
            }

            $entityManager->flush();
            
            // Log APPROVAL activity
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'APPROVE_RENTAL_REQUEST',
                'Approved rental request #' . $rentalRequest->getId(),
                [
                    'rental_request_id' => $rentalRequest->getId(),
                    'car_id' => $rentalRequest->getCar()->getId(),
                    'car_make' => $rentalRequest->getCar()->getMake(),
                    'car_model' => $rentalRequest->getCar()->getModel(),
                    'customer_id' => $rentalRequest->getCustomer()->getId(),
                    'customer_name' => $rentalRequest->getCustomer()->getFirstName() . ' ' . $rentalRequest->getCustomer()->getLastName(),
                    'customer_email' => $rentalRequest->getCustomer()->getEmail(),
                    'old_status' => $oldStatus,
                    'new_status' => 'approved'
                ],
                'STAFF'
            );

            $this->addFlash('success', 'Rental request approved! Car marked as rented.');
        }

        return $this->redirectToRoute('app_rental_request_index');
    }
    
    /**
     * Reject rental request
     */
    #[Route('/rental-request/{id}/reject', name: 'app_rental_request_reject', methods: ['POST'])]
    public function reject(
        RentalRequest $rentalRequest,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        // Check if staff is logged in
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        // Check CSRF token
        if ($this->isCsrfTokenValid('reject'.$rentalRequest->getId(), $request->request->get('_token'))) {
            // Store old status for logging
            $oldStatus = $rentalRequest->getStatus();
            
            $rentalRequest->setStatus('rejected');
            $rentalRequest->setRejectedAt(new \DateTime());
            
            $entityManager->persist($rentalRequest);
            $entityManager->flush();
            
            // Log REJECTION activity
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'REJECT_RENTAL_REQUEST',
                'Rejected rental request #' . $rentalRequest->getId(),
                [
                    'rental_request_id' => $rentalRequest->getId(),
                    'car_id' => $rentalRequest->getCar()->getId(),
                    'car_make' => $rentalRequest->getCar()->getMake(),
                    'car_model' => $rentalRequest->getCar()->getModel(),
                    'customer_id' => $rentalRequest->getCustomer()->getId(),
                    'customer_name' => $rentalRequest->getCustomer()->getFirstName() . ' ' . $rentalRequest->getCustomer()->getLastName(),
                    'customer_email' => $rentalRequest->getCustomer()->getEmail(),
                    'old_status' => $oldStatus,
                    'new_status' => 'rejected'
                ],
                'STAFF'
            );
            
            $this->addFlash('success', 'Rental request rejected.');
        }
        
        return $this->redirectToRoute('app_rental_request_index');
    }
    
    /**
     * Index page - List all rental requests
     */
    #[Route('/rental-requests', name: 'app_rental_request_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // Only staff can see this
        $this->denyAccessUnlessGranted('ROLE_STAFF');

        $repository = $entityManager->getRepository(RentalRequest::class);

        // Get requests by status
        $pendingRequests = $repository->findBy(
            ['status' => 'pending'],
            ['createdAt' => 'DESC']
        );

        $approvedRequests = $repository->findBy(
            ['status' => 'approved'],
            ['createdAt' => 'DESC']
        );

        $rejectedRequests = $repository->findBy(
            ['status' => 'rejected'],
            ['createdAt' => 'DESC']
        );

        // Get data for modal forms
        $customers = $entityManager->getRepository(Customer::class)->findAll();
        $allCars = $entityManager->getRepository(Car::class)->findAll();
        
        // For available cars, just use all cars for now (simplified)
        $availableCars = $allCars;

        return $this->render('rental_request/index.html.twig', [
            'pendingRequests' => $pendingRequests,
            'approvedRequests' => $approvedRequests,
            'rejectedRequests' => $rejectedRequests,
            'customers' => $customers,
            'availableCars' => $availableCars,
            'allCars' => $allCars,
        ]);
    }
    
    /**
     * CREATE - Show form to create new rental request (for staff)
     */
    #[Route('/admin/rental-requests/create', name: 'app_rental_request_create', methods: ['GET', 'POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_STAFF');
        
        $rentalRequest = new RentalRequest();
        $form = $this->createForm(RentalRequestType::class, $rentalRequest, [
            'is_staff' => true  // Staff can select customer, car, and status
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Set default values
            if (!$rentalRequest->getCreatedAt()) {
                $rentalRequest->setCreatedAt(new \DateTime());
            }
            
            // If approved/rejected, set timestamps
            if ($rentalRequest->getStatus() === 'approved') {
                $rentalRequest->setApprovedAt(new \DateTime());
                
                // Update car status to rented
                $car = $rentalRequest->getCar();
                if ($car) {
                    $carStatus = $entityManager->getRepository(CarStatus::class)
                        ->findOneBy(['code' => 'rented']);
                    if ($carStatus) {
                        $car->setStatus($carStatus);
                        $car->setUpdatedAt(new \DateTime());
                    }
                }
            } elseif ($rentalRequest->getStatus() === 'rejected') {
                $rentalRequest->setRejectedAt(new \DateTime());
            }
            
            $entityManager->persist($rentalRequest);
            $entityManager->flush();

            // Log CREATE activity (staff version)
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'CREATE_RENTAL_REQUEST',
                'Staff created new rental request for customer: ' . $rentalRequest->getCustomer()->getDisplayName(),
                [
                    'rental_request_id' => $rentalRequest->getId(),
                    'car_id' => $rentalRequest->getCar()->getId(),
                    'car_make' => $rentalRequest->getCar()->getMake(),
                    'car_model' => $rentalRequest->getCar()->getModel(),
                    'customer_id' => $rentalRequest->getCustomer()->getId(),
                    'customer_name' => $rentalRequest->getCustomer()->getFirstName() . ' ' . $rentalRequest->getCustomer()->getLastName(),
                    'customer_email' => $rentalRequest->getCustomer()->getEmail(),
                    'start_date' => $rentalRequest->getStartDate() ? $rentalRequest->getStartDate()->format('Y-m-d') : null,
                    'end_date' => $rentalRequest->getEndDate() ? $rentalRequest->getEndDate()->format('Y-m-d') : null,
                    'status' => $rentalRequest->getStatus()
                ],
                'STAFF'
            );
            
            $this->addFlash('success', 'Rental request created successfully!');
            return $this->redirectToRoute('app_rental_request_index');
        }
        
        // Get customers and cars for the form
        $customers = $entityManager->getRepository(Customer::class)->findAll();
        $allCars = $entityManager->getRepository(Car::class)->findAll();
        
        return $this->render('rental_request/create.html.twig', [
            'form' => $form->createView(),
            'customers' => $customers,
            'allCars' => $allCars,
        ]);
    }
    
    /**
     * SHOW - Display rental request details
     */
    #[Route('/staff/rental-requests/{id}', name: 'app_staff_rental_request_show', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function show(RentalRequest $rentalRequest): Response
    {
        return $this->render('rental_request/show.html.twig', [
            'rentalRequest' => $rentalRequest,
        ]);
    }
    
    /**
     * EDIT - Edit an existing rental request
     */
    /**
 * EDIT - Edit an existing rental request
 */
#[Route('/staff/rental-requests/{id}/edit', name: 'app_rental_request_edit', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_STAFF')]
public function edit(Request $request, RentalRequest $rentalRequest, EntityManagerInterface $entityManager): Response
{        
    // Store original values BEFORE form is handled
    $originalData = [
        'status' => $rentalRequest->getStatus(),
        'car_id' => $rentalRequest->getCar() ? $rentalRequest->getCar()->getId() : null,
        'car_name' => $rentalRequest->getCar() ? $rentalRequest->getCar()->getMake() . ' ' . $rentalRequest->getCar()->getModel() : null,
        'customer_id' => $rentalRequest->getCustomer() ? $rentalRequest->getCustomer()->getId() : null,
        'customer_name' => $rentalRequest->getCustomer() ? $rentalRequest->getCustomer()->getFirstName() . ' ' . $rentalRequest->getCustomer()->getLastName() : null,
        'customer_email' => $rentalRequest->getCustomer() ? $rentalRequest->getCustomer()->getEmail() : null,
        'start_date' => $rentalRequest->getStartDate() ? $rentalRequest->getStartDate()->format('Y-m-d') : null,
        'end_date' => $rentalRequest->getEndDate() ? $rentalRequest->getEndDate()->format('Y-m-d') : null,
        'notes' => $rentalRequest->getNotes(),
    ];
    
    $oldCar = $rentalRequest->getCar();
    
    // Use POST method
    $form = $this->createForm(RentalRequestType::class, $rentalRequest, [
        'is_staff' => true,
        'method' => 'POST'
    ]);
    
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        // Get new values after form submission
        $newStatus = $rentalRequest->getStatus();
        $newCar = $rentalRequest->getCar();
        $newCustomer = $rentalRequest->getCustomer();
        
        // Track changes
        $changes = [];
        $changedFields = [];
        
        // Check for status change
        if ($originalData['status'] !== $newStatus) {
            $changes['status'] = [
                'from' => $originalData['status'],
                'to' => $newStatus
            ];
            $changedFields[] = 'Status';
        }
        
        // Check for car change
        if ($newCar && $originalData['car_id'] !== $newCar->getId()) {
            $changes['car'] = [
                'from' => $originalData['car_name'] . ' (ID: ' . $originalData['car_id'] . ')',
                'to' => $newCar->getMake() . ' ' . $newCar->getModel() . ' (ID: ' . $newCar->getId() . ')'
            ];
            $changedFields[] = 'Car';
        }
        
        // Check for customer change
        if ($newCustomer && $originalData['customer_id'] !== $newCustomer->getId()) {
            $changes['customer'] = [
                'from' => $originalData['customer_name'] . ' (' . $originalData['customer_email'] . ')',
                'to' => $newCustomer->getFirstName() . ' ' . $newCustomer->getLastName() . ' (' . $newCustomer->getEmail() . ')'
            ];
            $changedFields[] = 'Customer';
        }
        
        // Check for date changes
        $newStartDate = $rentalRequest->getStartDate() ? $rentalRequest->getStartDate()->format('Y-m-d') : null;
        $newEndDate = $rentalRequest->getEndDate() ? $rentalRequest->getEndDate()->format('Y-m-d') : null;
        
        if ($originalData['start_date'] !== $newStartDate) {
            $changes['start_date'] = [
                'from' => $originalData['start_date'],
                'to' => $newStartDate
            ];
            $changedFields[] = 'Start Date';
        }
        
        if ($originalData['end_date'] !== $newEndDate) {
            $changes['end_date'] = [
                'from' => $originalData['end_date'],
                'to' => $newEndDate
            ];
            $changedFields[] = 'End Date';
        }
        
        // Check for notes change
        if ($originalData['notes'] !== $rentalRequest->getNotes()) {
            $changes['notes'] = [
                'from' => $originalData['notes'] ?? '(empty)',
                'to' => $rentalRequest->getNotes() ?? '(empty)'
            ];
            $changedFields[] = 'Notes';
        }
        
        // Handle car status changes based on rental request status
        
        // If changing from approved to something else, make old car available
        if ($originalData['status'] === 'approved' && $newStatus !== 'approved') {
            $availableStatus = $entityManager->getRepository(CarStatus::class)
                ->findOneBy(['code' => 'available']);
            if ($availableStatus && $oldCar) {
                $oldCar->setStatus($availableStatus);
                $oldCar->setUpdatedAt(new \DateTime());
            }
        }
        
        // If approving, set timestamp and update car status
        if ($newStatus === 'approved' && $originalData['status'] !== 'approved') {
            $rentalRequest->setApprovedAt(new \DateTime());
            
            if ($newCar) {
                $rentedStatus = $entityManager->getRepository(CarStatus::class)
                    ->findOneBy(['code' => 'rented']);
                if ($rentedStatus) {
                    $newCar->setStatus($rentedStatus);
                    $newCar->setUpdatedAt(new \DateTime());
                }
            }
        }
        
        // If rejecting, set timestamp
        if ($newStatus === 'rejected' && $originalData['status'] !== 'rejected') {
            $rentalRequest->setRejectedAt(new \DateTime());
            
            // If previously approved, make car available
            if ($originalData['status'] === 'approved' && $oldCar) {
                $availableStatus = $entityManager->getRepository(CarStatus::class)
                    ->findOneBy(['code' => 'available']);
                if ($availableStatus) {
                    $oldCar->setStatus($availableStatus);
                    $oldCar->setUpdatedAt(new \DateTime());
                }
            }
        }
        
        // If car was changed and the request is approved, handle both cars
        if (!empty($changes['car']) && $newStatus === 'approved') {
            // Make old car available
            if ($oldCar) {
                $availableStatus = $entityManager->getRepository(CarStatus::class)
                    ->findOneBy(['code' => 'available']);
                if ($availableStatus) {
                    $oldCar->setStatus($availableStatus);
                    $oldCar->setUpdatedAt(new \DateTime());
                }
            }
            
            // Make new car rented
            if ($newCar) {
                $rentedStatus = $entityManager->getRepository(CarStatus::class)
                    ->findOneBy(['code' => 'rented']);
                if ($rentedStatus) {
                    $newCar->setStatus($rentedStatus);
                    $newCar->setUpdatedAt(new \DateTime());
                }
            }
        }
        
        $entityManager->flush();
        
        // Log EDIT activity
        $user = $this->getUser();
        $this->activityLogger->log(
            $user->getEmail(),
            'UPDATE_RENTAL_REQUEST',
            'Updated rental request #' . $rentalRequest->getId() . 
            (!empty($changedFields) ? ' - Changed: ' . implode(', ', $changedFields) : ''),
            [
                'rental_request_id' => $rentalRequest->getId(),
                'car_id' => $newCar ? $newCar->getId() : null,
                'car_make' => $newCar ? $newCar->getMake() : null,
                'car_model' => $newCar ? $newCar->getModel() : null,
                'customer_id' => $newCustomer ? $newCustomer->getId() : null,
                'customer_name' => $newCustomer ? $newCustomer->getFirstName() . ' ' . $newCustomer->getLastName() : null,
                'customer_email' => $newCustomer ? $newCustomer->getEmail() : null,
                'old_status' => $originalData['status'],
                'new_status' => $newStatus,
                'changes' => !empty($changes) ? $changes : 'No changes detected',
                'changed_fields' => !empty($changedFields) ? $changedFields : ['None']
            ],
            'STAFF'
        );
        
        $this->addFlash('success', 'Rental request updated successfully!');
        return $this->redirectToRoute('app_rental_request_index');
    }
    
    // Get all customers and cars for the form
    $customers = $entityManager->getRepository(Customer::class)->findAll();
    $allCars = $entityManager->getRepository(Car::class)->findAll();
    
    return $this->render('rental_request/edit.html.twig', [
        'form' => $form->createView(),
        'rentalRequest' => $rentalRequest,
        'customers' => $customers,
        'allCars' => $allCars,
    ]);
}
    /**
     * DELETE - Delete a rental request
     */
    #[Route('/staff/rental-requests/{id}', name: 'app_staff_rental_request_delete', methods: ['POST', 'DELETE'])]
    #[IsGranted('ROLE_STAFF')]
    public function delete(Request $request, RentalRequest $rentalRequest, EntityManagerInterface $entityManager): Response
    {
        // Check CSRF token
        if ($this->isCsrfTokenValid('delete' . $rentalRequest->getId(), $request->request->get('_token'))) {
            
            // Store data for logging before deletion
            $rentalData = [
                'id' => $rentalRequest->getId(),
                'car' => $rentalRequest->getCar() ? $rentalRequest->getCar()->getMake() . ' ' . $rentalRequest->getCar()->getModel() : null,
                'car_id' => $rentalRequest->getCar() ? $rentalRequest->getCar()->getId() : null,
                'customer' => $rentalRequest->getCustomer() ? $rentalRequest->getCustomer()->getFirstName() . ' ' . $rentalRequest->getCustomer()->getLastName() : null,
                'customer_email' => $rentalRequest->getCustomer() ? $rentalRequest->getCustomer()->getEmail() : null,
                'status' => $rentalRequest->getStatus(),
                'start_date' => $rentalRequest->getStartDate() ? $rentalRequest->getStartDate()->format('Y-m-d') : null,
                'end_date' => $rentalRequest->getEndDate() ? $rentalRequest->getEndDate()->format('Y-m-d') : null
            ];
            
            // If approved request is being deleted, make car available
            if ($rentalRequest->getStatus() === 'approved') {
                $car = $rentalRequest->getCar();
                if ($car) {
                    $availableStatus = $entityManager->getRepository(CarStatus::class)
                        ->findOneBy(['code' => 'available']);
                    if ($availableStatus) {
                        $car->setStatus($availableStatus);
                        $car->setUpdatedAt(new \DateTime());
                    }
                }
            }
            
            $entityManager->remove($rentalRequest);
            $entityManager->flush();
            
            // Log DELETE activity
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'DELETE_RENTAL_REQUEST',
                'Deleted rental request #' . $rentalData['id'],
                [
                    'rental_request_id' => $rentalData['id'],
                    'car' => $rentalData['car'],
                    'car_id' => $rentalData['car_id'],
                    'customer' => $rentalData['customer'],
                    'customer_email' => $rentalData['customer_email'],
                    'status' => $rentalData['status'],
                    'start_date' => $rentalData['start_date'],
                    'end_date' => $rentalData['end_date'],
                    'deleted_by' => $this->getUser()->getEmail()
                ],
                'STAFF'
            );
            
            $this->addFlash('success', 'Rental request deleted successfully!');
        }
        
        return $this->redirectToRoute('app_rental_request_index');
    }
}