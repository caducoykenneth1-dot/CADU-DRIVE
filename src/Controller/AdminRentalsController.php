<?php

namespace App\Controller;

// CHANGE FROM:
// use App\Entity\Rental;
// use App\Repository\RentalRepository;

// TO:
use App\Entity\RentalRequest;
use App\Repository\RentalRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminRentalsController extends AbstractController
{
    #[Route('/admin/rentals', name: 'app_admin_rentals')]
    public function index(RentalRequestRepository $rentalRequestRepository): Response
    {
        // CHANGE FROM:
        // $rentals = $rentalRepository->findAll();
        
        // TO:
        $rentalRequests = $rentalRequestRepository->findAll();
        
        return $this->render('admin/rentals.html.twig', [
            // CHANGE FROM:
            // 'rentals' => $rentals,
            
            // TO:
            'rentalRequests' => $rentalRequests,
        ]);
    }
}