<?php

namespace App\Controller;

use App\Entity\RentalRequest;
use App\Entity\Car;
use App\Entity\Customer;
use App\Entity\CarStatus;
use App\Form\WalkInRentalType;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WalkInRentalController extends AbstractController
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private EntityManagerInterface $entityManager
    ) {}

  
#[Route('/staff/walk-in-rentals', name: 'app_walk_in_rental_index')]
#[IsGranted('ROLE_STAFF')]
public function index(): Response
{
    // Get available cars
    $availableStatus = $this->entityManager->getRepository(CarStatus::class)
        ->findOneBy(['code' => 'available']);
    
    $availableCars = [];
    if ($availableStatus) {
        $availableCars = $this->entityManager->getRepository(Car::class)
            ->findBy(['status' => $availableStatus], ['make' => 'ASC', 'model' => 'ASC']);
    }

    // Get all customers for dropdown
    $customers = $this->entityManager->getRepository(Customer::class)
        ->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC']);

    // Get today's ACTIVE walk-in rentals (NOT completed)
    $today = new \DateTime('today');
    $todayEnd = new \DateTime('tomorrow');
    
    $todayRentals = $this->entityManager->getRepository(RentalRequest::class)
        ->createQueryBuilder('r')
        ->where('r.status = :status')
        ->andWhere('r.returnedAt IS NULL') // ✅ CRITICAL: Exclude completed rentals
        ->andWhere('r.createdAt >= :todayStart')
        ->andWhere('r.createdAt < :todayEnd')
        ->setParameter('status', 'approved')
        ->setParameter('todayStart', $today)
        ->setParameter('todayEnd', $todayEnd)
        ->orderBy('r.createdAt', 'DESC')
        ->getQuery()
        ->getResult();

    return $this->render('walk_in_rental/index.html.twig', [
        'availableCars' => $availableCars,
        'customers' => $customers,
        'todayRentals' => $todayRentals,
        'today' => $today->format('Y-m-d'),
    ]);
}
    /**
     * Create a new walk-in rental (immediate approval)
     */
   #[Route('/staff/walk-in-rentals/new', name: 'app_walk_in_rental_new', methods: ['GET', 'POST'])]
#[IsGranted('ROLE_STAFF')]
public function new(Request $request): Response
{
    $rentalRequest = new RentalRequest();
    
    // Pre-set some values for walk-in rentals
    $rentalRequest->setCreatedAt(new \DateTime());
    $rentalRequest->setStatus('approved'); // Walk-ins are immediately approved
    $rentalRequest->setApprovedAt(new \DateTime());
    $rentalRequest->setApprovedBy($this->getUser());
    
    $form = $this->createForm(WalkInRentalType::class, $rentalRequest, [
        'entity_manager' => $this->entityManager,
    ]);
    
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        try {
            // Determine if using existing or new customer
            $customerType = $form->get('customerType')->getData();
            
            if ($customerType === 'existing') {
                // Use existing customer
                $existingCustomer = $form->get('existingCustomer')->getData();
                if (!$existingCustomer) {
                    throw new \Exception('Please select an existing customer');
                }
                $customer = $existingCustomer;
            } else {
                // Create new customer
                $email = $form->get('customerEmail')->getData();
                
                // Check if customer already exists with this email
                $existingCustomer = $this->entityManager->getRepository(Customer::class)
                    ->findOneBy(['email' => $email]);
                
                if ($existingCustomer) {
                    // Customer exists - use the existing one
                    $customer = $existingCustomer;
                    $this->addFlash('info', 'Customer with this email already exists. Using existing customer record.');
                } else {
                    // Create new customer
                    $customer = new Customer();
                    $customer->setFirstName($form->get('customerFirstName')->getData());
                    $customer->setLastName($form->get('customerLastName')->getData());
                    $customer->setEmail($email);
                    $customer->setPhone($form->get('customerPhone')->getData());
                    $customer->setLicenseNumber($form->get('customerLicense')->getData());
                    $customer->setNotes($form->get('customerNotes')->getData());
                    $customer->setCreatedAt(new \DateTimeImmutable());
                    $customer->setUpdatedAt(new \DateTimeImmutable());
                    
                    $this->entityManager->persist($customer);
                    $this->addFlash('success', 'New customer created successfully.');
                }
            }
            
            // Set the customer on the rental request
            $rentalRequest->setCustomer($customer);
            
            // Calculate total price based on daily rate and number of days
            $car = $rentalRequest->getCar();
            $startDate = $rentalRequest->getStartDate();
            $endDate = $rentalRequest->getEndDate();
            
            if ($car && $startDate && $endDate) {
                $dailyRate = $car->getDailyRate() ?? $car->getPrice() ?? 0;
                $days = $startDate->diff($endDate)->days + 1; // Inclusive of start date
                $totalPrice = $dailyRate * $days;
                
                $rentalRequest->setTotalPrice((string)$totalPrice);
            }

            // Update car status to rented
            if ($car) {
                $rentedStatus = $this->entityManager->getRepository(CarStatus::class)
                    ->findOneBy(['code' => 'rented']);
                
                if ($rentedStatus) {
                    $car->setStatus($rentedStatus);
                    $car->setUpdatedAt(new \DateTime());
                }
            }

            // Save the rental
            $this->entityManager->persist($rentalRequest);
            $this->entityManager->flush();

            // Log the activity
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'CREATE_WALK_IN_RENTAL',
                'Created walk-in rental for customer: ' . $customer->getDisplayName(),
                [
                    'rental_request_id' => $rentalRequest->getId(),
                    'car_id' => $car ? $car->getId() : null,
                    'car_make' => $car ? $car->getMake() : null,
                    'car_model' => $car ? $car->getModel() : null,
                    'customer_id' => $customer->getId(),
                    'customer_name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
                    'customer_email' => $customer->getEmail(),
                    'start_date' => $rentalRequest->getStartDate() ? $rentalRequest->getStartDate()->format('Y-m-d') : null,
                    'end_date' => $rentalRequest->getEndDate() ? $rentalRequest->getEndDate()->format('Y-m-d') : null,
                    'total_price' => $rentalRequest->getTotalPrice(),
                    'walk_in' => true,
                    'customer_type' => $customerType,
                    'approved_by' => $this->getUser()->getEmail()
                ],
                'STAFF'
            );

            $this->addFlash('success', 
                'Walk-in rental created successfully! ' .
                'Total: $' . number_format($rentalRequest->getTotalPrice(), 2)
            );

            return $this->redirectToRoute('app_walk_in_rental_index');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Error creating rental: ' . $e->getMessage());
        }
    }

    return $this->render('walk_in_rental/new.html.twig', [
        'form' => $form->createView(),
        'rentalRequest' => $rentalRequest,
    ]);
}

/**
 * View completed walk-in rentals history
 */
#[Route('/staff/walk-in-rentals/history', name: 'app_walk_in_rental_history')]
#[IsGranted('ROLE_STAFF')]
public function history(Request $request): Response
{
    // Get filter parameters from request
    $startDate = $request->query->get('start_date');
    $endDate = $request->query->get('end_date');
    $customerSearch = $request->query->get('customer');
    
    // Build base query
    $qb = $this->entityManager->getRepository(RentalRequest::class)
        ->createQueryBuilder('r')
        ->where('r.status = :status')
        ->andWhere('r.returnedAt IS NOT NULL')
        ->setParameter('status', 'completed')
        ->orderBy('r.returnedAt', 'DESC');
    
    // Apply filters if provided
    if ($startDate) {
        $qb->andWhere('r.returnedAt >= :start')
           ->setParameter('start', new \DateTime($startDate . ' 00:00:00'));
    }
    
    if ($endDate) {
        $qb->andWhere('r.returnedAt <= :end')
           ->setParameter('end', new \DateTime($endDate . ' 23:59:59'));
    }
    
    if ($customerSearch) {
        $qb->join('r.customer', 'c')
           ->andWhere('c.firstName LIKE :search OR c.lastName LIKE :search OR c.email LIKE :search OR c.phoneNumber LIKE :search')
           ->setParameter('search', '%' . $customerSearch . '%');
    }
    
    // Get results
    $completedRentals = $qb->getQuery()->getResult();
    
    // Calculate statistics
    $totalRevenue = 0;
    $totalDays = 0;
    $monthlyCount = 0;
    $currentMonthStart = new \DateTime('first day of this month 00:00:00');
    $currentMonthEnd = new \DateTime('last day of this month 23:59:59');
    
    foreach ($completedRentals as $rental) {
        // Calculate revenue
        $price = (float) $rental->getTotalPrice();
        $totalRevenue += $price;
        
        // Calculate rental days
        if ($rental->getStartDate() && $rental->getEndDate()) {
            $days = $rental->getStartDate()->diff($rental->getEndDate())->days + 1;
            $totalDays += $days;
        }
        
        // Count this month's rentals
        if ($rental->getReturnedAt() >= $currentMonthStart && $rental->getReturnedAt() <= $currentMonthEnd) {
            $monthlyCount++;
        }
    }
    
    // Calculate averages
    $avgRentalDays = count($completedRentals) > 0 ? $totalDays / count($completedRentals) : 0;
    
    return $this->render('walk_in_rental/history.html.twig', [
        'completedRentals' => $completedRentals,
        'totalRevenue' => $totalRevenue,
        'monthlyCount' => $monthlyCount,
        'avgRentalDays' => $avgRentalDays,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'customerSearch' => $customerSearch,
        'currentPage' => 1, // For pagination if you implement it later
        'totalPages' => 1,  // For pagination if you implement it later
    ]);
}

    /**
     * Quick rental modal endpoint - creates rental with minimal data
     */
   // In WalkInRentalController.php - update quickRental() method:

/**
 * Quick rental modal endpoint - creates rental with minimal data
 */
#[Route('/staff/walk-in-rentals/quick', name: 'app_walk_in_rental_quick', methods: ['POST'])]
#[IsGranted('ROLE_STAFF')]
public function quickRental(Request $request): Response
{
    if ($request->isXmlHttpRequest() || $request->getContentType() === 'json') {
        $data = json_decode($request->getContent(), true);
        
        try {
            // Get or create customer
            if ($data['customer_type'] === 'new') {
                // Check if customer already exists with this email
                $existingCustomer = $this->entityManager->getRepository(Customer::class)
                    ->findOneBy(['email' => $data['customer']['email']]);
                
                if ($existingCustomer) {
                    $customer = $existingCustomer;
                    // Update customer info if provided
                    if (!empty($data['customer']['phone'])) {
                        $customer->setPhone($data['customer']['phone']);
                    }
                    if (!empty($data['customer']['license_number'])) {
                        $customer->setLicenseNumber($data['customer']['license_number']);
                    }
                    $customer->setUpdatedAt(new \DateTimeImmutable());
                } else {
                    // Create new customer
                    $customer = new Customer();
                    $customer->setFirstName($data['customer']['first_name']);
                    $customer->setLastName($data['customer']['last_name']);
                    $customer->setEmail($data['customer']['email']);
                    $customer->setPhone($data['customer']['phone'] ?? null);
                    $customer->setLicenseNumber($data['customer']['license_number'] ?? null);
                    $customer->setCreatedAt(new \DateTimeImmutable());
                    $customer->setUpdatedAt(new \DateTimeImmutable());
                    
                    $this->entityManager->persist($customer);
                    $this->addFlash('info', 'New customer created successfully.');
                }
            } else {
                // Use existing customer
                $customer = $this->entityManager->getRepository(Customer::class)->find($data['customer_id']);
                if (!$customer) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Customer not found'
                    ], 404);
                }
            }
            
            // Get car
            $car = $this->entityManager->getRepository(Car::class)->find($data['car_id']);
            if (!$car) {
                return $this->json([
                    'success' => false,
                    'message' => 'Car not found'
                ], 404);
            }
            
            // Check if car is available
            $availableStatus = $this->entityManager->getRepository(CarStatus::class)
                ->findOneBy(['code' => 'available']);
            
            if (!$car->getStatus() || $car->getStatusCode() !== 'available') {
                return $this->json([
                    'success' => false,
                    'message' => 'Selected car is not available for rental'
                ], 400);
            }
            
            // Create rental request
            $rentalRequest = new RentalRequest();
            $rentalRequest->setCreatedAt(new \DateTime());
            $rentalRequest->setCustomer($customer);
            $rentalRequest->setCar($car);
            $rentalRequest->setStartDate(new \DateTime($data['start_date']));
            $rentalRequest->setEndDate(new \DateTime($data['end_date']));
            $rentalRequest->setStatus('approved');
            $rentalRequest->setApprovedAt(new \DateTime());
            $rentalRequest->setApprovedBy($this->getUser());
            
            if (!empty($data['notes'])) {
                $rentalRequest->setNotes($data['notes']);
            }
            
            // Calculate price
            $dailyRate = $car->getDailyRate() ?? $car->getPrice() ?? 0;
            $days = $rentalRequest->getStartDate()->diff($rentalRequest->getEndDate())->days + 1;
            $totalPrice = $dailyRate * $days;
            $rentalRequest->setTotalPrice((string)$totalPrice);
            
            // Update car status to rented
            $rentedStatus = $this->entityManager->getRepository(CarStatus::class)
                ->findOneBy(['code' => 'rented']);
            
            if ($rentedStatus) {
                $car->setStatus($rentedStatus);
                $car->setUpdatedAt(new \DateTime());
            }
            
            $this->entityManager->persist($rentalRequest);
            $this->entityManager->flush();
            
            // Log activity
            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'QUICK_WALK_IN_RENTAL',
                'Quick walk-in rental created for: ' . $customer->getDisplayName(),
                [
                    'rental_request_id' => $rentalRequest->getId(),
                    'car_id' => $car->getId(),
                    'car_make' => $car->getMake(),
                    'car_model' => $car->getModel(),
                    'customer_id' => $customer->getId(),
                    'customer_name' => $customer->getFirstName() . ' ' . $customer->getLastName(),
                    'customer_email' => $customer->getEmail(),
                    'start_date' => $rentalRequest->getStartDate()->format('Y-m-d'),
                    'end_date' => $rentalRequest->getEndDate()->format('Y-m-d'),
                    'total_price' => $totalPrice,
                    'customer_type' => $data['customer_type'],
                    'approved_by' => $this->getUser()->getEmail()
                ],
                'STAFF'
            );
            
            return $this->json([
                'success' => true,
                'message' => 'Rental created successfully!',
                'rental_id' => $rentalRequest->getId(),
                'total_price' => number_format($totalPrice, 2),
                'customer_name' => $customer->getFirstName() . ' ' . $customer->getLastName()
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    return $this->json(['success' => false, 'message' => 'Invalid request'], 400);
}
    /**
     * Complete walk-in rental (mark as returned)
     */
    /**
 * Complete walk-in rental (mark as returned)
 */
/**
 * Complete walk-in rental (mark as returned)
 */
#[Route('/staff/walk-in-rentals/{id}/complete', name: 'app_walk_in_rental_complete', methods: ['POST'])]
#[IsGranted('ROLE_STAFF')]
public function complete(RentalRequest $rentalRequest, Request $request): Response
{
    // Verify this is a walk-in/approved rental
    if ($rentalRequest->getStatus() !== 'approved') {
        $this->addFlash('error', 'Only approved rentals can be completed.');
        return $this->redirectToRoute('app_walk_in_rental_index');
    }

    if ($this->isCsrfTokenValid('complete'.$rentalRequest->getId(), $request->request->get('_token'))) {
        try {
            $car = $rentalRequest->getCar();
            
            // ✅ CRITICAL: Change rental status to "completed"
            $rentalRequest->setStatus('completed');
            $rentalRequest->setReturnedAt(new \DateTime()); // Add this field to your entity
            
            if ($car) {
                $availableStatus = $this->entityManager->getRepository(CarStatus::class)
                    ->findOneBy(['code' => 'available']);
                
                if ($availableStatus) {
                    $car->setStatus($availableStatus);
                    $car->setUpdatedAt(new \DateTime());
                }
            }
            
            // Add return notes if provided
            $returnNotes = $request->request->get('return_notes');
            if ($returnNotes) {
                $existingNotes = $rentalRequest->getNotes() ?? '';
                $rentalRequest->setNotes($existingNotes . "\n\nRETURNED: " . $returnNotes);
            }

            $this->entityManager->flush();

            $this->activityLogger->log(
                $this->getUser()->getEmail(),
                'COMPLETE_WALK_IN_RENTAL',
                'Completed walk-in rental #' . $rentalRequest->getId(),
                [
                    'rental_request_id' => $rentalRequest->getId(),
                    'car_id' => $car ? $car->getId() : null,
                    'customer_id' => $rentalRequest->getCustomer()->getId()
                ],
                'STAFF'
            );

            $this->addFlash('success', 'Rental completed! Car is now available and rental moved to history.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error completing rental: ' . $e->getMessage());
        }
    }

    return $this->redirectToRoute('app_walk_in_rental_index');
}

    /**
     * Get available cars for selected dates (AJAX endpoint)
     */
    #[Route('/staff/walk-in-rentals/available-cars', name: 'app_walk_in_rental_available_cars')]
    #[IsGranted('ROLE_STAFF')]
    public function getAvailableCars(Request $request): Response
    {
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        
        if (!$startDate || !$endDate) {
            return $this->json(['error' => 'Start and end dates are required'], 400);
        }

        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
            
            // Get all available cars
            $availableStatus = $this->entityManager->getRepository(CarStatus::class)
                ->findOneBy(['code' => 'available']);
            
            $allAvailableCars = $availableStatus ? 
                $this->entityManager->getRepository(Car::class)->findBy(['status' => $availableStatus]) : 
                [];
            
            // Filter out cars that are already rented during this period
            $rentedCarIds = $this->entityManager->getRepository(RentalRequest::class)
                ->createQueryBuilder('r')
                ->select('IDENTITY(r.car) as car_id')
                ->where('r.status = :status')
                ->andWhere('r.startDate <= :end')
                ->andWhere('r.endDate >= :start')
                ->setParameter('status', 'approved')
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getResult();
            
            $rentedCarIds = array_column($rentedCarIds, 'car_id');
            
            $availableCars = array_filter($allAvailableCars, function($car) use ($rentedCarIds) {
                return !in_array($car->getId(), $rentedCarIds);
            });

            $carData = array_map(function($car) {
                return [
                    'id' => $car->getId(),
                    'make' => $car->getMake(),
                    'model' => $car->getModel(),
                    'year' => $car->getYear(),
                    'price' => $car->getPrice(),
                    'daily_rate' => $car->getDailyRate(),
                    'image' => $car->getImage()
                ];
            }, array_values($availableCars));

            return $this->json([
                'success' => true,
                'cars' => $carData,
                'count' => count($carData)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], 400);
        }
    }
    
}