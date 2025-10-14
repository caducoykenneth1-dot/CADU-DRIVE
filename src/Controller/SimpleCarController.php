<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Demo controller kept for the simplified car listing example.
 */
final class SimpleCarController extends AbstractController
{
    /**
     * Render the lightweight car page that was generated for tutorials.
     */
    #[Route('/simple-cars', name: 'simple_car_index')]
    public function index(): Response
    {
        return $this->render('car/simple_index.html.twig', [
            'controller_name' => 'SimpleCarController',
        ]);
    }
}
