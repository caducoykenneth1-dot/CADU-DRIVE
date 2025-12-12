<?php

// src/Controller/RentalController.php
namespace App\Controller;

use App\Entity\RentalRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RentalController extends AbstractController
{
    #[Route('/rental/{id}', name: 'app_rental_show', methods: ['GET'])]
    public function show(RentalRequest $rental): Response
    {
        return $this->render('rental/show.html.twig', [
            'rental' => $rental,
        ]);
    }
}
