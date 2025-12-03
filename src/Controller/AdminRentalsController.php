<?php

namespace App\Controller;

use App\Repository\RentalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class AdminRentalsController extends AbstractController
{
    #[Route('/admin/rentals', name: 'app_admin_rentals')]
    public function index(RentalRepository $rentalRepository): Response
    {
        $rentals = $rentalRepository->findAll();

        return $this->render('admin/rentals.html.twig', [
            'rentals' => $rentals,
        ]);
    }
}
