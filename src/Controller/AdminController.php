<?php

namespace App\Controller;

use App\Repository\CarRepository;
use App\Repository\CustomerRepository;
use App\Repository\RentalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Primary entry point for admin operations dashboard.
 */
class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(
        CustomerRepository $customerRepository,
        CarRepository $carRepository,
        RentalRepository $rentalRepository,
    ): Response {
        $now = new \DateTimeImmutable();
        $startOfMonth = $now->modify('first day of this month midnight');

        $totalCustomers = $customerRepository->count([]);
        $newCustomersThisMonth = (int) $customerRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdAt >= :startOfMonth')
            ->setParameter('startOfMonth', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        $totalCars = $carRepository->count([]);
        $totalRentals = $rentalRepository->count([]);

        $activeRentals = (int) $rentalRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.startDate <= :now')
            ->andWhere('r.endDate >= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        $upcomingReturn = $rentalRepository->createQueryBuilder('r')
            ->where('r.endDate >= :now')
            ->setParameter('now', $now)
            ->orderBy('r.endDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $recentCustomers = $customerRepository->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()
            ->getResult();

        $recentRentals = $rentalRepository->createQueryBuilder('r')
            ->orderBy('r.startDate', 'DESC')
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

        return $this->render('admin/dashboard.html.twig', [
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
            'recentRentals' => $recentRentals,
            'fleetSnapshot' => $fleetSnapshot,
        ]);
    }
}
